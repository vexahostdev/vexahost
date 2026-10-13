<?php

namespace App\Support;

use App\Services\SettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MailIdentity
{
    public const CACHE_KEY = 'mail_identity';
    public const CACHE_TTL = 300; // 5 menit

    /**
     * @return array{address: string, name: string, source: string}
     */
    public static function noreply(): array
    {
        $data = static::getResolvedData();

        return [
            'address' => $data['noreply_address'],
            'name' => $data['noreply_name'],
            'source' => $data['sources']['noreply'],
        ];
    }

    /**
     * @return array{address: string, name: string, source: string}
     */
    public static function support(): array
    {
        $data = static::getResolvedData();

        return [
            'address' => $data['support_address'],
            'name' => $data['support_name'],
            'source' => $data['sources']['support'],
        ];
    }

    /**
     * @return array{address: string, name: string, source: string}
     */
    public static function replyTo(): array
    {
        $data = static::getResolvedData();

        return [
            'address' => $data['reply_to_address'],
            'name' => $data['reply_to_name'],
            'source' => $data['sources']['reply_to'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function adminRecipients(): array
    {
        $data = static::getResolvedData();

        return $data['admin_recipients'];
    }

    /**
     * Alamat surel kontak bantuan yang ditampilkan ke klien.
     */
    public static function contactEmail(): string
    {
        return static::support()['address'];
    }

    public static function flushCache(): void
    {
        Cache::forget(static::CACHE_KEY);
    }

    /**
     * Memeriksa apakah domain alamat surel pengirim terdaftar di domain yang diizinkan.
     */
    public static function isDomainAllowed(string $email, array $allowedDomains): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $parts = explode('@', $email);
        $domain = strtolower(end($parts));

        foreach ($allowedDomains as $allowed) {
            $allowed = strtolower(trim($allowed));
            if ($allowed !== '' && ($domain === $allowed || str_ends_with($domain, '.' . $allowed))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolusi identitas surel secara defensif dengan prioritas: DB -> config/env -> default aman.
     */
    protected static function getResolvedData(): array
    {
        return Cache::remember(static::CACHE_KEY, static::CACHE_TTL, function () {
            return static::resolveFreshData();
        });
    }

    public static function resolveFreshData(): array
    {
        $allowedDomains = (array) config('mail.allowed_sender_domains', ['vexahostcloud.my.id']);
        if (empty($allowedDomains)) {
            $allowedDomains = ['vexahostcloud.my.id'];
        }

        $configNoreplyAddress = config('mail.from.address') ?: 'noreply@vexahostcloud.my.id';
        $configNoreplyName = config('mail.from.name') ?: 'VexaHost Cloud';

        $configSupportAddress = config('mail.support.address') ?: 'support@vexahostcloud.my.id';
        $configSupportName = config('mail.support.name') ?: 'VexaHost Support';

        $configReplyToAddress = config('mail.reply_to.address') ?: $configSupportAddress;
        $configReplyToName = config('mail.reply_to.name') ?: $configSupportName;

        // Kumpulkan penerima admin dari konfigurasi lama & baru
        $configAdmins = [];
        if (!empty(config('mail.admin_addresses'))) {
            $configAdmins = (array) config('mail.admin_addresses');
        }
        if ($oldMailAdmin = config('mail.admin_address')) {
            $configAdmins[] = $oldMailAdmin;
        }
        if ($oldVexaAdmin = config('vexahost.admin_email')) {
            $configAdmins[] = $oldVexaAdmin;
        }

        $dbNoreplyAddress = null;
        $dbNoreplyName = null;
        $dbSupportAddress = null;
        $dbSupportName = null;
        $dbReplyToAddress = null;
        $dbAdminAddresses = [];

        try {
            if (Schema::hasTable('settings')) {
                $settings = app(SettingsService::class);
                $dbNoreplyAddress = trim((string) $settings->get('mail_noreply_address', ''));
                $dbNoreplyName = trim((string) $settings->get('mail_noreply_name', ''));
                $dbSupportAddress = trim((string) $settings->get('mail_support_address', ''));
                $dbSupportName = trim((string) $settings->get('mail_support_name', ''));
                $dbReplyToAddress = trim((string) $settings->get('mail_reply_to_address', ''));

                $rawAdmins = (string) $settings->get('mail_admin_addresses', '');
                if ($rawAdmins !== '') {
                    $dbAdminAddresses = preg_split('/[\r\n,;]+/', $rawAdmins) ?: [];
                }

                // Sertakan juga nilai admin_notification_email lama jika ada di tabel settings
                $legacyAdmin = trim((string) $settings->get('admin_notification_email', ''));
                if ($legacyAdmin !== '') {
                    $dbAdminAddresses[] = $legacyAdmin;
                }
            }
        } catch (\Throwable $e) {
            // Defensif: jika DB bermasalah / belum migrasi, lanjutkan dengan config
        }

        // Resolusi Noreply
        $sources = [
            'noreply' => 'config',
            'support' => 'config',
            'reply_to' => 'config',
            'admin' => 'config',
        ];

        if ($dbNoreplyAddress !== null && $dbNoreplyAddress !== '') {
            if (static::isDomainAllowed($dbNoreplyAddress, $allowedDomains)) {
                $resolvedNoreplyAddress = $dbNoreplyAddress;
                $sources['noreply'] = 'database';
            } else {
                Log::warning("Alamat noreply dari DB [{$dbNoreplyAddress}] diabaikan karena domain tidak terverifikasi. Fallback ke konfigurasi.");
                $resolvedNoreplyAddress = $configNoreplyAddress;
            }
        } else {
            $resolvedNoreplyAddress = $configNoreplyAddress;
        }
        $resolvedNoreplyName = ($dbNoreplyName !== null && $dbNoreplyName !== '') ? $dbNoreplyName : $configNoreplyName;

        // Resolusi Support
        if ($dbSupportAddress !== null && $dbSupportAddress !== '') {
            if (static::isDomainAllowed($dbSupportAddress, $allowedDomains)) {
                $resolvedSupportAddress = $dbSupportAddress;
                $sources['support'] = 'database';
            } else {
                Log::warning("Alamat support dari DB [{$dbSupportAddress}] diabaikan karena domain tidak terverifikasi. Fallback ke konfigurasi.");
                $resolvedSupportAddress = $configSupportAddress;
            }
        } else {
            $resolvedSupportAddress = $configSupportAddress;
        }
        $resolvedSupportName = ($dbSupportName !== null && $dbSupportName !== '') ? $dbSupportName : $configSupportName;

        // Resolusi Reply-To
        if ($dbReplyToAddress !== null && $dbReplyToAddress !== '') {
            if (filter_var($dbReplyToAddress, FILTER_VALIDATE_EMAIL)) {
                $resolvedReplyToAddress = $dbReplyToAddress;
                $sources['reply_to'] = 'database';
            } else {
                $resolvedReplyToAddress = $configReplyToAddress;
            }
        } else {
            $resolvedReplyToAddress = $configReplyToAddress;
        }
        $resolvedReplyToName = $resolvedSupportName;

        // Resolusi Admin Recipients
        $mergedAdmins = array_merge($dbAdminAddresses, $configAdmins);
        $cleanAdmins = [];
        foreach ($mergedAdmins as $item) {
            $email = trim((string) $item);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $cleanAdmins[] = strtolower($email);
            }
        }
        $cleanAdmins = array_values(array_unique($cleanAdmins));

        if (empty($cleanAdmins) && config('mail.admin_addresses') === null) {
            $cleanAdmins = ['support@vexahostcloud.my.id', 'vexahostcloudtech@gmail.com'];
        }

        return [
            'noreply_address' => $resolvedNoreplyAddress,
            'noreply_name' => $resolvedNoreplyName,
            'support_address' => $resolvedSupportAddress,
            'support_name' => $resolvedSupportName,
            'reply_to_address' => $resolvedReplyToAddress,
            'reply_to_name' => $resolvedReplyToName,
            'admin_recipients' => $cleanAdmins,
            'sources' => $sources,
        ];
    }
}
