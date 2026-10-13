<?php

namespace Tests\Feature;

use App\Models\FulfillmentChecklist;
use App\Models\Order;
use App\Models\SupplierPurchase;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Notifications\AdminFulfillmentOverdueNotification;
use App\Services\FulfillmentService;
use App\Services\SettingsService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * PHASE 5 - Fulfillment Workboard.
 */
class FulfillmentWorkboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Notification::fake();
    }

    protected function admin(): User
    {
        return User::where('is_admin', true)->firstOrFail();
    }

    protected function makeOrder(array $attributes = []): Order
    {
        $customer = User::create([
            'username' => 'ff' . uniqid(),
            'email' => 'ff' . uniqid() . '@test.id',
            'full_name' => 'Pelanggan Fulfillment',
            'password' => bcrypt('password123'),
            'channel' => 'website',
        ]);
        $org = $customer->createPersonalOrganization();
        $customer->switchToOrganization($org);

        $spec = VpsSpec::where('category', 'vps')->first() ?? VpsSpec::first();

        return Order::create(array_merge([
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'provider' => 'tencent',
            'hostname' => 'ff-' . uniqid(),
            'root_password' => 'KataSandiPelanggan#1',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'paid',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 80000,
            'paid_at' => now(),
        ], $attributes));
    }

    // ======================= Kolom papan =======================

    public function test_orders_map_to_the_right_columns(): void
    {
        $service = app(FulfillmentService::class);

        $this->assertSame('awaiting_payment', $service->columnFor($this->makeOrder(['status' => 'pending', 'paid_at' => null])));
        $this->assertSame('ready', $service->columnFor($this->makeOrder()));
        $this->assertSame('purchased', $service->columnFor($this->makeOrder(['fulfillment_stage' => 'purchased'])));
        $this->assertSame('setup', $service->columnFor($this->makeOrder(['fulfillment_stage' => 'setup'])));
        $this->assertSame('delivered', $service->columnFor($this->makeOrder(['status' => 'active'])));
        $this->assertNull($service->columnFor($this->makeOrder(['status' => 'cancelled'])));
    }

    public function test_admin_can_view_board(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->admin())->get('/admin/fulfillment')
            ->assertStatus(200)
            ->assertSee('Siap Diproses')
            ->assertSee('#ORD-' . str_pad($order->id, 3, '0', STR_PAD_LEFT));
    }

    public function test_customer_cannot_view_board(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($order->customer)->get('/admin/fulfillment')->assertStatus(403);
    }

    // ======================= Pembelian Supplier =======================

    public function test_recording_purchase_moves_order_and_stores_cost(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->admin())
            ->post("/admin/fulfillment/{$order->id}/purchase", [
                'supplier_order_no' => 'SMP-2026-0001',
                'actual_cost' => 61500,
            ])
            ->assertSessionHas('success');

        $this->assertSame('purchased', $order->fresh()->fulfillment_stage);
        // Status order TIDAK diubah oleh papan kerja.
        $this->assertSame('paid', $order->fresh()->status);

        $purchase = SupplierPurchase::where('order_id', $order->id)->first();
        $this->assertSame('SMP-2026-0001', $purchase->supplier_order_no);
        $this->assertEquals(61500, (float) $purchase->actual_cost);
        // Bawaan: prepaid satu bulan.
        $this->assertTrue($purchase->supplier_expires_at->isSameDay($purchase->purchased_at->copy()->addMonth()));
    }

    public function test_purchase_requires_order_number_and_cost(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->admin())
            ->post("/admin/fulfillment/{$order->id}/purchase", [])
            ->assertSessionHasErrors(['supplier_order_no', 'actual_cost']);
    }

    public function test_purchase_rejected_for_unpaid_order(): void
    {
        $order = $this->makeOrder(['status' => 'pending', 'paid_at' => null]);

        $this->actingAs($this->admin())
            ->post("/admin/fulfillment/{$order->id}/purchase", [
                'supplier_order_no' => 'SMP-X',
                'actual_cost' => 1000,
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, SupplierPurchase::count());
    }

    public function test_supplier_expiry_must_be_after_purchase(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->admin())
            ->post("/admin/fulfillment/{$order->id}/purchase", [
                'supplier_order_no' => 'SMP-Y',
                'actual_cost' => 1000,
                'purchased_at' => now()->format('Y-m-d H:i'),
                'supplier_expires_at' => now()->subDay()->format('Y-m-d H:i'),
            ])
            ->assertSessionHasErrors('supplier_expires_at');
    }

    // ======================= Tahap & daftar periksa =======================

    public function test_stage_can_move_purchased_to_setup_and_back(): void
    {
        $order = $this->makeOrder(['fulfillment_stage' => 'purchased']);

        $this->actingAs($this->admin())->post("/admin/fulfillment/{$order->id}/stage", ['stage' => 'setup']);
        $this->assertSame('setup', $order->fresh()->fulfillment_stage);

        $this->actingAs($this->admin())->post("/admin/fulfillment/{$order->id}/stage", ['stage' => 'purchased']);
        $this->assertSame('purchased', $order->fresh()->fulfillment_stage);
    }

    public function test_cannot_skip_purchase_step(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->admin())
            ->post("/admin/fulfillment/{$order->id}/stage", ['stage' => 'setup'])
            ->assertSessionHas('error');

        $this->assertNull($order->fresh()->fulfillment_stage);
    }

    public function test_checklist_steps_depend_on_package_type(): void
    {
        $service = app(FulfillmentService::class);

        $vps = $this->makeOrder(['control_panel' => 'coolify']);
        $this->assertArrayHasKey('vps_panel', $service->stepsFor($vps));

        $plain = $this->makeOrder(['control_panel' => 'none']);
        $this->assertArrayNotHasKey('vps_panel', $service->stepsFor($plain));
    }

    public function test_checklist_step_toggles_and_persists(): void
    {
        $order = $this->makeOrder(['fulfillment_stage' => 'setup']);

        $this->actingAs($this->admin())->post("/admin/fulfillment/{$order->id}/steps/vps_update");
        $this->assertTrue((bool) FulfillmentChecklist::where('order_id', $order->id)->where('step_key', 'vps_update')->value('is_done'));

        $this->actingAs($this->admin())->post("/admin/fulfillment/{$order->id}/steps/vps_update");
        $this->assertFalse((bool) FulfillmentChecklist::where('order_id', $order->id)->where('step_key', 'vps_update')->value('is_done'));
    }

    public function test_unknown_checklist_step_is_rejected(): void
    {
        $order = $this->makeOrder(['fulfillment_stage' => 'setup']);

        $this->actingAs($this->admin())->post("/admin/fulfillment/{$order->id}/steps/langkah_palsu")->assertStatus(404);
    }

    public function test_progress_is_calculated(): void
    {
        $service = app(FulfillmentService::class);
        $order = $this->makeOrder(['fulfillment_stage' => 'setup']);

        $this->assertSame(0, $service->progressFor($order));

        $total = count($service->stepsFor($order));
        FulfillmentChecklist::create(['order_id' => $order->id, 'step_key' => 'vps_update', 'is_done' => true]);

        $this->assertSame((int) round(100 / $total), $service->progressFor($order->fresh()));
    }

    // ======================= Serah terima =======================

    public function test_provisioning_marks_delivered_and_links_purchase(): void
    {
        $order = $this->makeOrder(['fulfillment_stage' => 'setup']);
        SupplierPurchase::create([
            'order_id' => $order->id,
            'supplier_order_no' => 'SMP-LINK',
            'actual_cost' => 60000,
            'purchased_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$order->id}/provision", [
                'public_ip' => '103.150.10.9',
                'root_password' => 'VexaRootSecretPass123!',
            ])
            ->assertSessionHas('success');

        $order->refresh();
        $instance = VpsInstance::where('order_id', $order->id)->first();

        $this->assertSame('active', $order->status);
        $this->assertSame('delivered', $order->fulfillment_stage);
        $this->assertNotNull($order->delivered_at);
        $this->assertSame($instance->id, SupplierPurchase::where('order_id', $order->id)->value('vps_instance_id'));
    }

    /**
     * Sebelumnya tenggang ditulis 7 hari, yaitu tepat batas hapus VPS di Supplier.
     */
    public function test_provisioning_uses_product_grace_period_not_seven_days(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->admin())->post("/admin/orders/{$order->id}/provision", [
            'public_ip' => '103.150.10.10',
            'root_password' => 'VexaRootSecretPass123!',
        ]);

        $instance = VpsInstance::where('order_id', $order->id)->first();
        $this->assertSame(5, (int) round($instance->expires_at->diffInDays($instance->grace_period_ends_at)));
    }

    public function test_handover_text_contains_access_but_no_password(): void
    {
        $order = $this->makeOrder();
        $this->actingAs($this->admin())->post("/admin/orders/{$order->id}/provision", [
            'public_ip' => '103.150.10.11',
            'root_password' => 'VexaRootSecretPass123!',
        ]);

        $order->refresh()->load('vpsInstance', 'customer');
        $template = \App\Models\NotificationTemplate::where('code', 'fulfillment_handover')->first();
        $text = app(FulfillmentService::class)->handoverText($order, $template);

        $this->assertStringContainsString('103.150.10.11', $text);
        $this->assertStringNotContainsString('KataSandiPelanggan#1', $text);
        $this->assertStringNotContainsString('{{', $text);

        $this->actingAs($this->admin())->get('/admin/fulfillment')
            ->assertSee('Salin Teks Serah Terima');
    }

    // ======================= Waktu tanggap =======================

    public function test_order_is_overdue_after_sla(): void
    {
        app(SettingsService::class)->set('fulfillment_sla_minutes', 60, 'integer');
        $service = app(FulfillmentService::class);

        $this->assertFalse($service->isOverdue($this->makeOrder(['paid_at' => now()->subMinutes(30)])));
        $this->assertTrue($service->isOverdue($this->makeOrder(['paid_at' => now()->subMinutes(90)])));
        $this->assertFalse($service->isOverdue($this->makeOrder(['status' => 'active', 'paid_at' => now()->subDays(2)])));
    }

    public function test_sla_command_sends_one_digest_and_alerts_each_order_once(): void
    {
        app(SettingsService::class)->set('fulfillment_sla_minutes', 60, 'integer');

        $a = $this->makeOrder(['paid_at' => now()->subHours(3)]);
        $b = $this->makeOrder(['paid_at' => now()->subHours(2)]);
        $this->makeOrder(['paid_at' => now()->subMinutes(10)]);

        $this->artisan('fulfillment:check-sla')->assertExitCode(0);
        $this->artisan('fulfillment:check-sla')->assertExitCode(0);

        Notification::assertSentOnDemandTimes(AdminFulfillmentOverdueNotification::class, count(\App\Support\MailIdentity::adminRecipients()));
        $this->assertNotNull($a->fresh()->sla_alerted_at);
        $this->assertNotNull($b->fresh()->sla_alerted_at);
    }

    public function test_sla_command_dry_run_changes_nothing(): void
    {
        $order = $this->makeOrder(['paid_at' => now()->subDays(1)]);

        $this->artisan('fulfillment:check-sla', ['--dry-run' => true])->assertExitCode(0);

        Notification::assertNothingSent();
        $this->assertNull($order->fresh()->sla_alerted_at);
    }
}
