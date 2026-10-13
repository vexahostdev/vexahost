<?php

namespace Tests\Feature;

use App\Models\RenewalReminder;
use App\Models\User;
use App\Models\VpsInstance;
use App\Notifications\AdminRenewalDigestNotification;
use App\Notifications\InstanceRenewalReminderNotification;
use App\Services\RenewalService;
use App\Services\SettingsService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * PHASE 3 - Renewal Reminder dan Siklus Hidup Layanan.
 */
class RenewalReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    protected function customer(): User
    {
        return User::create([
            'full_name' => 'Pelanggan Renewal',
            'username' => 'renewal' . uniqid(),
            'email' => 'renewal' . uniqid() . '@test.id',
            'password' => bcrypt('password123'),
            'is_admin' => false,
        ]);
    }

    protected function makeInstance(array $attributes = []): VpsInstance
    {
        return VpsInstance::create(array_merge([
            'customer_id' => $this->customer()->id,
            'hostname' => 'vx-renewal-' . uniqid(),
            'status' => 'running',
            'cpu' => 2,
            'ram' => 4,
            'disk' => 60,
            'control_panel' => 'coolify',
            'billing_cycle' => 'monthly',
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->addDays(3),
        ], $attributes));
    }

    protected function settings(): SettingsService
    {
        return app(SettingsService::class);
    }

    // ======================= Masa tenggang per jenis produk =======================

    public function test_grace_days_differ_per_product_type(): void
    {
        $renewal = app(RenewalService::class);

        $this->assertSame(5, $renewal->graceDaysForType('vps'));
        $this->assertSame(25, $renewal->graceDaysForType('database'));
        $this->assertSame(25, $renewal->graceDaysForType('app'));
    }

    /**
     * Pengaman keras: tenggang tidak boleh menyentuh batas penghapusan Supplier,
     * meskipun pengaturan terlanjur diisi terlalu besar.
     */
    public function test_grace_days_are_capped_below_supplier_limit(): void
    {
        $this->settings()->set('renewal_grace_days_vps', 99, 'integer');
        $this->settings()->set('renewal_grace_days_database', 99, 'integer');

        $renewal = app(RenewalService::class);

        $this->assertSame(6, $renewal->graceDaysForType('vps'));
        $this->assertSame(29, $renewal->graceDaysForType('database'));
    }

    public function test_safety_margin_is_reported(): void
    {
        $renewal = app(RenewalService::class);
        $instance = $this->makeInstance();

        $this->assertSame('vps', $renewal->productType($instance));
        $this->assertSame(2, $renewal->safetyMarginFor($instance));
    }

    public function test_database_package_uses_database_grace_period(): void
    {
        $renewal = app(RenewalService::class);

        $instance = $this->makeInstance([
            'control_panel' => 'managed_database',
            'db_engine' => 'postgres',
            'hostname' => 'vx-db-uji',
        ]);

        $this->assertSame('database', $renewal->productType($instance));
        $this->assertSame(25, $renewal->graceDaysFor($instance));
    }

    public function test_grace_period_end_is_calculated_from_expiry(): void
    {
        $renewal = app(RenewalService::class);
        $instance = $this->makeInstance(['expires_at' => now()->addDays(3)]);

        $renewal->applyGracePeriod($instance);

        $this->assertNotNull($instance->fresh()->grace_period_ends_at);
        $this->assertTrue(
            $instance->fresh()->grace_period_ends_at->isSameDay($instance->expires_at->copy()->addDays(5))
        );
    }

    // ======================= Pengiriman pengingat =======================

    public function test_h_minus_3_reminder_is_sent(): void
    {
        Notification::fake();

        $instance = $this->makeInstance(['expires_at' => now()->addDays(3)]);

        $this->artisan('renewal:remind')->assertExitCode(0);

        Notification::assertSentTo($instance->customer, InstanceRenewalReminderNotification::class);
        $this->assertDatabaseHas('renewal_reminders', [
            'vps_instance_id' => $instance->id,
            'stage' => RenewalReminder::STAGE_H3,
        ]);
    }

    public function test_h_minus_1_reminder_is_sent(): void
    {
        Notification::fake();

        $instance = $this->makeInstance(['expires_at' => now()->addDay()]);

        $this->artisan('renewal:remind');

        $this->assertDatabaseHas('renewal_reminders', [
            'vps_instance_id' => $instance->id,
            'stage' => RenewalReminder::STAGE_H1,
        ]);
    }

    /**
     * Pengaman utama terhadap surel ganda: indeks unik pada
     * (vps_instance_id, stage, period_expires_at).
     */
    public function test_reminders_are_idempotent_across_runs(): void
    {
        Notification::fake();

        $instance = $this->makeInstance(['expires_at' => now()->addDays(3)]);

        $this->artisan('renewal:remind');
        $this->artisan('renewal:remind');
        $this->artisan('renewal:remind');

        $this->assertSame(
            1,
            RenewalReminder::where('vps_instance_id', $instance->id)
                ->where('stage', RenewalReminder::STAGE_H3)
                ->count()
        );

        Notification::assertSentToTimes(
            $instance->customer,
            InstanceRenewalReminderNotification::class,
            1
        );
    }

    public function test_dry_run_does_not_write_or_send(): void
    {
        Notification::fake();

        $instance = $this->makeInstance(['expires_at' => now()->addDays(3)]);

        $this->artisan('renewal:remind', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame(0, RenewalReminder::count());
        Notification::assertNothingSent();
        $this->assertSame('running', $instance->fresh()->status);
    }

    // ======================= Siklus hidup =======================

    public function test_expired_instance_is_suspended_and_grace_applied(): void
    {
        Notification::fake();

        $instance = $this->makeInstance(['expires_at' => now()->subDay()]);

        $this->artisan('renewal:remind');

        $fresh = $instance->fresh();
        $this->assertSame('suspended', $fresh->status);
        $this->assertNotNull($fresh->grace_period_ends_at);
        $this->assertDatabaseHas('renewal_reminders', [
            'vps_instance_id' => $instance->id,
            'stage' => RenewalReminder::STAGE_H0,
        ]);
    }

    public function test_instance_is_terminated_after_grace_period_ends(): void
    {
        Notification::fake();

        $instance = $this->makeInstance([
            'status' => 'suspended',
            'expires_at' => now()->subDays(6),
            'grace_period_ends_at' => now()->subDay(),
        ]);

        $this->artisan('renewal:remind');

        $this->assertSame('terminated', $instance->fresh()->status);
        $this->assertDatabaseHas('renewal_reminders', [
            'vps_instance_id' => $instance->id,
            'stage' => RenewalReminder::STAGE_GRACE_ENDED,
        ]);
    }

    public function test_instance_still_within_grace_is_not_terminated(): void
    {
        Notification::fake();

        $instance = $this->makeInstance([
            'status' => 'suspended',
            'expires_at' => now()->subDay(),
            'grace_period_ends_at' => now()->addDays(4),
        ]);

        $this->artisan('renewal:remind');

        $this->assertSame('suspended', $instance->fresh()->status);
    }

    public function test_instance_without_expiry_is_ignored(): void
    {
        Notification::fake();

        $instance = $this->makeInstance(['expires_at' => null]);

        $this->artisan('renewal:remind');

        $this->assertSame(0, RenewalReminder::where('vps_instance_id', $instance->id)->count());
        $this->assertSame('running', $instance->fresh()->status);
    }

    // ======================= Digest admin =======================

    public function test_admin_receives_single_digest_not_one_mail_per_instance(): void
    {
        Notification::fake();

        $this->makeInstance(['expires_at' => now()->addDays(3)]);
        $this->makeInstance(['expires_at' => now()->addDays(3)]);
        $this->makeInstance(['expires_at' => now()->addDays(3)]);

        $this->artisan('renewal:remind');

        Notification::assertSentOnDemandTimes(AdminRenewalDigestNotification::class, count(\App\Support\MailIdentity::adminRecipients()));
    }

    public function test_digest_is_skipped_when_disabled(): void
    {
        Notification::fake();

        $this->settings()->set('digest_enabled', false, 'boolean');
        $this->makeInstance(['expires_at' => now()->addDays(3)]);

        $this->artisan('renewal:remind');

        Notification::assertNothingSentTo(
            (new \Illuminate\Notifications\AnonymousNotifiable)
                ->route('mail', $this->settings()->get('admin_notification_email'))
        );
    }

    public function test_no_digest_when_nothing_is_due(): void
    {
        Notification::fake();

        $this->makeInstance(['expires_at' => now()->addDays(20)]);

        $this->artisan('renewal:remind')->assertExitCode(0);

        Notification::assertNothingSent();
    }
}
