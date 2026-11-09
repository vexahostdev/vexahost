<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Services\SettingsService;
use App\Support\KatalogDokumentasi;
use App\Support\KatalogProduk;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * - Waktu disimpan UTC, tetapi ditampilkan dalam WIB (Asia/Jakarta, UTC+7).
 * - Tidak ada lagi klaim SLA/uptime di halaman pelanggan maupun publik.
 */
class DisplayTimeAndClaimsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array{0: User, 1: VpsInstance}
     */
    private function customerWithVps(): array
    {
        $customer = User::create([
            'username' => 'wibcustomer',
            'email' => 'wibcustomer@example.com',
            'full_name' => 'Pelanggan WIB',
            'password' => Hash::make('password123'),
            'channel' => 'website',
            'is_admin' => false,
        ]);
        $customer->switchToOrganization($customer->createPersonalOrganization());
        $customer = $customer->fresh();

        $spec = VpsSpec::where('category', 'vps')->first() ?? VpsSpec::first();
        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'coolify',
            'provider' => 'tencent',
            'hostname' => 'srv-wib',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'active',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => $spec->sell_price,
            'paid_at' => now(),
        ]);

        $vps = VpsInstance::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'order_id' => $order->id,
            'hostname' => 'srv-wib',
            'public_ip' => '139.180.200.40',
            'os' => 'ubuntu2404',
            'status' => 'running',
            'cpu' => 2,
            'ram' => 4,
            'disk' => 60,
            'control_panel' => 'coolify',
            'provider' => 'tencent',
            'expires_at' => now()->addDays(20),
        ]);

        return [$customer, $vps];
    }

    // ======================= Waktu tampil dalam WIB =======================

    public function test_ticket_times_are_shown_in_wib_while_stored_in_utc(): void
    {
        // 01:24 UTC = 08:24 WIB.
        Carbon::setTestNow(Carbon::parse('2026-09-19 01:24:00', 'UTC'));
        [$customer, $vps] = $this->customerWithVps();

        $ticket = SupportTicket::create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->current_organization_id,
            'vps_instance_id' => $vps->id,
            'subject' => 'Cek jam tampil',
            'priority' => 'medium',
            'status' => 'open',
        ]);
        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $customer->id,
            'message' => 'Pesan uji zona waktu.',
            'is_admin_reply' => false,
        ]);

        // Database tetap UTC.
        $this->assertSame('2026-09-19 01:24:00', DB::table('support_tickets')->where('id', $ticket->id)->value('created_at'));

        $this->actingAs($customer)->get("/dashboard/support/{$ticket->id}")
            ->assertOk()
            ->assertSee('19 Sep 2026, 08:24 WIB')
            ->assertDontSee('01:24 WIB');

        $this->actingAs($customer)->get("/dashboard/vps/{$vps->id}")
            ->assertOk()
            ->assertSee('19 Sep 2026, 08:24 WIB');
    }

    public function test_date_near_midnight_uses_wib_calendar_day(): void
    {
        // 20:00 UTC tanggal 18 = 03:00 WIB tanggal 19.
        Carbon::setTestNow(Carbon::parse('2026-09-18 20:00:00', 'UTC'));
        [$customer, $vps] = $this->customerWithVps();
        $vps->update(['expires_at' => Carbon::parse('2026-10-18 20:00:00', 'UTC')]);

        $this->actingAs($customer)->get("/dashboard/vps/{$vps->id}")
            ->assertOk()
            ->assertSee('Aktif s/d 19 Oct 2026');
    }

    // ======================= Tidak ada klaim SLA =======================

    public function test_public_pages_have_no_uptime_sla_claims(): void
    {
        $paths = ['/', '/terms', '/privacy', '/refund', '/status', '/docs'];

        // Halaman produk dan kelompok dokumentasi lahir belakangan; daftar yang
        // tidak ikut tumbuh membuat penjaga ini diam-diam berhenti menjaga.
        foreach (array_keys(KatalogProduk::semua()) as $slug) {
            $paths[] = '/'.$slug;
        }
        foreach (array_keys(KatalogDokumentasi::kelompok()) as $slug) {
            $paths[] = '/docs/'.$slug;
        }

        foreach ($paths as $path) {
            $this->get($path)
                ->assertOk()
                ->assertDontSee('SLA 99.9%')
                ->assertDontSee('Uptime SLA')
                ->assertDontSee('Garansi Uptime');
        }
    }

    public function test_customer_dashboard_has_no_uptime_sla_claims(): void
    {
        [$customer, $vps] = $this->customerWithVps();

        $this->actingAs($customer)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Uptime SLA')
            ->assertDontSee('99.9%');

        $this->actingAs($customer)->get("/dashboard/vps/{$vps->id}")
            ->assertOk()
            ->assertDontSee('SLA Garansi')
            ->assertDontSee('99.9%');
    }

    public function test_landing_and_footer_have_no_unprovable_infrastructure_claims(): void
    {
        $response = $this->get('/')->assertOk();

        foreach ([
            'IOPS', 'MB/s', 'RAID-10', 'Gedung Cyber 1', 'ISO/IEC 27001', 'SOC 2', '10 Gbps',
            'Terabit', 'CPU Steal Policy', 'Always-On', '24/7', 'Tier-3', 'kelas perbankan',
            'latency rendah', 'Python web scraper',
        ] as $claim) {
            $response->assertDontSee($claim, false);
        }

        // Jam layanan di landing mengikuti pengaturan admin (sama dengan dashboard).
        $response->assertSee(app(SettingsService::class)->get('support_hours'));
        $response->assertSee('Tencent Cloud');
    }

    public function test_package_features_do_not_promise_work_the_team_does_not_do(): void
    {
        // Tim tidak memasang backup harian maupun memory swap (keputusan pemilik 2026-09-19).
        $features = VpsSpec::all()->pluck('features')->implode(' ');

        foreach (['backup', 'Backup', 'Swap', 'swap', 'Anti-Crash', '<10ms', '~3–10 ms', 'hidup 100%', 'menjamin'] as $claim) {
            $this->assertStringNotContainsString($claim, $features, "Fitur paket masih memuat klaim: {$claim}");
        }

        // Halaman publik yang menampilkan fitur paket juga bersih.
        foreach (['/', '/checkout'] as $path) {
            $this->get($path)->assertOk()
                ->assertDontSee('backup otomatis harian')
                ->assertDontSee('Automated daily backup')
                ->assertDontSee('Health Check');
        }
    }

    public function test_terms_describe_third_party_infrastructure_without_uptime_promise(): void
    {
        $this->get('/terms')
            ->assertOk()
            ->assertSee('penyedia pihak ketiga')
            ->assertSee('tidak menjanjikan persentase ketersediaan (uptime) tertentu', false)
            ->assertSee('tidak menyediakan pencadangan otomatis')
            ->assertDontSee('RAID-10')
            ->assertSee('TOS-2026-V5');
    }

    public function test_terms_version_cannot_be_overridden_by_stale_env(): void
    {
        putenv('TERMS_VERSION=TOS-2026-V3');
        $_ENV['TERMS_VERSION'] = $_SERVER['TERMS_VERSION'] = 'TOS-2026-V3';

        try {
            $config = require config_path('legal.php');
            $this->assertSame('TOS-2026-V5', $config['terms_version']);
        } finally {
            putenv('TERMS_VERSION');
            unset($_ENV['TERMS_VERSION'], $_SERVER['TERMS_VERSION']);
        }
    }

    public function test_sitemap_no_longer_lists_sla_page(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertDontSee('/sla<', false);
    }
}
