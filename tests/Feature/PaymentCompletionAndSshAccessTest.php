<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Notifications\AdminFulfillmentOverdueNotification;
use App\Notifications\AdminNewPaidOrderNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\VpsProvisionedNotification;
use App\Notifications\VpsSshUsernameChangedNotification;
use App\Services\Payments\XenditService;
use App\Services\RenewalService;
use App\Services\SettingsService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Perbaikan production 13 Okt 2026:
 *  1. Notifikasi "setelah lunas" lewat satu titik pusat (Xendit race, renewal).
 *  2. Pengingat bertahap ke admin (WA + email) sampai order diserahkan.
 *  3. Username SSH per VPS (default root) + ikon mata tanpa password akun.
 */
class PaymentCompletionAndSshAccessTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_WA = '6281111111111';
    private const ADMIN_EMAIL = 'ops@vexahost.test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Notification::fake();

        config([
            'whatsapp.enabled' => true,
            'whatsapp.key' => 'test-key',
            'whatsapp.url' => 'https://wa.test',
            'services.xendit.secret_key' => 'xnd_test_secret',
            'services.xendit.webhook_token' => 'tok_test',
        ]);

        app(SettingsService::class)->setMany([
            'mail_admin_addresses' => self::ADMIN_EMAIL,
            'admin_notification_email' => self::ADMIN_EMAIL,
            'admin_notification_whatsapp' => '0811-1111-1111',
        ]);
        \App\Support\MailIdentity::flushCache();

        Http::fake([
            'https://api.xendit.co/*' => Http::response(['status' => 'PAID'], 200),
            '*' => Http::response(['success' => true], 200),
        ]);
    }

    // ============================== helpers ==============================

    private function admin(): User
    {
        return User::where('is_admin', true)->firstOrFail();
    }

    private function customer(string $username = 'budi'): User
    {
        $user = User::create([
            'username' => $username,
            'email' => $username . '@example.com',
            'full_name' => 'Pelanggan ' . $username,
            'password' => Hash::make('password123'),
            'channel' => 'website',
            'is_admin' => false,
        ]);
        $org = $user->createPersonalOrganization();
        $user->switchToOrganization($org);

        return $user->fresh();
    }

    private function spec(): VpsSpec
    {
        return VpsSpec::where('category', 'vps')->first() ?? VpsSpec::first();
    }

    private function pendingXenditOrder(User $customer, array $attrs = []): Order
    {
        $spec = $this->spec();

        $order = Order::create(array_merge([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'hostname' => 'srv-' . $customer->username,
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'billing_cycle' => 'monthly',
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'online_payment',
            'amount' => 150000,
            'currency' => 'IDR',
        ], $attrs));

        PaymentTransaction::create([
            'order_id' => $order->id,
            'provider' => 'xendit',
            'provider_transaction_id' => 'inv_' . $order->id,
            'provider_order_ref' => 'VH-ORD-' . $order->id,
            'payment_method' => 'online_payment',
            'amount' => 150000,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);

        return $order;
    }

    private function runningVps(User $customer, array $attrs = []): VpsInstance
    {
        return VpsInstance::create(array_merge([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'hostname' => 'srv-' . $customer->username,
            'public_ip' => '139.180.200.20',
            'ssh_port' => 22,
            'os' => 'ubuntu2404',
            'status' => 'running',
            'cpu' => 2,
            'ram' => 4,
            'disk' => 60,
            'control_panel' => 'none',
            'provider' => 'manual',
            'initial_root_password' => 'PasswordAwal#123',
            'billing_cycle' => 'monthly',
            'expires_at' => now()->addDays(5),
        ], $attrs));
    }

    private function xenditWebhook(Order $order)
    {
        return $this->withHeaders(['x-callback-token' => 'tok_test'])
            ->postJson('/api/payment/xendit/webhook', [
                'id' => 'inv_' . $order->id,
                'external_id' => 'VH-ORD-' . $order->id,
                'status' => 'PAID',
                'amount' => 150000,
                'payment_channel' => 'QRIS',
            ]);
    }

    private function adminWhatsAppMessages(): array
    {
        return collect(Http::recorded())
            ->map(fn ($pair) => $pair[0])
            ->filter(fn (HttpRequest $r) => str_contains($r->url(), '/messages/text') && ($r['to'] ?? null) === self::ADMIN_WA)
            ->map(fn (HttpRequest $r) => (string) $r['message'])
            ->values()
            ->all();
    }

    private function adminMailCount(string $class): int
    {
        return Notification::sent(
            new AnonymousNotifiable,
            $class,
            fn ($n, $channels, $notifiable) => ($notifiable->routes['mail'] ?? null) === self::ADMIN_EMAIL
        )->count();
    }

    // ===================== 1. Titik pusat "setelah lunas" =====================

    public function test_xendit_page_sync_before_webhook_still_notifies_once(): void
    {
        $customer = $this->customer();
        $order = $this->pendingXenditOrder($customer);

        // Pelanggan kembali ke halaman order lebih dulu daripada webhook.
        $this->assertTrue(XenditService::checkAndSyncStatus($order));

        $order->refresh();
        $this->assertSame('paid', $order->status);
        $this->assertNotNull($order->paid_notified_at);

        Notification::assertSentToTimes($customer, PaymentReceivedNotification::class, 1);
        Notification::assertSentOnDemand(AdminNewPaidOrderNotification::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === self::ADMIN_EMAIL);
        $this->assertCount(1, $this->adminWhatsAppMessages());
        $this->assertStringContainsString("ORDER BARU LUNAS", $this->adminWhatsAppMessages()[0]);
        $this->assertStringContainsString("#{$order->id}", $this->adminWhatsAppMessages()[0]);

        // Webhook tiba belakangan: tidak boleh mengirim ulang.
        $this->xenditWebhook($order)->assertOk();

        Notification::assertSentToTimes($customer, PaymentReceivedNotification::class, 1);
        $this->assertSame(1, $this->adminMailCount(AdminNewPaidOrderNotification::class));
        $this->assertCount(1, $this->adminWhatsAppMessages());
    }

    public function test_xendit_webhook_first_notifies_once(): void
    {
        $customer = $this->customer();
        $order = $this->pendingXenditOrder($customer);

        $this->xenditWebhook($order)->assertOk();
        $this->xenditWebhook($order)->assertOk(); // retry dari Xendit

        $this->assertSame('paid', $order->fresh()->status);
        Notification::assertSentToTimes($customer, PaymentReceivedNotification::class, 1);
        $this->assertSame(1, $this->adminMailCount(AdminNewPaidOrderNotification::class));
        $this->assertCount(1, $this->adminWhatsAppMessages());
    }

    public function test_admin_mark_paid_sends_single_customer_email(): void
    {
        $customer = $this->customer();
        $order = $this->pendingXenditOrder($customer, ['payment_method' => 'qris']);

        $this->actingAs($this->admin())
            ->post(route('admin.orders.mark-paid', $order->id), ['note' => 'cek mutasi'])
            ->assertSessionHas('success');

        Notification::assertSentToTimes($customer, PaymentReceivedNotification::class, 1);
        $this->assertCount(1, $this->adminWhatsAppMessages());
    }

    public function test_renewal_paid_via_page_sync_extends_once_even_when_webhook_arrives(): void
    {
        $customer = $this->customer();
        $expires = now()->addDays(5)->startOfMinute();
        $vps = $this->runningVps($customer, ['expires_at' => $expires]);
        $order = $this->pendingXenditOrder($customer, [
            'order_type' => 'renewal',
            'vps_instance_id' => $vps->id,
        ]);

        XenditService::checkAndSyncStatus($order);

        $expected = $expires->copy()->addMonth();
        $this->assertTrue($vps->fresh()->expires_at->equalTo($expected));
        $this->assertNotNull($order->fresh()->renewal_applied_at);
        $this->assertSame('active', $order->fresh()->status);

        // Webhook belakangan + panggilan ulang langsung: tidak boleh menambah lagi.
        $this->xenditWebhook($order)->assertOk();
        app(RenewalService::class)->handleRenewalPayment($order->fresh());

        $this->assertTrue($vps->fresh()->expires_at->equalTo($expected));
        Notification::assertSentToTimes($customer, PaymentReceivedNotification::class, 1);

        $messages = $this->adminWhatsAppMessages();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('PERPANJANGAN LUNAS — PERLU TINDAKAN', $messages[0]);
        $this->assertStringContainsString('perpanjang VPS ini di Supplier', $messages[0]);
    }

    public function test_renewal_repair_command_lists_and_applies_pending_renewals(): void
    {
        $customer = $this->customer();
        $expires = now()->addDays(3)->startOfMinute();
        $vps = $this->runningVps($customer, ['expires_at' => $expires]);

        // Kondisi data production yang rusak: lunas, tapi masa aktif belum ditambah.
        $order = $this->pendingXenditOrder($customer, ['order_type' => 'renewal', 'vps_instance_id' => $vps->id]);
        Order::whereKey($order->id)->update(['status' => 'paid', 'paid_at' => now(), 'paid_notified_at' => now()]);

        $this->artisan('renewal:repair')->expectsOutputToContain('Ditemukan 1')->assertSuccessful();
        $this->assertTrue($vps->fresh()->expires_at->equalTo($expires)); // dry-run tidak mengubah

        $this->artisan('renewal:repair --apply')->assertSuccessful();
        $this->assertTrue($vps->fresh()->expires_at->equalTo($expires->copy()->addMonth()));

        $this->artisan('renewal:repair --apply')->expectsOutputToContain('Aman')->assertSuccessful();
        $this->assertTrue($vps->fresh()->expires_at->equalTo($expires->copy()->addMonth()));
    }

    // ===================== 2. Pengingat bertahap admin =====================

    public function test_admin_reminders_escalate_until_order_delivered(): void
    {
        $customer = $this->customer();
        $order = $this->pendingXenditOrder($customer);
        XenditService::checkAndSyncStatus($order);
        $this->assertCount(1, $this->adminWhatsAppMessages()); // notifikasi lunas

        $reminders = fn () => collect($this->adminWhatsAppMessages())
            ->filter(fn ($m) => str_contains($m, 'PENGINGAT'))->count();

        $this->travel(20)->minutes();
        $this->artisan('fulfillment:remind-admin')->assertSuccessful();
        $this->assertSame(0, $reminders());

        $this->travel(11)->minutes(); // 31 menit
        $this->artisan('fulfillment:remind-admin')->assertSuccessful();
        $this->assertSame(1, $reminders());
        $this->artisan('fulfillment:remind-admin')->assertSuccessful(); // tidak dobel
        $this->assertSame(1, $reminders());

        $this->travel(30)->minutes(); // 61 menit
        $this->artisan('fulfillment:remind-admin')->assertSuccessful();
        $this->assertSame(2, $reminders());

        $this->travel(110)->minutes(); // 110 menit sejak pengingat terakhir
        $this->artisan('fulfillment:remind-admin')->assertSuccessful();
        $this->assertSame(2, $reminders());

        $this->travel(11)->minutes(); // >120 menit
        $this->artisan('fulfillment:remind-admin')->assertSuccessful();
        $this->assertSame(3, $reminders());
        $this->assertSame(3, $this->adminMailCount(AdminFulfillmentOverdueNotification::class));

        // Diserahkan → berhenti.
        Order::whereKey($order->id)->update(['status' => 'active']);
        $this->travel(5)->hours();
        $this->artisan('fulfillment:remind-admin')->assertSuccessful();
        $this->assertSame(3, $reminders());
    }

    public function test_reminders_skip_renewals_and_stale_orders_and_group_into_one_message(): void
    {
        $customer = $this->customer();
        $vps = $this->runningVps($customer);

        $base = ['customer_id' => $customer->id, 'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $this->spec()->id, 'control_panel' => 'none', 'datacenter_location' => 'indonesia', 'os' => 'ubuntu2404',
            'status' => 'paid', 'channel' => 'website', 'payment_method' => 'qris', 'amount' => 100000];

        $a = Order::create($base + ['paid_at' => now()->subHour()]);
        $b = Order::create($base + ['paid_at' => now()->subMinutes(45)]);
        Order::create($base + ['paid_at' => now()->subHour(), 'order_type' => 'renewal', 'vps_instance_id' => $vps->id]);
        Order::create($base + ['paid_at' => now()->subDays(8)]);

        $this->artisan('fulfillment:remind-admin')->assertSuccessful();

        $messages = $this->adminWhatsAppMessages();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('2 ORDER LUNAS BELUM DISETUP', $messages[0]);
        $this->assertStringContainsString("#{$a->id}", $messages[0]);
        $this->assertStringContainsString("#{$b->id}", $messages[0]);
    }

    public function test_reminder_is_retried_when_no_channel_succeeds(): void
    {
        app(SettingsService::class)->setMany(['admin_notification_whatsapp' => '']);
        $mockAlerts = \Mockery::mock(\App\Services\AdminAlertService::class);
        $mockAlerts->shouldReceive('fulfillmentReminder')->andReturn(false);
        $this->app->instance(\App\Services\AdminAlertService::class, $mockAlerts);

        $customer = $this->customer();
        $order = Order::create(['customer_id' => $customer->id, 'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $this->spec()->id, 'control_panel' => 'none', 'datacenter_location' => 'indonesia', 'os' => 'ubuntu2404',
            'status' => 'paid', 'channel' => 'website', 'payment_method' => 'qris', 'amount' => 100000,
            'paid_at' => now()->subHour()]);

        $this->artisan('fulfillment:remind-admin')->assertFailed();
        $this->assertSame(0, $order->fresh()->admin_reminder_count);
    }

    public function test_admin_can_save_whatsapp_number_and_send_test(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.settings.notification'), [
                'admin_notification_email' => self::ADMIN_EMAIL,
                'admin_notification_whatsapp' => '081222333444, 6281555666777',
                'fulfillment_sla_minutes' => 120,
                'renewal_grace_days_vps' => 3,
                'renewal_grace_days_database' => 7,
                'renewal_grace_days_app' => 7,
            ])
            ->assertSessionHas('success');

        $this->assertSame(['6281222333444', '6281555666777'],
            app(\App\Services\AdminAlertService::class)->adminWhatsAppNumbers());

        $this->actingAs($this->admin())
            ->post(route('admin.settings.notification.test'))
            ->assertSessionHas('success');

        $this->actingAs($this->admin())
            ->post(route('admin.settings.notification'), [
                'admin_notification_email' => self::ADMIN_EMAIL,
                'admin_notification_whatsapp' => 'nomor<script>',
                'fulfillment_sla_minutes' => 120,
                'renewal_grace_days_vps' => 3,
                'renewal_grace_days_database' => 7,
                'renewal_grace_days_app' => 7,
            ])
            ->assertSessionHasErrors('admin_notification_whatsapp');
    }

    // ===================== 3. Username SSH & ikon mata =====================

    public function test_existing_vps_defaults_to_root(): void
    {
        $vps = $this->runningVps($this->customer());

        $this->assertSame('root', $vps->fresh()->ssh_user);
        $this->assertSame('ssh root@139.180.200.20 -p 22', $vps->fresh()->ssh_command);
    }

    public function test_admin_provision_with_custom_ssh_username_flows_to_customer(): void
    {
        $customer = $this->customer();
        $order = $this->pendingXenditOrder($customer);
        XenditService::checkAndSyncStatus($order);

        $this->actingAs($this->admin())
            ->post(route('admin.orders.provision', $order->id), [
                'public_ip' => '103.150.10.10',
                'ssh_port' => 2222,
                'ssh_username' => 'ubuntu',
                'root_password' => 'RetailPass#2026',
            ])
            ->assertSessionHasNoErrors();

        $vps = VpsInstance::where('order_id', $order->id)->firstOrFail();
        $this->assertSame('ubuntu', $vps->ssh_username);
        $this->assertSame('ssh ubuntu@103.150.10.10 -p 2222', $vps->ssh_command);

        Notification::assertSentTo($customer, VpsProvisionedNotification::class, function ($n) use ($customer) {
            $wa = $n->toWhatsApp($customer)?->getContent() ?? '';
            $mail = $n->toMail($customer)->render();

            return str_contains($wa, '`ubuntu`')
                && str_contains($wa, 'ssh ubuntu@103.150.10.10 -p 2222')
                && !str_contains($wa, 'root@')
                && str_contains((string) $mail, 'ssh ubuntu@103.150.10.10 -p 2222');
        });

        $this->actingAs($customer)->get("/dashboard/vps/{$vps->id}")
            ->assertOk()
            ->assertSee('ssh ubuntu@103.150.10.10 -p 2222')
            ->assertSee('Port 2222 (SSH)')
            ->assertDontSee('Verifikasi Keamanan Akun');
    }

    public function test_admin_provision_without_username_uses_root_and_rejects_invalid(): void
    {
        $customer = $this->customer();
        $order = $this->pendingXenditOrder($customer);
        XenditService::checkAndSyncStatus($order);

        $this->actingAs($this->admin())
            ->post(route('admin.orders.provision', $order->id), [
                'public_ip' => '103.150.10.11',
                'ssh_username' => 'root;rm -rf /',
                'root_password' => 'RetailPass#2026',
            ])
            ->assertSessionHasErrors('ssh_username');

        $this->actingAs($this->admin())
            ->post(route('admin.orders.provision', $order->id), [
                'public_ip' => '103.150.10.11',
                'root_password' => 'RetailPass#2026',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('root', VpsInstance::where('order_id', $order->id)->value('ssh_username'));
    }

    public function test_admin_can_change_ssh_username_and_customer_is_notified(): void
    {
        $customer = $this->customer();
        $vps = $this->runningVps($customer);

        $this->actingAs($this->admin())
            ->post(route('admin.instances.ssh-username', $vps->id), ['ssh_username' => 'vexa'])
            ->assertSessionHas('success');

        $this->assertSame('vexa', $vps->fresh()->ssh_username);
        Notification::assertSentTo($customer, VpsSshUsernameChangedNotification::class,
            fn ($n) => $n->oldUsername === 'root' && str_contains($n->toWhatsApp($customer)->getContent(), 'ssh vexa@'));
        $this->assertDatabaseHas('vps_activity_logs', ['vps_instance_id' => $vps->id, 'action' => 'ssh_username_updated']);

        // Tidak berubah → tidak ada notifikasi tambahan.
        $this->actingAs($this->admin())
            ->post(route('admin.instances.ssh-username', $vps->id), ['ssh_username' => 'vexa']);
        Notification::assertSentToTimes($customer, VpsSshUsernameChangedNotification::class, 1);

        $this->actingAs($this->admin())
            ->post(route('admin.instances.ssh-username', $vps->id), ['ssh_username' => ''])
            ->assertSessionHasErrors('ssh_username');

        $this->actingAs($customer)->post(route('admin.instances.ssh-username', $vps->id), ['ssh_username' => 'x'])
            ->assertStatus(403);
    }

    public function test_handover_template_uses_ssh_username(): void
    {
        $customer = $this->customer();
        $vps = $this->runningVps($customer, ['ssh_username' => 'debian']);
        $order = Order::create(['customer_id' => $customer->id, 'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $this->spec()->id, 'control_panel' => 'none', 'datacenter_location' => 'indonesia', 'os' => 'ubuntu2404',
            'status' => 'active', 'channel' => 'website', 'payment_method' => 'qris', 'amount' => 100000, 'paid_at' => now()]);
        $vps->update(['order_id' => $order->id]);

        $template = \App\Models\NotificationTemplate::where('code', \App\Models\NotificationTemplate::FULFILLMENT_HANDOVER)->firstOrFail();
        $text = app(\App\Services\FulfillmentService::class)->handoverText($order->fresh(), $template);

        $this->assertStringContainsString('Pengguna: debian', $text);
        $this->assertStringNotContainsString('{{pengguna}}', $text);
    }

    // ===================== 4. Masa aktif dihitung sejak lunas =====================

    public function test_late_activation_keeps_active_period_from_payment_time(): void
    {
        $customer = $this->customer();
        $order = $this->pendingXenditOrder($customer);
        XenditService::checkAndSyncStatus($order);
        $paidAt = $order->fresh()->paid_at->copy();

        // Admin baru setup ±22 jam kemudian.
        $this->travel(22)->hours();

        $this->actingAs($this->admin())
            ->post(route('admin.orders.provision', $order->id), [
                'public_ip' => '103.150.10.12',
                'root_password' => 'RetailPass#2026',
            ])
            ->assertSessionHasNoErrors();

        $vps = VpsInstance::where('order_id', $order->id)->firstOrFail();
        $this->assertTrue($vps->starts_at->equalTo($paidAt));
        $this->assertTrue($vps->expires_at->equalTo($paidAt->copy()->addMonth()));
        $this->assertTrue($order->fresh()->expires_at->equalTo($paidAt->copy()->addMonth()));
    }

    public function test_align_command_fixes_vps_activated_late_before_fix(): void
    {
        $customer = $this->customer();
        $paidAt = now()->subDays(2)->startOfMinute();
        $activatedAt = $paidAt->copy()->addHours(14);

        $order = Order::create(['customer_id' => $customer->id, 'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $this->spec()->id, 'control_panel' => 'none', 'datacenter_location' => 'indonesia', 'os' => 'ubuntu2404',
            'status' => 'active', 'channel' => 'website', 'payment_method' => 'qris', 'amount' => 100000,
            'paid_at' => $paidAt, 'starts_at' => $activatedAt, 'expires_at' => $activatedAt->copy()->addMonth(),
            'grace_period_ends_at' => $activatedAt->copy()->addMonth()->addDays(3)]);
        $vps = $this->runningVps($customer, ['order_id' => $order->id, 'starts_at' => $activatedAt,
            'expires_at' => $activatedAt->copy()->addMonth(), 'grace_period_ends_at' => $activatedAt->copy()->addMonth()->addDays(3)]);

        // VPS yang sudah benar tidak ikut.
        $okOrder = Order::create(['customer_id' => $customer->id, 'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $this->spec()->id, 'control_panel' => 'none', 'datacenter_location' => 'indonesia', 'os' => 'ubuntu2404',
            'status' => 'active', 'channel' => 'website', 'payment_method' => 'qris', 'amount' => 100000, 'paid_at' => $paidAt]);
        $okVps = $this->runningVps($customer, ['order_id' => $okOrder->id, 'hostname' => 'srv-ok',
            'starts_at' => $paidAt->copy()->addMinutes(10), 'expires_at' => $paidAt->copy()->addMinutes(10)->addMonth()]);

        $this->artisan('vps:align-active-period')->expectsOutputToContain('Ditemukan 1')->assertSuccessful();
        $this->assertTrue($vps->fresh()->starts_at->equalTo($activatedAt)); // dry-run

        $this->artisan('vps:align-active-period --apply')->assertSuccessful();

        $fresh = $vps->fresh();
        $this->assertTrue($fresh->starts_at->equalTo($paidAt));
        $this->assertTrue($fresh->expires_at->equalTo($paidAt->copy()->addMonth()));
        $this->assertTrue($fresh->grace_period_ends_at->equalTo($paidAt->copy()->addMonth()->addDays(3)));
        $this->assertTrue($order->fresh()->expires_at->equalTo($paidAt->copy()->addMonth()));
        $this->assertTrue($okVps->fresh()->starts_at->equalTo($paidAt->copy()->addMinutes(10)));

        // Diulang: tidak bergeser lagi.
        $this->artisan('vps:align-active-period --apply')->expectsOutputToContain('Aman')->assertSuccessful();
        $this->assertTrue($vps->fresh()->expires_at->equalTo($paidAt->copy()->addMonth()));
    }

    public function test_late_applied_renewal_of_expired_vps_counts_from_payment_time(): void
    {
        $customer = $this->customer();
        $vps = $this->runningVps($customer, ['expires_at' => now()->subDays(2)]);
        $paidAt = now()->subDay()->startOfMinute();

        $order = $this->pendingXenditOrder($customer, ['order_type' => 'renewal', 'vps_instance_id' => $vps->id]);
        Order::whereKey($order->id)->update(['status' => 'paid', 'paid_at' => $paidAt, 'paid_notified_at' => now()]);

        $this->artisan('renewal:repair --apply')->assertSuccessful();

        $this->assertTrue($vps->fresh()->expires_at->equalTo($paidAt->copy()->addMonth()));
    }

    public function test_activation_after_sla_counts_from_handover_as_compensation(): void
    {
        $customer = $this->customer();
        $order = $this->pendingXenditOrder($customer);
        XenditService::checkAndSyncStatus($order);

        $this->travel(30)->hours(); // lewat SLA 24 jam
        $handover = now()->copy();

        $this->actingAs($this->admin())
            ->post(route('admin.orders.provision', $order->id), [
                'public_ip' => '103.150.10.13',
                'root_password' => 'RetailPass#2026',
            ])
            ->assertSessionHasNoErrors();

        $vps = VpsInstance::where('order_id', $order->id)->firstOrFail();
        $this->assertEqualsWithDelta($handover->timestamp, $vps->starts_at->timestamp, 5);
        $this->assertEqualsWithDelta($handover->copy()->addMonth()->timestamp, $vps->expires_at->timestamp, 5);
        $this->assertGreaterThan($order->fresh()->paid_at->copy()->addMonth()->addHours(29)->timestamp, $vps->expires_at->timestamp);
        $this->assertDatabaseHas('vps_activity_logs', ['vps_instance_id' => $vps->id, 'action' => 'sla_compensation']);
    }

    // ===================== 5. Perpanjangan & Supplier =====================

    public function test_renewal_paid_with_supplier_already_covering_needs_no_action(): void
    {
        $customer = $this->customer();
        $vps = $this->runningVps($customer, ['expires_at' => now()->addDays(5)]);
        \App\Models\SupplierPurchase::create([
            'vps_instance_id' => $vps->id, 'supplier' => 'sumopod', 'supplier_order_no' => 'SP-' . uniqid(), 'actual_cost' => 50000,
            'purchased_at' => now(), 'supplier_expires_at' => now()->addMonths(2),
        ]);
        $order = $this->pendingXenditOrder($customer, ['order_type' => 'renewal', 'vps_instance_id' => $vps->id]);

        XenditService::checkAndSyncStatus($order);

        $messages = $this->adminWhatsAppMessages();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('Tidak perlu tindakan', $messages[0]);
    }

    public function test_supplier_reminder_lists_urgent_gap_and_decision(): void
    {
        $c1 = $this->customer('urgent');
        $urgentVps = $this->runningVps($c1, ['hostname' => 'srv-urgent', 'expires_at' => now()->addMonth()]);
        \App\Models\SupplierPurchase::create(['vps_instance_id' => $urgentVps->id, 'supplier' => 'sumopod', 'supplier_order_no' => 'SP-' . uniqid(),
            'actual_cost' => 1, 'purchased_at' => now(), 'supplier_expires_at' => now()->addDays(2)]);

        $c2 = $this->customer('gapcust');
        $gapVps = $this->runningVps($c2, ['hostname' => 'srv-gap', 'expires_at' => now()->addMonths(2)]);
        \App\Models\SupplierPurchase::create(['vps_instance_id' => $gapVps->id, 'supplier' => 'sumopod', 'supplier_order_no' => 'SP-' . uniqid(),
            'actual_cost' => 1, 'purchased_at' => now(), 'supplier_expires_at' => now()->addDays(20)]);

        $c3 = $this->customer('unpaid');
        $decisionVps = $this->runningVps($c3, ['hostname' => 'srv-unpaid', 'expires_at' => now()->addDay()]);
        \App\Models\SupplierPurchase::create(['vps_instance_id' => $decisionVps->id, 'supplier' => 'sumopod', 'supplier_order_no' => 'SP-' . uniqid(),
            'actual_cost' => 1, 'purchased_at' => now(), 'supplier_expires_at' => now()->addDays(1)->addHours(3)]);

        // Siang: hanya yang mendesak.
        $this->artisan('supplier:remind-admin')->assertSuccessful();
        $noon = $this->adminWhatsAppMessages();
        $this->assertCount(1, $noon);
        $this->assertStringContainsString('srv-urgent', $noon[0]);
        $this->assertStringNotContainsString('srv-gap', $noon[0]);

        // Pagi: ringkasan lengkap.
        $this->artisan('supplier:remind-admin --daily')->assertSuccessful();
        $morning = $this->adminWhatsAppMessages()[1];
        $this->assertStringContainsString('PERPANJANG SEGERA', $morning);
        $this->assertStringContainsString('srv-urgent', $morning);
        $this->assertStringContainsString('BELUM TERTUTUP', $morning);
        $this->assertStringContainsString('srv-gap', $morning);
        $this->assertStringContainsString('PERLU KEPUTUSAN', $morning);
        $this->assertStringContainsString('srv-unpaid', $morning);
    }

    public function test_supplier_reminder_sends_nothing_when_all_covered(): void
    {
        $customer = $this->customer();
        $vps = $this->runningVps($customer, ['expires_at' => now()->addDays(10)]);
        \App\Models\SupplierPurchase::create(['vps_instance_id' => $vps->id, 'supplier' => 'sumopod', 'supplier_order_no' => 'SP-' . uniqid(),
            'actual_cost' => 1, 'purchased_at' => now(), 'supplier_expires_at' => now()->addDays(12)]);

        $this->artisan('supplier:remind-admin --daily')->expectsOutputToContain('Tidak ada tugas')->assertSuccessful();
        $this->assertCount(0, $this->adminWhatsAppMessages());
    }

    public function test_customer_gets_h7_reminder_once(): void
    {
        $customer = $this->customer();
        $customer->update(['phone' => '081299998888']);
        $vps = $this->runningVps($customer, ['expires_at' => now()->addDays(7)->setTime(12, 0)]);

        $this->artisan('renewal:remind')->assertSuccessful();
        $this->artisan('renewal:remind')->assertSuccessful();

        Notification::assertSentToTimes($customer, \App\Notifications\InstanceRenewalReminderNotification::class, 1);
        Notification::assertSentTo($customer, \App\Notifications\InstanceRenewalReminderNotification::class,
            fn ($n) => $n->stage === \App\Models\RenewalReminder::STAGE_H7
                && str_contains($n->toWhatsApp($customer)->getContent(), '7 Hari Lagi'));

        $this->assertTrue(collect($this->adminWhatsAppMessages())->contains(fn ($m) => str_contains($m, 'RINGKASAN PERPANJANGAN')));
    }

    public function test_terms_page_shows_configured_grace_and_setup_sla(): void
    {
        app(SettingsService::class)->set('renewal_grace_days_vps', 5, 'integer');

        $this->get(route('terms'))
            ->assertOk()
            ->assertSee('selama 5 hari kalender', false)
            ->assertSee('1&times;24 jam', false)
            ->assertDontSee('3 (tiga) hari kalender', false);
    }

    public function test_eye_icon_reveals_password_without_account_password(): void
    {
        $customer = $this->customer();
        $vps = $this->runningVps($customer);

        $this->actingAs($customer)
            ->postJson(route('dashboard.vps.reveal-password', $vps->id))
            ->assertOk()
            ->assertJson(['success' => true, 'password' => 'PasswordAwal#123']);

        $this->assertNotNull($vps->fresh()->root_password_revealed_at);
        $this->assertDatabaseHas('vps_activity_logs', ['vps_instance_id' => $vps->id, 'action' => 'password_reveal']);

        // Tetap terenkripsi di database.
        $raw = \Illuminate\Support\Facades\DB::table('vps_instances')->where('id', $vps->id)->value('initial_root_password');
        $this->assertNotSame('PasswordAwal#123', $raw);
    }

    public function test_eye_icon_cannot_reveal_other_customers_password(): void
    {
        $owner = $this->customer('pemilik');
        $vps = $this->runningVps($owner);
        $other = $this->customer('oranglain');

        $this->actingAs($other)
            ->postJson(route('dashboard.vps.reveal-password', $vps->id))
            ->assertNotFound();

        app('auth')->forgetGuards();
        $this->postJson(route('dashboard.vps.reveal-password', $vps->id))->assertUnauthorized();
    }

    public function test_eye_icon_reports_missing_password(): void
    {
        $customer = $this->customer();
        $vps = $this->runningVps($customer, ['initial_root_password' => null]);

        $this->actingAs($customer)
            ->postJson(route('dashboard.vps.reveal-password', $vps->id))
            ->assertNotFound()
            ->assertJson(['success' => false]);
    }
}
