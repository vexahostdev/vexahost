<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\SupplierPurchase;
use App\Models\User;
use App\Models\VpsInstance;
use App\Services\SupplierCoverageService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PHASE 8 - Catatan Pembelian Supplier.
 */
class SupplierPurchaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    protected function admin(): User
    {
        return User::where('is_admin', true)->firstOrFail();
    }

    protected function makeInstance(array $attributes = []): VpsInstance
    {
        $customer = User::create([
            'username' => 'sp' . uniqid(),
            'email' => 'sp' . uniqid() . '@test.id',
            // Nama sengaja tanpa kata "supplier" agar tes kebocoran tidak tertipu
            // oleh nama pelanggan yang memang tampil di dasbornya sendiri.
            'full_name' => 'Pelanggan Uji Pembelian',
            'password' => bcrypt('password123'),
        ]);

        return VpsInstance::create(array_merge([
            'customer_id' => $customer->id,
            'hostname' => 'sp-' . uniqid(),
            'status' => 'running',
            'cpu' => 2,
            'ram' => 4,
            'disk' => 60,
            'control_panel' => 'coolify',
            'starts_at' => now()->subDays(10),
            'expires_at' => now()->addDays(20),
        ], $attributes));
    }

    protected function purchase(VpsInstance $instance, $expiresAt, float $cost = 60000): SupplierPurchase
    {
        return SupplierPurchase::create([
            'vps_instance_id' => $instance->id,
            'supplier_order_no' => 'SMP-' . uniqid(),
            'actual_cost' => $cost,
            'purchased_at' => now()->subDays(10),
            'supplier_expires_at' => $expiresAt,
        ]);
    }

    // ======================= Kecocokan masa aktif =======================

    public function test_covered_when_supplier_outlasts_customer(): void
    {
        $instance = $this->makeInstance(['expires_at' => now()->addDays(20)]);
        $this->purchase($instance, now()->addDays(21));

        $row = app(SupplierCoverageService::class)->rowFor($instance->fresh('supplierPurchases'));
        $this->assertSame(SupplierCoverageService::STATUS_COVERED, $row['status']);
        $this->assertSame(1, $row['drift_days']);
    }

    /**
     * Risiko utama: pelanggan sudah membayar lebih lama daripada masa aktif di Supplier.
     */
    public function test_urgent_when_supplier_expires_soon_before_customer(): void
    {
        $instance = $this->makeInstance(['expires_at' => now()->addDays(25)]);
        $this->purchase($instance, now()->addDays(2));

        $row = app(SupplierCoverageService::class)->rowFor($instance->fresh('supplierPurchases'));
        $this->assertSame(SupplierCoverageService::STATUS_URGENT, $row['status']);
        $this->assertLessThan(0, $row['drift_days']);
    }

    public function test_gap_when_supplier_expires_before_customer_but_not_yet_urgent(): void
    {
        $instance = $this->makeInstance(['expires_at' => now()->addDays(30)]);
        $this->purchase($instance, now()->addDays(15));

        $row = app(SupplierCoverageService::class)->rowFor($instance->fresh('supplierPurchases'));
        $this->assertSame(SupplierCoverageService::STATUS_GAP, $row['status']);
    }

    public function test_missing_when_instance_has_no_purchase(): void
    {
        $instance = $this->makeInstance();

        $row = app(SupplierCoverageService::class)->rowFor($instance->fresh('supplierPurchases'));
        $this->assertSame(SupplierCoverageService::STATUS_MISSING, $row['status']);
    }

    public function test_latest_purchase_is_used_for_coverage(): void
    {
        $instance = $this->makeInstance(['expires_at' => now()->addDays(25)]);
        $this->purchase($instance, now()->addDays(2));
        $this->purchase($instance, now()->addDays(32));

        $row = app(SupplierCoverageService::class)->rowFor($instance->fresh('supplierPurchases'));
        $this->assertSame(SupplierCoverageService::STATUS_COVERED, $row['status']);
    }

    public function test_urgent_rows_are_listed_first_and_terminated_are_excluded(): void
    {
        $covered = $this->makeInstance();
        $this->purchase($covered, now()->addDays(40));

        $urgent = $this->makeInstance(['expires_at' => now()->addDays(25)]);
        $this->purchase($urgent, now()->addDay());

        $this->makeInstance(['status' => 'terminated']);

        $rows = app(SupplierCoverageService::class)->rows();
        $this->assertSame($urgent->id, $rows->first()['instance']->id);
        $this->assertCount(2, $rows);
    }

    // ======================= Halaman & aksi =======================

    public function test_admin_can_view_page_and_filter(): void
    {
        $instance = $this->makeInstance(['expires_at' => now()->addDays(25)]);
        $this->purchase($instance, now()->addDay());

        $this->actingAs($this->admin())->get('/admin/supplier-purchases')
            ->assertStatus(200)
            ->assertSee('Perpanjang Segera')
            ->assertSee($instance->hostname);

        // Hostname juga muncul di dropdown modal dan riwayat pembelian, jadi yang
        // diperiksa adalah isi tabel kecocokan: kosong untuk saringan "tertutup".
        $this->actingAs($this->admin())->get('/admin/supplier-purchases?status=covered')
            ->assertStatus(200)
            ->assertSee('Tidak ada instance dengan status ini')
            ->assertDontSee('Catat Perpanjangan');
    }

    public function test_recording_renewal_closes_the_gap(): void
    {
        $instance = $this->makeInstance(['expires_at' => now()->addDays(25)]);
        $this->purchase($instance, now()->addDay());

        $this->actingAs($this->admin())->post('/admin/supplier-purchases', [
            'vps_instance_id' => $instance->id,
            'supplier_order_no' => 'SMP-RENEW-01',
            'actual_cost' => 61000,
            'purchased_at' => now()->format('Y-m-d'),
        ])->assertSessionHas('success');

        $purchase = SupplierPurchase::where('supplier_order_no', 'SMP-RENEW-01')->first();
        // Bawaan: prepaid satu bulan.
        $this->assertTrue($purchase->supplier_expires_at->isSameDay(now()->addMonth()));

        $row = app(SupplierCoverageService::class)->rowFor($instance->fresh('supplierPurchases'));
        $this->assertSame(SupplierCoverageService::STATUS_COVERED, $row['status']);
    }

    public function test_purchase_validation(): void
    {
        $instance = $this->makeInstance();

        $this->actingAs($this->admin())->post('/admin/supplier-purchases', ['vps_instance_id' => $instance->id])
            ->assertSessionHasErrors(['supplier_order_no', 'actual_cost', 'purchased_at']);

        $this->actingAs($this->admin())->post('/admin/supplier-purchases', [
            'vps_instance_id' => $instance->id,
            'supplier_order_no' => 'SMP-X',
            'actual_cost' => 1000,
            'purchased_at' => now()->format('Y-m-d'),
            'supplier_expires_at' => now()->subDay()->format('Y-m-d'),
        ])->assertSessionHasErrors('supplier_expires_at');
    }

    public function test_update_and_delete_are_audited(): void
    {
        $instance = $this->makeInstance();
        $purchase = $this->purchase($instance, now()->addDays(20), 60000);

        $this->actingAs($this->admin())->put("/admin/supplier-purchases/{$purchase->id}", [
            'supplier_order_no' => $purchase->supplier_order_no,
            'actual_cost' => 58000,
            'purchased_at' => now()->subDays(10)->format('Y-m-d'),
            'supplier_expires_at' => now()->addDays(20)->format('Y-m-d'),
        ])->assertSessionHas('success');

        $this->assertEquals(58000, (float) $purchase->fresh()->actual_cost);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'supplier.purchase_updated']);

        $this->actingAs($this->admin())->delete("/admin/supplier-purchases/{$purchase->id}")->assertSessionHas('success');

        $this->assertNull(SupplierPurchase::find($purchase->id));
        $log = AdminAuditLog::where('action', 'supplier.purchase_deleted')->first();
        $this->assertNotNull($log);
        // Data yang dihapus tetap tersimpan di jejak audit.
        $this->assertSame($purchase->supplier_order_no, $log->changes['dihapus']['supplier_order_no']);
    }

    public function test_sidebar_shows_urgent_count(): void
    {
        $instance = $this->makeInstance(['expires_at' => now()->addDays(25)]);
        $this->purchase($instance, now()->addDay());

        $this->assertSame(1, app(SupplierCoverageService::class)->urgentCount());
        $this->actingAs($this->admin())->get('/admin')->assertSee('Pembelian Supplier');
    }

    // ======================= Kerahasiaan supplier =======================

    /**
     * Checkout mengirim data paket ke browser lewat Js::from(); harga modal
     * sempat ikut terbaca dari view-source oleh pengunjung mana pun.
     */
    public function test_checkout_does_not_expose_cost_price(): void
    {
        $html = $this->get('/checkout')->assertStatus(200)->getContent();

        $this->assertStringNotContainsString('cost_price', $html);
        $this->assertStringContainsString('sell_price', $html);
    }

    public function test_admin_package_editor_still_receives_cost_price(): void
    {
        $this->actingAs($this->admin())->get('/admin/packages')
            ->assertStatus(200)
            ->assertSee('cost_price', false);
    }

    public function test_supplier_name_never_appears_on_customer_pages(): void
    {
        $instance = $this->makeInstance();
        $this->purchase($instance, now()->addDays(30));
        $customer = $instance->customer;
        $org = $customer->createPersonalOrganization();
        $customer->switchToOrganization($org);

        foreach (['/', '/checkout', '/terms', '/status', '/refund'] as $path) {
            $this->get($path)->assertDontSee('Supplier')->assertDontSee('supplier');
        }

        $this->actingAs($customer->fresh())->withSession(['2fa.passed' => true]);
        foreach (['/dashboard', '/dashboard/billing'] as $path) {
            $this->get($path)->assertStatus(200)->assertDontSee('Supplier')->assertDontSee('supplier');
        }
    }

    public function test_customer_emails_never_mention_supplier(): void
    {
        $instance = $this->makeInstance();
        $notifications = [
            new \App\Notifications\InstanceRenewalReminderNotification($instance, \App\Models\RenewalReminder::STAGE_H3, 3, 5),
            new \App\Notifications\InstanceRenewalReminderNotification($instance, \App\Models\RenewalReminder::STAGE_GRACE_FINAL, 0, 5),
            new \App\Notifications\InstanceRenewalReminderNotification($instance, \App\Models\RenewalReminder::STAGE_GRACE_ENDED, -1, 5),
        ];

        foreach ($notifications as $notification) {
            $html = (string) $notification->toMail($instance->customer)->render();
            $this->assertStringNotContainsStringIgnoringCase('supplier', $html);
        }

        foreach (\App\Models\NotificationTemplate::all() as $template) {
            $this->assertStringNotContainsStringIgnoringCase('supplier', $template->subject . ' ' . $template->body);
        }
    }

    /**
     * Kode sudah menyiapkan driver provider 'supplier'. Begitu dipakai, label
     * provider di dasbor dan faktur tidak boleh menampilkan nama mentahnya.
     */
    public function test_unknown_provider_value_never_reaches_customer_label(): void
    {
        $instance = $this->makeInstance(['provider' => 'supplier']);

        $this->assertSame(\App\Models\Order::NEUTRAL_PROVIDER_LABEL, $instance->provider_label);
        $this->assertSame('Tencent Cloud', \App\Models\Order::customerProviderLabel('tencent'));
        $this->assertSame(\App\Models\Order::NEUTRAL_PROVIDER_LABEL, \App\Models\Order::customerProviderLabel(null));
        $this->assertSame(\App\Models\Order::NEUTRAL_PROVIDER_LABEL, \App\Models\Order::customerProviderLabel('manual'));
    }

    public function test_invoice_pdf_hides_raw_provider_value(): void
    {
        $customer = User::create([
            'username' => 'inv' . uniqid(),
            'email' => 'inv' . uniqid() . '@test.id',
            'full_name' => 'Pelanggan Faktur',
            'password' => bcrypt('password123'),
        ]);
        $org = $customer->createPersonalOrganization();
        $customer->switchToOrganization($org);

        $order = \App\Models\Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'vps_spec_id' => \App\Models\VpsSpec::first()->id,
            'control_panel' => 'none',
            'provider' => 'supplier',
            'hostname' => 'inv-' . uniqid(),
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'status' => 'active',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 80000,
            'paid_at' => now(),
        ]);
        $invoice = \App\Models\Invoice::create([
            'order_id' => $order->id,
            'organization_id' => $org->id,
            'invoice_number' => 'INV-RAHASIA-01',
            'amount' => 80000,
            'status' => 'paid',
            'issued_at' => now(),
            'paid_at' => now(),
        ]);

        $html = $this->actingAs($customer->fresh())
            ->withSession(['2fa.passed' => true])
            ->get("/dashboard/invoices/{$invoice->id}/print?format=html")
            ->assertStatus(200)
            ->getContent();

        $this->assertStringNotContainsStringIgnoringCase('supplier', $html);
        $this->assertStringContainsString(\App\Models\Order::NEUTRAL_PROVIDER_LABEL, $html);
    }

    public function test_customer_cannot_access(): void
    {
        $instance = $this->makeInstance();

        $this->actingAs($instance->customer)->get('/admin/supplier-purchases')->assertStatus(403);
    }
}
