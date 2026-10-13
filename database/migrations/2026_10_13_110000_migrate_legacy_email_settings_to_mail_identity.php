<?php

use App\Services\SettingsService;
use App\Support\MailIdentity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('settings')) {
            return;
        }

        // 1. Migrasi admin_notification_email -> mail_admin_addresses
        $mailAdminSetting = DB::table('settings')->where('key', 'mail_admin_addresses')->first();
        $adminNotifSetting = DB::table('settings')->where('key', 'admin_notification_email')->first();

        $mailAdminVal = $mailAdminSetting ? trim((string) $mailAdminSetting->value) : '';
        $adminNotifVal = $adminNotifSetting ? trim((string) $adminNotifSetting->value) : '';

        if ($mailAdminVal === '' && $adminNotifVal !== '') {
            $configAdmins = config('mail.admin_addresses') ?? ['support@vexahostcloud.my.id', 'vexahostcloudtech@gmail.com'];
            if (is_string($configAdmins)) {
                $configAdmins = array_filter(array_map('trim', explode(',', $configAdmins)));
            }

            $merged = array_values(array_unique(array_filter(array_merge([$adminNotifVal], (array) $configAdmins))));
            $mergedStr = implode(', ', $merged);

            DB::table('settings')->updateOrInsert(
                ['key' => 'mail_admin_addresses'],
                [
                    'value' => $mergedStr,
                    'type' => 'string',
                    'group' => 'mail',
                    'label' => 'Email Admin',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        // 2. Migrasi support_email -> mail_support_address
        $mailSupportSetting = DB::table('settings')->where('key', 'mail_support_address')->first();
        $supportSetting = DB::table('settings')->where('key', 'support_email')->first();

        $mailSupportVal = $mailSupportSetting ? trim((string) $mailSupportSetting->value) : '';
        $supportVal = $supportSetting ? trim((string) $supportSetting->value) : '';

        $allowedDomains = (array) config('mail.allowed_sender_domains', ['vexahostcloud.my.id']);

        if ($mailSupportVal === '' && $supportVal !== '' && MailIdentity::isDomainAllowed($supportVal, $allowedDomains)) {
            DB::table('settings')->updateOrInsert(
                ['key' => 'mail_support_address'],
                [
                    'value' => $supportVal,
                    'type' => 'string',
                    'group' => 'mail',
                    'label' => 'Alamat Surel Dukungan',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        // Flush caches
        Cache::forget(SettingsService::CACHE_KEY);
        MailIdentity::flushCache();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Idempotent data migration
    }
};
