<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\VpsSpec;
use App\Notifications\AdminNewPaidOrderNotification;
use App\Notifications\AdminTicketNotification;
use App\Notifications\InvoiceCreatedNotification;
use App\Notifications\PaymentRejectedNotification;
use App\Notifications\TemplatedNotification;
use App\Notifications\TicketRepliedNotification;
use App\Notifications\WelcomeCredentialsNotification;
use App\Services\AdminAlertService;
use App\Services\SettingsService;
use App\Support\MailIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MailIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $this->admin = User::where('email', 'vexahostcloudtech@gmail.com')->first();

        $this->customer = User::create([
            'username' => 'mailtestcustomer',
            'email' => 'mailtestcustomer@example.com',
            'full_name' => 'Mail Test Customer',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'channel' => 'website',
            'is_admin' => false,
        ]);

        app(SettingsService::class)->flush();
        MailIdentity::flushCache();
    }

    public function test_support_emails_have_support_from_and_reply_to(): void
    {
        $user = $this->customer;
        $order = Order::create([
            'customer_id' => $user->id,
            'vps_spec_id' => 1,
            'control_panel' => 'coolify',
            'provider' => 'tencent',
            'hostname' => 'vps-test',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 100000,
        ]);
        $ticket = SupportTicket::create([
            'customer_id' => $user->id,
            'subject' => 'Kendala Server',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        // 1. TicketRepliedNotification
        $notif1 = new TicketRepliedNotification($ticket, 'Balasan support teknis');
        $mail1 = $notif1->toMail($user);
        $this->assertSame('support@vexahostcloud.my.id', $mail1->from[0]);
        $this->assertSame('support@vexahostcloud.my.id', $mail1->replyTo[0][0]);

        // 2. PaymentRejectedNotification
        $notif2 = new PaymentRejectedNotification($order);
        $mail2 = $notif2->toMail($user);
        $this->assertSame('support@vexahostcloud.my.id', $mail2->from[0]);
        $this->assertSame('support@vexahostcloud.my.id', $mail2->replyTo[0][0]);

        // 3. TemplatedNotification dengan senderType support (Abuse)
        $notif3 = new TemplatedNotification('Subjek Abuse', 'Isi pelanggaran', 'ABUSE', 'support');
        $mail3 = $notif3->toMail($user);
        $this->assertSame('support@vexahostcloud.my.id', $mail3->from[0]);
        $this->assertSame('support@vexahostcloud.my.id', $mail3->replyTo[0][0]);
    }

    public function test_noreply_emails_have_noreply_from_and_support_reply_to(): void
    {
        $user = $this->customer;
        $order = Order::create([
            'customer_id' => $user->id,
            'vps_spec_id' => 1,
            'control_panel' => 'coolify',
            'provider' => 'tencent',
            'hostname' => 'vps-test-2',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'pending',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 100000,
        ]);
        $invoice = Invoice::create([
            'order_id' => $order->id,
            'customer_id' => $user->id,
            'invoice_number' => 'INV-TEST-001',
            'amount' => 100000,
            'subtotal' => 100000,
            'status' => 'unpaid',
            'issued_at' => now(),
            'due_at' => now()->addDay(),
        ]);
        $ticket = SupportTicket::create([
            'customer_id' => $user->id,
            'subject' => 'Tiket Baru',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        // Sampel 1: WelcomeCredentialsNotification
        $notif1 = new WelcomeCredentialsNotification('secret123');
        $mail1 = $notif1->toMail($user);
        $this->assertSame('noreply@vexahostcloud.my.id', $mail1->from[0]);
        $this->assertSame('support@vexahostcloud.my.id', $mail1->replyTo[0][0]);

        // Sampel 2: InvoiceCreatedNotification
        $notif2 = new InvoiceCreatedNotification($invoice);
        $mail2 = $notif2->toMail($user);
        $this->assertSame('noreply@vexahostcloud.my.id', $mail2->from[0]);
        $this->assertSame('support@vexahostcloud.my.id', $mail2->replyTo[0][0]);

        // Sampel 3: AdminTicketNotification
        $notif3 = new AdminTicketNotification($ticket, 'Pesan tiket baru');
        $mail3 = $notif3->toMail($user);
        $this->assertSame('noreply@vexahostcloud.my.id', $mail3->from[0]);
        $this->assertSame('support@vexahostcloud.my.id', $mail3->replyTo[0][0]);

        // Sampel 4: TemplatedNotification broadcast (default noreply)
        $notif4 = new TemplatedNotification('Broadcast', 'Info maintenance', 'INFO');
        $mail4 = $notif4->toMail($user);
        $this->assertSame('noreply@vexahostcloud.my.id', $mail4->from[0]);
        $this->assertSame('support@vexahostcloud.my.id', $mail4->replyTo[0][0]);
    }

    public function test_admin_alerts_sent_to_multiple_addresses(): void
    {
        Notification::fake();

        $user = $this->customer;
        $order = Order::create([
            'customer_id' => $user->id,
            'vps_spec_id' => 1,
            'control_panel' => 'coolify',
            'provider' => 'tencent',
            'hostname' => 'vps-paid-test',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'paid',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 100000,
            'paid_at' => now(),
        ]);

        $service = app(AdminAlertService::class);
        $recipients = $service->adminEmails();

        $this->assertCount(2, $recipients);
        $this->assertContains('support@vexahostcloud.my.id', $recipients);
        $this->assertContains('vexahostcloudtech@gmail.com', $recipients);

        $service->orderPaid($order);

        Notification::assertSentOnDemand(
            AdminNewPaidOrderNotification::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'support@vexahostcloud.my.id';
            }
        );

        Notification::assertSentOnDemand(
            AdminNewPaidOrderNotification::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'vexahostcloudtech@gmail.com';
            }
        );
    }

    public function test_db_override_applied_when_filled_and_falls_back_when_empty(): void
    {
        // 1. Fallback env saat DB kosong
        app(SettingsService::class)->flush();
        MailIdentity::flushCache();
        $this->assertSame('noreply@vexahostcloud.my.id', MailIdentity::noreply()['address']);
        $this->assertSame('support@vexahostcloud.my.id', MailIdentity::support()['address']);

        // 2. Override DB dipakai saat diisi lewat SettingsService
        $settings = app(SettingsService::class);
        $settings->set('mail_noreply_address', 'custom-noreply@vexahostcloud.my.id');
        $settings->set('mail_support_address', 'custom-support@vexahostcloud.my.id');
        $settings->set('mail_reply_to_address', 'reply@vexahostcloud.my.id');
        MailIdentity::flushCache();

        $this->assertSame('custom-noreply@vexahostcloud.my.id', MailIdentity::noreply()['address']);
        $this->assertSame('custom-support@vexahostcloud.my.id', MailIdentity::support()['address']);
        $this->assertSame('reply@vexahostcloud.my.id', MailIdentity::replyTo()['address']);
    }

    public function test_db_override_with_external_domain_is_ignored(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('mail_noreply_address', 'external@gmail.com');
        $settings->set('mail_support_address', 'external@yahoo.com');
        MailIdentity::flushCache();

        // Harus fallback ke konfigurasi karena gmail.com bukan allowed domain
        $this->assertSame('noreply@vexahostcloud.my.id', MailIdentity::noreply()['address']);
        $this->assertSame('support@vexahostcloud.my.id', MailIdentity::support()['address']);
    }

    public function test_admin_settings_form_rejects_external_sender_domain(): void
    {
        $admin = $this->admin;

        $response = $this->actingAs($admin)->post(route('admin.settings.mail'), [
            'mail_noreply_address' => 'spammer@gmail.com',
            'mail_support_address' => 'support@vexahostcloud.my.id',
        ]);

        $response->assertSessionHasErrors(['mail_noreply_address']);
    }

    public function test_cache_is_flushed_after_admin_saves_mail_settings(): void
    {
        $admin = $this->admin;

        // Warm up cache
        MailIdentity::noreply();
        $this->assertTrue(Cache::has(MailIdentity::CACHE_KEY));

        $response = $this->actingAs($admin)->post(route('admin.settings.mail'), [
            'mail_noreply_address' => 'update@vexahostcloud.my.id',
            'mail_noreply_name' => 'VexaHost Cloud Updated',
            'mail_support_address' => 'support@vexahostcloud.my.id',
            'mail_support_name' => 'VexaHost Support',
            'mail_reply_to_address' => 'support@vexahostcloud.my.id',
            'mail_admin_addresses' => "admin1@vexahostcloud.my.id\nadmin2@vexahostcloud.my.id",
        ]);

        $response->assertSessionHasNoErrors();

        // Cache harus sudah terhapus
        $this->assertFalse(Cache::has(MailIdentity::CACHE_KEY));

        // Pembacaan ulang menghasilkan nilai baru
        $this->assertSame('update@vexahostcloud.my.id', MailIdentity::noreply()['address']);
    }

    public function test_admin_recipients_override_replaces_all_config_addresses(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('mail_admin_addresses', 'singleadmin@vexahostcloud.my.id');
        MailIdentity::flushCache();

        $recipients = MailIdentity::adminRecipients();
        $this->assertSame(['singleadmin@vexahostcloud.my.id'], $recipients);
    }

    public function test_migration_data_migrates_legacy_settings_idempotently(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('admin_notification_email', 'legacy-admin@contoh.id');
        $settings->set('mail_admin_addresses', '');
        $settings->set('support_email', 'support@vexahostcloud.my.id');
        $settings->set('mail_support_address', '');

        $migration = require database_path('migrations/2026_10_13_110000_migrate_legacy_email_settings_to_mail_identity.php');
        $migration->up();

        $adminAddresses = $settings->get('mail_admin_addresses');
        $this->assertStringContainsString('legacy-admin@contoh.id', $adminAddresses);
        $this->assertStringContainsString('support@vexahostcloud.my.id', $adminAddresses);
        $this->assertSame('support@vexahostcloud.my.id', $settings->get('mail_support_address'));
        // Legacy keys must still exist
        $this->assertSame('legacy-admin@contoh.id', $settings->get('admin_notification_email'));
        $this->assertSame('support@vexahostcloud.my.id', $settings->get('support_email'));
    }

    public function test_migration_data_ignores_unverified_support_domain(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('support_email', 'vexahostcloudtech@gmail.com');
        $settings->set('mail_support_address', '');

        $migration = require database_path('migrations/2026_10_13_110000_migrate_legacy_email_settings_to_mail_identity.php');
        $migration->up();

        // mail_support_address should remain empty because gmail.com is not verified
        $this->assertEmpty($settings->get('mail_support_address'));
    }

    public function test_support_and_notification_forms_succeed_without_legacy_fields(): void
    {
        $admin = $this->admin;

        // Support form without support_email
        $response = $this->actingAs($admin)->post(route('admin.settings.support'), [
            'support_whatsapp' => '+6281234567890',
            'support_hours' => '24/7 Mon-Sun',
            'social_instagram' => 'https://instagram.com/vexahost',
        ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // Notification form without admin_notification_email
        $response = $this->actingAs($admin)->post(route('admin.settings.notification'), [
            'admin_notification_whatsapp' => '6281234567890',
            'fulfillment_sla_minutes' => 60,
            'renewal_grace_days_vps' => 3,
            'renewal_grace_days_database' => 14,
            'renewal_grace_days_app' => 14,
        ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
    }
}

