<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Notifications\AdminTicketNotification;
use App\Notifications\TicketRepliedNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Permintaan layanan server lewat tiket.
 *
 * Server dibeli retail tanpa API supplier, jadi panel tidak menjalankan
 * start/stop/reboot/reinstall sendiri. Pelanggan mengajukan permintaan,
 * admin mengerjakan di dashboard supplier lalu menutupnya dari panel admin.
 */
class VpsServiceRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Notification::fake();
    }

    private function admin(): User
    {
        return User::where('is_admin', true)->firstOrFail();
    }

    /**
     * @return array{0: User, 1: VpsInstance}
     */
    private function customerWithVps(array $vpsAttributes = [], string $username = 'reqcustomer'): array
    {
        $customer = User::create([
            'username' => $username,
            'email' => $username . '@example.com',
            'full_name' => 'Pelanggan ' . $username,
            'password' => Hash::make('password123'),
            'channel' => 'website',
            'is_admin' => false,
        ]);
        $org = $customer->createPersonalOrganization();
        $customer->switchToOrganization($org);

        // Paket VPS biasa (bukan AI/Database) agar stack bisa dipilih.
        $spec = VpsSpec::where('category', 'vps')->first() ?? VpsSpec::first();

        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'coolify',
            'provider' => 'tencent',
            'hostname' => 'srv-' . $username,
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'active',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => $spec->sell_price,
            'paid_at' => now(),
        ]);

        $vps = VpsInstance::create(array_merge([
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'order_id' => $order->id,
            'hostname' => 'srv-' . $username,
            'public_ip' => '139.180.200.20',
            'os' => 'ubuntu2404',
            'status' => 'running',
            'cpu' => 2,
            'ram' => 4,
            'disk' => 60,
            'control_panel' => 'coolify',
            'provider' => 'tencent',
            'initial_root_password' => 'PasswordAwal#123',
            'expires_at' => now()->addDays(20),
        ], $vpsAttributes));

        return [$customer, $vps];
    }

    private function reinstallPayload(VpsInstance $vps, array $overrides = []): array
    {
        return array_merge([
            'os' => 'ubuntu2204',
            'control_panel' => 'dokploy',
            'confirm_hostname' => $vps->hostname,
            'notes' => 'Mohon aktifkan swap.',
        ], $overrides);
    }

    // ======================= Aksi daya palsu sudah dihapus =======================

    public function test_old_power_action_routes_no_longer_exist(): void
    {
        [$customer, $vps] = $this->customerWithVps();

        foreach (['start', 'stop', 'reboot', 'force-reboot', 'reinstall'] as $action) {
            $this->actingAs($customer)
                ->post("/dashboard/vps/{$vps->id}/{$action}")
                ->assertNotFound();
        }

        $this->assertSame('running', $vps->fresh()->status);
    }

    public function test_detail_page_shows_honest_actions_only(): void
    {
        [$customer, $vps] = $this->customerWithVps();

        $this->actingAs($customer)->get("/dashboard/vps/{$vps->id}")
            ->assertOk()
            ->assertSee('Cara Reboot')
            ->assertSee('Ajukan Reinstall OS')
            ->assertSee('sudo reboot')
            ->assertSee('Aktif')
            ->assertDontSee('Nyalakan Server')
            ->assertDontSee('Force Reset')
            ->assertDontSee('Matikan (Stop)');
    }

    public function test_dashboard_list_has_no_power_buttons(): void
    {
        [$customer] = $this->customerWithVps();

        $this->actingAs($customer)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Matikan Server')
            ->assertDontSee('Nyalakan Server')
            ->assertDontSee('Reboot Server');
    }

    // ======================= Pengajuan reinstall =======================

    public function test_customer_reinstall_request_creates_ticket_without_touching_server(): void
    {
        [$customer, $vps] = $this->customerWithVps();

        $response = $this->actingAs($customer)
            ->post("/dashboard/vps/{$vps->id}/requests/reinstall", $this->reinstallPayload($vps));

        $response->assertRedirect(route('dashboard.vps.show', $vps->id));
        $response->assertSessionHas('success');

        $ticket = SupportTicket::where('vps_instance_id', $vps->id)->firstOrFail();
        $this->assertSame(SupportTicket::TYPE_REINSTALL, $ticket->type);
        $this->assertSame('high', $ticket->priority);
        $this->assertSame('open', $ticket->status);
        $this->assertSame(['os' => 'ubuntu2204', 'control_panel' => 'dokploy'], $ticket->request_data);
        $this->assertSame($customer->current_organization_id, $ticket->organization_id);
        $this->assertStringContainsString('Mohon aktifkan swap.', $ticket->messages()->first()->message);

        // Server tidak berubah sampai admin benar-benar mengerjakan.
        $fresh = $vps->fresh();
        $this->assertSame('ubuntu2404', $fresh->os);
        $this->assertSame('coolify', $fresh->control_panel);
        $this->assertSame('PasswordAwal#123', $fresh->initial_root_password);
        $this->assertSame('running', $fresh->status);

        $this->assertDatabaseHas('vps_activity_logs', [
            'vps_instance_id' => $vps->id,
            'action' => 'reinstall_requested',
            'status' => 'submitted',
        ]);

        Notification::assertSentOnDemand(AdminTicketNotification::class);
    }

    public function test_reinstall_requires_matching_hostname_confirmation(): void
    {
        [$customer, $vps] = $this->customerWithVps();

        $this->actingAs($customer)
            ->from("/dashboard/vps/{$vps->id}")
            ->post("/dashboard/vps/{$vps->id}/requests/reinstall", $this->reinstallPayload($vps, ['confirm_hostname' => 'server-lain']))
            ->assertRedirect("/dashboard/vps/{$vps->id}")
            ->assertSessionHasErrors('confirm_hostname');

        $this->assertSame(0, SupportTicket::count());
    }

    public function test_reinstall_hostname_confirmation_ignores_case_and_spaces(): void
    {
        [$customer, $vps] = $this->customerWithVps();

        $this->actingAs($customer)
            ->post("/dashboard/vps/{$vps->id}/requests/reinstall", $this->reinstallPayload($vps, [
                'confirm_hostname' => '  ' . strtoupper($vps->hostname) . ' ',
            ]))
            ->assertSessionHas('success');

        $this->assertSame(1, SupportTicket::count());
    }

    public function test_reinstall_rejects_os_and_stack_outside_allowed_list(): void
    {
        [$customer, $vps] = $this->customerWithVps();

        $this->actingAs($customer)
            ->post("/dashboard/vps/{$vps->id}/requests/reinstall", $this->reinstallPayload($vps, [
                'os' => 'windows95',
                'control_panel' => 'managed_database',
            ]))
            ->assertSessionHasErrors(['os', 'control_panel']);

        $this->assertSame(0, SupportTicket::count());
    }

    public function test_duplicate_reinstall_request_reuses_open_ticket(): void
    {
        [$customer, $vps] = $this->customerWithVps();

        $this->actingAs($customer)
            ->post("/dashboard/vps/{$vps->id}/requests/reinstall", $this->reinstallPayload($vps));
        $ticket = SupportTicket::firstOrFail();

        $this->actingAs($customer)
            ->post("/dashboard/vps/{$vps->id}/requests/reinstall", $this->reinstallPayload($vps))
            ->assertRedirect(route('dashboard.support.show', $ticket->id))
            ->assertSessionHas('info');

        $this->assertSame(1, SupportTicket::count());
        Notification::assertSentOnDemandTimes(AdminTicketNotification::class, count(\App\Support\MailIdentity::adminRecipients()));
    }

    public function test_new_reinstall_allowed_after_previous_request_resolved(): void
    {
        [$customer, $vps] = $this->customerWithVps();

        $this->actingAs($customer)
            ->post("/dashboard/vps/{$vps->id}/requests/reinstall", $this->reinstallPayload($vps));
        SupportTicket::firstOrFail()->update(['status' => 'resolved', 'resolved_at' => now()]);

        $this->actingAs($customer)
            ->post("/dashboard/vps/{$vps->id}/requests/reinstall", $this->reinstallPayload($vps))
            ->assertSessionHas('success');

        $this->assertSame(2, SupportTicket::where('type', SupportTicket::TYPE_REINSTALL)->count());
    }

    public function test_reinstall_blocked_for_expired_or_suspended_server(): void
    {
        [$customer, $vps] = $this->customerWithVps(['expires_at' => now()->subDay()]);

        $this->actingAs($customer)
            ->post("/dashboard/vps/{$vps->id}/requests/reinstall", $this->reinstallPayload($vps))
            ->assertSessionHas('error');

        [$suspendedCustomer, $suspendedVps] = $this->customerWithVps(['status' => 'suspended'], 'suspendeduser');

        $this->actingAs($suspendedCustomer)
            ->post("/dashboard/vps/{$suspendedVps->id}/requests/reinstall", $this->reinstallPayload($suspendedVps))
            ->assertSessionHas('error');

        $this->assertSame(0, SupportTicket::count());
    }

    public function test_customer_cannot_request_for_other_organization_server(): void
    {
        [, $vps] = $this->customerWithVps();
        [$otherCustomer] = $this->customerWithVps([], 'otheruser');

        $this->actingAs($otherCustomer)
            ->post("/dashboard/vps/{$vps->id}/requests/reinstall", $this->reinstallPayload($vps))
            ->assertNotFound();

        $this->actingAs($otherCustomer)
            ->post("/dashboard/vps/{$vps->id}/requests/unreachable")
            ->assertNotFound();

        $this->assertSame(0, SupportTicket::where('vps_instance_id', $vps->id)->count());
    }

    public function test_fixed_stack_package_keeps_current_stack(): void
    {
        [$customer, $vps] = $this->customerWithVps(['control_panel' => 'managed_database', 'db_engine' => 'postgresql']);
        // Paket database ditentukan dari spec order bila ada, jadi lepaskan order agar
        // deteksi memakai kolom instance.
        $vps->update(['order_id' => null]);

        $this->actingAs($customer)
            ->post("/dashboard/vps/{$vps->id}/requests/reinstall", [
                'os' => 'ubuntu2204',
                'control_panel' => 'coolify', // diabaikan untuk paket dengan stack tetap
                'confirm_hostname' => $vps->hostname,
            ])
            ->assertSessionHas('success');

        $this->assertSame('managed_database', SupportTicket::firstOrFail()->request_data['control_panel']);
    }

    // ======================= Laporan server tidak bisa diakses =======================

    public function test_customer_can_report_unreachable_server_once(): void
    {
        [$customer, $vps] = $this->customerWithVps();

        $this->actingAs($customer)
            ->post("/dashboard/vps/{$vps->id}/requests/unreachable", ['description' => 'SSH timeout sejak pagi.'])
            ->assertRedirect(route('dashboard.vps.show', $vps->id))
            ->assertSessionHas('success');

        $ticket = SupportTicket::firstOrFail();
        $this->assertSame(SupportTicket::TYPE_UNREACHABLE, $ticket->type);
        $this->assertSame('high', $ticket->priority);
        $this->assertStringContainsString('SSH timeout sejak pagi.', $ticket->messages()->first()->message);
        $this->assertDatabaseHas('vps_activity_logs', ['vps_instance_id' => $vps->id, 'action' => 'unreachable_reported']);

        // Laporan kedua saat yang pertama masih terbuka diarahkan ke tiket yang sama.
        $this->actingAs($customer)
            ->post("/dashboard/vps/{$vps->id}/requests/unreachable")
            ->assertRedirect(route('dashboard.support.show', $ticket->id));

        $this->assertSame(1, SupportTicket::count());
        $this->assertSame('running', $vps->fresh()->status);
    }

    public function test_open_request_shows_banner_on_detail_page(): void
    {
        [$customer, $vps] = $this->customerWithVps();

        $this->actingAs($customer)
            ->post("/dashboard/vps/{$vps->id}/requests/reinstall", $this->reinstallPayload($vps));

        $this->actingAs($customer)->get("/dashboard/vps/{$vps->id}")
            ->assertOk()
            ->assertSee('Reinstall OS sedang menunggu diproses tim')
            ->assertSee('Reinstall Sedang Diproses')
            ->assertDontSee('Ajukan Reinstall OS');
    }

    // ======================= Admin menyelesaikan reinstall =======================

    public function test_admin_completes_reinstall_and_customer_is_notified(): void
    {
        [$customer, $vps] = $this->customerWithVps(['root_password_revealed_at' => now()]);

        $this->actingAs($customer)
            ->post("/dashboard/vps/{$vps->id}/requests/reinstall", $this->reinstallPayload($vps));
        $ticket = SupportTicket::firstOrFail();

        $this->actingAs($this->admin())->get(route('admin.tickets.show', $ticket->id))
            ->assertOk()
            ->assertSee('Tandai Reinstall Selesai');

        $this->actingAs($this->admin())
            ->post(route('admin.tickets.complete-reinstall', $ticket->id), [
                'os' => 'ubuntu2204',
                'control_panel' => 'dokploy',
                'root_password' => 'PasswordBaru#456',
            ])
            ->assertSessionHas('success');

        $fresh = $vps->fresh();
        $this->assertSame('ubuntu2204', $fresh->os);
        $this->assertSame('dokploy', $fresh->control_panel);
        $this->assertSame('PasswordBaru#456', $fresh->initial_root_password);
        $this->assertNull($fresh->root_password_revealed_at);

        // Password disimpan terenkripsi di database.
        $raw = DB::table('vps_instances')->where('id', $vps->id)->value('initial_root_password');
        $this->assertNotSame('PasswordBaru#456', $raw);

        $ticket->refresh();
        $this->assertSame('resolved', $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertNotNull($ticket->first_response_at);

        $reply = $ticket->messages()->where('is_admin_reply', true)->firstOrFail();
        $this->assertFalse((bool) $reply->is_internal);
        $this->assertStringNotContainsString('PasswordBaru#456', $reply->message);

        $this->assertDatabaseHas('vps_activity_logs', ['vps_instance_id' => $vps->id, 'action' => 'reinstall_completed']);

        Notification::assertSentTo($customer, TicketRepliedNotification::class, function ($notification) {
            return !str_contains($notification->replyMessage, 'PasswordBaru#456');
        });

        // Setelah selesai, pelanggan bisa mengajukan reinstall lagi.
        $this->actingAs($customer)->get("/dashboard/vps/{$vps->id}")
            ->assertSee('Ajukan Reinstall OS');
    }

    public function test_admin_cannot_complete_closed_or_non_reinstall_ticket(): void
    {
        [$customer, $vps] = $this->customerWithVps();

        $this->actingAs($customer)
            ->post("/dashboard/vps/{$vps->id}/requests/unreachable");
        $unreachable = SupportTicket::firstOrFail();

        $payload = ['os' => 'ubuntu2204', 'control_panel' => 'dokploy', 'root_password' => 'PasswordBaru#456'];

        $this->actingAs($this->admin())
            ->post(route('admin.tickets.complete-reinstall', $unreachable->id), $payload)
            ->assertSessionHas('error');

        $this->actingAs($customer)
            ->post("/dashboard/vps/{$vps->id}/requests/reinstall", $this->reinstallPayload($vps));
        $reinstall = SupportTicket::where('type', SupportTicket::TYPE_REINSTALL)->firstOrFail();
        $reinstall->update(['status' => 'closed']);

        $this->actingAs($this->admin())
            ->post(route('admin.tickets.complete-reinstall', $reinstall->id), $payload)
            ->assertSessionHas('error');

        $this->assertSame('PasswordAwal#123', $vps->fresh()->initial_root_password);
    }

    public function test_admin_complete_reinstall_validates_password(): void
    {
        [$customer, $vps] = $this->customerWithVps();

        $this->actingAs($customer)
            ->post("/dashboard/vps/{$vps->id}/requests/reinstall", $this->reinstallPayload($vps));
        $ticket = SupportTicket::firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.tickets.complete-reinstall', $ticket->id), [
                'os' => 'ubuntu2204',
                'control_panel' => 'dokploy',
                'root_password' => 'short',
            ])
            ->assertSessionHasErrors('root_password');

        $this->assertSame('open', $ticket->fresh()->status);
        $this->assertSame('PasswordAwal#123', $vps->fresh()->initial_root_password);
    }

    public function test_customer_cannot_access_admin_complete_route(): void
    {
        [$customer, $vps] = $this->customerWithVps();

        $this->actingAs($customer)
            ->post("/dashboard/vps/{$vps->id}/requests/reinstall", $this->reinstallPayload($vps));
        $ticket = SupportTicket::firstOrFail();

        $this->actingAs($customer)
            ->post(route('admin.tickets.complete-reinstall', $ticket->id), [
                'os' => 'ubuntu2204',
                'control_panel' => 'dokploy',
                'root_password' => 'PasswordBaru#456',
            ]);

        $this->assertSame('open', $ticket->fresh()->status);
        $this->assertSame('PasswordAwal#123', $vps->fresh()->initial_root_password);
    }

    public function test_admin_ticket_list_filters_by_type(): void
    {
        [$customer, $vps] = $this->customerWithVps();

        $this->actingAs($customer)
            ->post("/dashboard/vps/{$vps->id}/requests/reinstall", $this->reinstallPayload($vps));
        $this->actingAs($customer)->post('/dashboard/support', [
            'subject' => 'Pertanyaan umum domain',
            'priority' => 'low',
            'message' => 'Bagaimana cara mengarahkan domain ke server saya?',
        ]);

        $this->actingAs($this->admin())->get(route('admin.tickets', ['type' => 'reinstall']))
            ->assertOk()
            ->assertSee('Permintaan Reinstall OS')
            ->assertDontSee('Pertanyaan umum domain')
            ->assertSee('permintaan server menunggu dikerjakan');
    }

    // ======================= Jadwal latar belakang =======================

    public function test_unused_background_jobs_are_not_scheduled(): void
    {
        $commands = collect(app(Schedule::class)->events())
            ->map(fn ($event) => (string) $event->command)
            ->implode("\n");

        $this->assertStringNotContainsString('provider:reconcile', $commands);
        $this->assertStringNotContainsString('maintenance:sync', $commands);
        $this->assertStringContainsString('billing:renew', $commands);
    }
}
