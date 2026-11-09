<?php

namespace Tests\Feature;

use App\Models\MaintenanceWindow;
use App\Models\Setting;
use App\Models\SystemComponent;
use App\Models\User;
use App\Services\MaintenanceService;
use App\Services\SettingsService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PHASE 1 - Pengaturan Sistem, Branding, dan Maintenance.
 */
class SettingsAndMaintenanceTest extends TestCase
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

    protected function customer(): User
    {
        return User::create([
            'full_name' => 'Pelanggan Uji',
            'username' => 'pelangganuji' . uniqid(),
            'email' => 'pelanggan' . uniqid() . '@test.id',
            'password' => bcrypt('password123'),
            'is_admin' => false,
        ]);
    }

    protected function settings(): SettingsService
    {
        return app(SettingsService::class);
    }

    // ======================= Penyimpanan pengaturan =======================

    public function test_settings_service_casts_values_by_type(): void
    {
        $settings = $this->settings();

        $settings->set('uji_boolean', true, 'boolean');
        $settings->set('uji_integer', 42, 'integer');
        $settings->set('uji_json', ['a' => 1], 'json');
        $settings->set('uji_string', 'halo', 'string');

        $this->assertTrue($settings->bool('uji_boolean'));
        $this->assertSame(42, $settings->int('uji_integer'));
        $this->assertSame(['a' => 1], $settings->array('uji_json'));
        $this->assertSame('halo', $settings->get('uji_string'));
    }

    public function test_settings_cache_is_flushed_after_write(): void
    {
        $settings = $this->settings();

        $settings->set('brand_name', 'Nama Lama', 'string');
        $this->assertSame('Nama Lama', $settings->get('brand_name'));

        $settings->set('brand_name', 'Nama Baru', 'string');
        $this->assertSame('Nama Baru', $settings->get('brand_name'));
    }

    public function test_seeder_creates_default_settings_and_components(): void
    {
        $this->assertDatabaseHas('settings', ['key' => 'brand_name']);
        $this->assertDatabaseHas('settings', ['key' => 'renewal_grace_days_vps']);
        $this->assertSame(5, SystemComponent::count());
        $this->assertDatabaseHas('system_components', ['slug' => 'web-panel', 'status' => 'operational']);
    }

    // ======================= Halaman pengaturan admin =======================

    public function test_admin_can_view_settings_page(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/settings');

        $response->assertStatus(200);
        $response->assertSee('Identitas Merek');
        $response->assertSee('Profil Perusahaan');
        $response->assertSee('Masa Tenggang per Jenis Produk');
    }

    public function test_guest_cannot_view_settings_page(): void
    {
        $this->get('/admin/settings')->assertRedirect();
    }

    public function test_customer_cannot_view_settings_page(): void
    {
        $this->actingAs($this->customer())->get('/admin/settings')->assertStatus(403);
    }

    public function test_admin_can_update_company_profile(): void
    {
        $response = $this->actingAs($this->admin())->post('/admin/settings/company', [
            'company_legal_name' => 'PT Vexa Digital Nusantara',
            'company_address' => 'Jalan Merdeka 10',
            'company_city' => 'Jakarta',
            'company_npwp' => '01.234.567.8-901.000',
        ]);

        $response->assertRedirect();
        $this->assertSame('PT Vexa Digital Nusantara', $this->settings()->get('company_legal_name'));
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'settings.company_updated']);
    }

    public function test_admin_can_update_brand_identity(): void
    {
        $response = $this->actingAs($this->admin())->post('/admin/settings/brand', [
            'brand_name' => 'VexaCloud',
            'brand_tagline' => 'Server Cepat Indonesia',
            'brand_accent_color' => '#112233',
        ]);

        $response->assertRedirect();
        $this->assertSame('VexaCloud', $this->settings()->get('brand_name'));
        $this->assertSame('#112233', $this->settings()->get('brand_accent_color'));
    }

    public function test_brand_accent_color_must_be_hex(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/settings/brand', [
                'brand_name' => 'VexaCloud',
                'brand_accent_color' => 'merah',
            ])
            ->assertSessionHasErrors('brand_accent_color');
    }

    /**
     * Tenggang VPS wajib di bawah H+3, batas penghentian di sisi Supplier.
     */
    public function test_vps_grace_period_cannot_reach_supplier_limit(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/settings/notification', [
                'admin_notification_email' => 'admin@vexahostcloud.my.id',
                'fulfillment_sla_minutes' => 120,
                'renewal_grace_days_vps' => 3,
                'renewal_grace_days_database' => 25,
                'renewal_grace_days_app' => 25,
            ])
            ->assertSessionHasErrors('renewal_grace_days_vps');
    }

    public function test_admin_can_regenerate_bypass_token(): void
    {
        $before = $this->settings()->get('maintenance_bypass_token');

        $this->actingAs($this->admin())->post('/admin/settings/bypass-token')->assertRedirect();

        $this->assertNotSame($before, $this->settings()->get('maintenance_bypass_token'));
    }

    public function test_admin_can_change_component_status(): void
    {
        $component = SystemComponent::where('slug', 'vps-network')->firstOrFail();

        $this->actingAs($this->admin())
            ->post("/admin/settings/components/{$component->id}", [
                'status' => 'degraded',
                'status_note' => 'Latensi meningkat di Jakarta.',
            ])
            ->assertRedirect();

        $this->assertSame('degraded', $component->fresh()->status);
    }

    // ======================= Maintenance Tingkat 1 =======================

    public function test_global_maintenance_blocks_guest_with_503_and_retry_after(): void
    {
        $this->settings()->set('maintenance_global_enabled', true, 'boolean');

        $response = $this->get('/');

        $response->assertStatus(503);
        $response->assertHeader('Retry-After');
        $response->assertSee('Sedang Dalam Pemeliharaan');
    }

    public function test_admin_bypasses_global_maintenance(): void
    {
        $this->settings()->set('maintenance_global_enabled', true, 'boolean');

        $this->actingAs($this->admin())->get('/admin')->assertStatus(200);
    }

    public function test_bypass_token_grants_access_during_maintenance(): void
    {
        $this->settings()->set('maintenance_global_enabled', true, 'boolean');
        $token = $this->settings()->get('maintenance_bypass_token');

        $this->get('/?bypass=' . $token)->assertStatus(200);
    }

    public function test_allowed_ip_grants_access_during_maintenance(): void
    {
        $this->settings()->set('maintenance_global_enabled', true, 'boolean');
        $this->settings()->set('maintenance_allowed_ips', '127.0.0.1', 'string');

        $this->get('/', ['REMOTE_ADDR' => '127.0.0.1'])->assertStatus(200);
    }

    /**
     * Ini pengaman paling kritis. Bila webhook Lynk ditolak saat maintenance,
     * pembayaran pelanggan berpotensi hilang tanpa percobaan ulang.
     */
    public function test_payment_webhook_is_never_blocked_by_maintenance(): void
    {
        $this->settings()->set('maintenance_global_enabled', true, 'boolean');

        $response = $this->post('/api/webhooks/lynk', ['test' => true]);

        $this->assertNotSame(503, $response->getStatusCode());
    }

    public function test_health_check_is_never_blocked_by_maintenance(): void
    {
        $this->settings()->set('maintenance_global_enabled', true, 'boolean');

        $this->get('/up')->assertStatus(200);
    }

    // ======================= Maintenance Tingkat 2 =======================

    public function test_scoped_maintenance_blocks_checkout_only(): void
    {
        $this->settings()->set('maintenance_scope_checkout', true, 'boolean');

        $this->get('/checkout')->assertStatus(503);
        $this->get('/')->assertStatus(200);
    }

    public function test_scope_state_reported_by_service(): void
    {
        $this->settings()->set('maintenance_scope_support', true, 'boolean');

        $maintenance = app(MaintenanceService::class);

        $this->assertTrue($maintenance->isScopeActive('support'));
        $this->assertFalse($maintenance->isScopeActive('checkout'));
    }

    // ======================= Maintenance Tingkat 3 =======================

    public function test_admin_can_create_maintenance_window(): void
    {
        $response = $this->actingAs($this->admin())->post('/admin/maintenance', [
            'title' => 'Upgrade Node Jakarta',
            'description' => 'Peningkatan kapasitas penyimpanan.',
            'scopes' => ['checkout', 'provisioning'],
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDay()->addHours(2)->format('Y-m-d\TH:i'),
            'notice_days' => 3,
            'notify_customers' => 1,
        ]);

        $response->assertRedirect(route('admin.maintenance.index'));
        $this->assertDatabaseHas('maintenance_windows', [
            'title' => 'Upgrade Node Jakarta',
            'status' => 'scheduled',
        ]);
    }

    public function test_maintenance_window_requires_end_after_start(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/maintenance', [
                'title' => 'Jadwal Salah',
                'scopes' => ['checkout'],
                'starts_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
                'ends_at' => now()->addDay()->format('Y-m-d\TH:i'),
                'notice_days' => 3,
            ])
            ->assertSessionHasErrors('ends_at');
    }

    public function test_maintenance_window_requires_at_least_one_scope(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/maintenance', [
                'title' => 'Tanpa Cakupan',
                'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
                'ends_at' => now()->addDay()->addHour()->format('Y-m-d\TH:i'),
                'notice_days' => 3,
            ])
            ->assertSessionHasErrors('scopes');
    }

    public function test_running_window_blocks_its_scope(): void
    {
        MaintenanceWindow::create([
            'title' => 'Maintenance Berjalan',
            'scopes' => ['checkout'],
            'starts_at' => now()->subMinutes(10),
            'ends_at' => now()->addHour(),
            'status' => 'in_progress',
            'notice_days' => 0,
        ]);

        $this->get('/checkout')->assertStatus(503);
    }

    public function test_window_limited_to_specific_spec_does_not_block_others(): void
    {
        $maintenance = app(MaintenanceService::class);

        MaintenanceWindow::create([
            'title' => 'Maintenance Paket Tertentu',
            'scopes' => ['checkout'],
            'spec_ids' => [999999],
            'starts_at' => now()->subMinutes(5),
            'ends_at' => now()->addHour(),
            'status' => 'in_progress',
            'notice_days' => 0,
        ]);

        $this->assertNull($maintenance->runningWindowFor('checkout', 12345));
        $this->assertNotNull($maintenance->runningWindowFor('checkout', 999999));
    }

    public function test_admin_can_start_and_complete_window(): void
    {
        $window = MaintenanceWindow::create([
            'title' => 'Jadwal Manual',
            'scopes' => ['support'],
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
            'status' => 'scheduled',
            'notice_days' => 1,
        ]);

        $this->actingAs($this->admin())->post("/admin/maintenance/{$window->id}/start")->assertRedirect();
        $this->assertSame('in_progress', $window->fresh()->status);

        $this->actingAs($this->admin())->post("/admin/maintenance/{$window->id}/complete")->assertRedirect();
        $this->assertSame('completed', $window->fresh()->status);
    }

    public function test_admin_can_cancel_window(): void
    {
        $window = MaintenanceWindow::create([
            'title' => 'Akan Dibatalkan',
            'scopes' => ['support'],
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => 'scheduled',
            'notice_days' => 1,
        ]);

        $this->actingAs($this->admin())->post("/admin/maintenance/{$window->id}/cancel")->assertRedirect();
        $this->assertSame('cancelled', $window->fresh()->status);
    }

    // ======================= Command maintenance:sync =======================

    public function test_sync_command_starts_and_completes_windows(): void
    {
        $due = MaintenanceWindow::create([
            'title' => 'Sudah Waktunya',
            'scopes' => ['support'],
            'starts_at' => now()->subMinutes(5),
            'ends_at' => now()->addHour(),
            'status' => 'scheduled',
            'notice_days' => 0,
        ]);

        $finished = MaintenanceWindow::create([
            'title' => 'Sudah Lewat',
            'scopes' => ['support'],
            'starts_at' => now()->subHours(3),
            'ends_at' => now()->subHour(),
            'status' => 'in_progress',
            'notice_days' => 0,
        ]);

        $this->artisan('maintenance:sync')->assertExitCode(0);

        $this->assertSame('in_progress', $due->fresh()->status);
        $this->assertSame('completed', $finished->fresh()->status);
    }

    public function test_sync_command_sets_component_to_maintenance_and_back(): void
    {
        $window = MaintenanceWindow::create([
            'title' => 'Maintenance Support',
            'scopes' => ['support'],
            'starts_at' => now()->subMinutes(5),
            'ends_at' => now()->addHour(),
            'status' => 'in_progress',
            'notice_days' => 0,
        ]);

        $this->artisan('maintenance:sync');
        $this->assertSame('maintenance', SystemComponent::where('slug', 'support')->first()->status);

        $window->update(['status' => 'completed']);
        $this->artisan('maintenance:sync');
        $this->assertSame('operational', SystemComponent::where('slug', 'support')->first()->status);
    }

    public function test_sync_command_is_idempotent_for_notifications(): void
    {
        $window = MaintenanceWindow::create([
            'title' => 'Akan Diumumkan',
            'scopes' => ['support'],
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => 'scheduled',
            'notice_days' => 3,
            'notify_customers' => true,
        ]);

        $this->artisan('maintenance:sync');
        $firstNotifiedAt = $window->fresh()->customers_notified_at;
        $this->assertNotNull($firstNotifiedAt);

        $this->artisan('maintenance:sync');
        $this->assertEquals($firstNotifiedAt, $window->fresh()->customers_notified_at);
    }

    // ======================= Halaman status =======================

    public function test_status_page_renders_components_from_database(): void
    {
        $response = $this->get('/status');

        $response->assertStatus(200);
        $response->assertSee('Status Komponen Layanan');
        $response->assertSee('Jaringan VPS');
    }

    public function test_status_page_shows_running_maintenance(): void
    {
        MaintenanceWindow::create([
            'title' => 'Pemeliharaan Tampil di Status',
            'scopes' => ['support'],
            'starts_at' => now()->subMinutes(5),
            'ends_at' => now()->addHour(),
            'status' => 'in_progress',
            'notice_days' => 0,
        ]);

        $this->get('/status')
            ->assertStatus(200)
            ->assertSee('Pemeliharaan Aktif')
            ->assertSee('Pemeliharaan Tampil di Status');
    }

    public function test_status_page_reflects_degraded_component(): void
    {
        SystemComponent::where('slug', 'vps-network')->update(['status' => 'outage']);

        $this->get('/status')
            ->assertStatus(200)
            ->assertSee('Sebagian Layanan Terganggu');
    }
}
