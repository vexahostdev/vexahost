<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\SystemComponent;
use App\Services\SettingsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * PHASE 1 - Nilai awal pengaturan sistem dan komponen halaman status.
 *
 * Seeder ini aman dijalankan berulang: nilai yang sudah ada tidak ditimpa,
 * sehingga pengaturan yang sudah diubah admin tidak kembali ke bawaan.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->defaults() as $key => $definition) {
            Setting::firstOrCreate(
                ['key' => $key],
                [
                    'value' => Setting::serializeValue($definition['value'], $definition['type']),
                    'type' => $definition['type'],
                    'group' => $definition['group'],
                    'label' => $definition['label'],
                    'is_public' => $definition['is_public'] ?? false,
                    'sort_order' => $definition['sort_order'] ?? 0,
                ]
            );
        }

        // Token jalur pintas dibuat acak sekali saat pertama kali di-seed.
        Setting::firstOrCreate(
            ['key' => 'maintenance_bypass_token'],
            [
                'value' => Str::random(40),
                'type' => 'string',
                'group' => 'maintenance',
                'label' => 'Token Jalur Pintas Maintenance',
                'is_public' => false,
            ]
        );

        foreach ($this->components() as $index => $component) {
            SystemComponent::firstOrCreate(
                ['slug' => $component['slug']],
                [
                    'name' => $component['name'],
                    'description' => $component['description'],
                    'status' => 'operational',
                    'uptime_percent' => $component['uptime_percent'],
                    'sort_order' => $index,
                    'is_visible' => true,
                ]
            );
        }

        app(SettingsService::class)->flush();
    }

    /**
     * Seluruh pengaturan bawaan beserta tipe dan grupnya.
     */
    protected function defaults(): array
    {
        return [
            // ---------- Identitas Merek ----------
            'brand_name' => [
                'value' => 'VexaHost', 'type' => 'string', 'group' => 'brand',
                'label' => 'Nama Merek', 'is_public' => true, 'sort_order' => 1,
            ],
            'brand_tagline' => [
                'value' => 'Cloud Server Indonesia', 'type' => 'string', 'group' => 'brand',
                'label' => 'Tagline', 'is_public' => true, 'sort_order' => 2,
            ],
            'brand_accent_color' => [
                'value' => '#4A6FA5', 'type' => 'string', 'group' => 'brand',
                'label' => 'Warna Aksen', 'is_public' => true, 'sort_order' => 3,
            ],
            'brand_logo_path' => [
                'value' => null, 'type' => 'string', 'group' => 'brand',
                'label' => 'Logo', 'is_public' => true, 'sort_order' => 4,
            ],
            'brand_favicon_path' => [
                'value' => null, 'type' => 'string', 'group' => 'brand',
                'label' => 'Favicon', 'is_public' => true, 'sort_order' => 5,
            ],

            // ---------- Profil Perusahaan ----------
            'company_legal_name' => [
                'value' => 'PT DESTINARA CHAKRAWALA ARTHA', 'type' => 'string', 'group' => 'company',
                'label' => 'Nama Badan Hukum', 'is_public' => true, 'sort_order' => 1,
            ],
            'company_address' => [
                'value' => '', 'type' => 'string', 'group' => 'company',
                'label' => 'Alamat', 'is_public' => true, 'sort_order' => 2,
            ],
            'company_city' => [
                'value' => '', 'type' => 'string', 'group' => 'company',
                'label' => 'Kota', 'is_public' => true, 'sort_order' => 3,
            ],
            'company_postal_code' => [
                'value' => '', 'type' => 'string', 'group' => 'company',
                'label' => 'Kode Pos', 'is_public' => true, 'sort_order' => 4,
            ],
            'company_npwp' => [
                'value' => '', 'type' => 'string', 'group' => 'company',
                'label' => 'NPWP', 'is_public' => false, 'sort_order' => 5,
            ],
            'company_phone' => [
                'value' => '', 'type' => 'string', 'group' => 'company',
                'label' => 'Telepon', 'is_public' => true, 'sort_order' => 6,
            ],
            'company_email' => [
                'value' => '', 'type' => 'string', 'group' => 'company',
                'label' => 'Surel Perusahaan', 'is_public' => true, 'sort_order' => 7,
            ],
            'company_website' => [
                'value' => 'https://vexahostcloud.my.id', 'type' => 'string', 'group' => 'company',
                'label' => 'Situs Web', 'is_public' => true, 'sort_order' => 8,
            ],
            'invoice_signature_name' => [
                'value' => '', 'type' => 'string', 'group' => 'company',
                'label' => 'Nama Penanda Tangan Faktur', 'is_public' => false, 'sort_order' => 9,
            ],
            'invoice_signature_title' => [
                'value' => '', 'type' => 'string', 'group' => 'company',
                'label' => 'Jabatan Penanda Tangan', 'is_public' => false, 'sort_order' => 10,
            ],
            'invoice_footer_note' => [
                'value' => '', 'type' => 'string', 'group' => 'company',
                'label' => 'Catatan Kaki Faktur', 'is_public' => false, 'sort_order' => 11,
            ],

            // ---------- Kontak & Dukungan ----------
            'support_email' => [
                'value' => 'vexahostcloudtech@gmail.com', 'type' => 'string', 'group' => 'support',
                'label' => 'Surel Dukungan', 'is_public' => true, 'sort_order' => 1,
            ],
            'support_whatsapp' => [
                'value' => '', 'type' => 'string', 'group' => 'support',
                'label' => 'Nomor WhatsApp', 'is_public' => true, 'sort_order' => 2,
            ],
            'support_hours' => [
                'value' => 'Senin–Jumat, 09.00–17.00 WIB', 'type' => 'string', 'group' => 'support',
                'label' => 'Jam Operasional', 'is_public' => true, 'sort_order' => 3,
            ],
            'social_instagram' => [
                'value' => '', 'type' => 'string', 'group' => 'support',
                'label' => 'Instagram', 'is_public' => true, 'sort_order' => 4,
            ],
            'social_twitter' => [
                'value' => '', 'type' => 'string', 'group' => 'support',
                'label' => 'X / Twitter', 'is_public' => true, 'sort_order' => 5,
            ],
            'social_linkedin' => [
                'value' => '', 'type' => 'string', 'group' => 'support',
                'label' => 'LinkedIn', 'is_public' => true, 'sort_order' => 6,
            ],

            // ---------- Notifikasi & Tenggang ----------
            'admin_notification_email' => [
                'value' => 'vexahostcloudtech@gmail.com', 'type' => 'string', 'group' => 'notification',
                'label' => 'Surel Penerima Digest Admin', 'is_public' => false, 'sort_order' => 1,
            ],
            'digest_enabled' => [
                'value' => true, 'type' => 'boolean', 'group' => 'notification',
                'label' => 'Aktifkan Digest Harian', 'is_public' => false, 'sort_order' => 2,
            ],
            'fulfillment_sla_minutes' => [
                'value' => 120, 'type' => 'integer', 'group' => 'notification',
                'label' => 'Ambang Waktu Tanggap Pemenuhan (menit)', 'is_public' => false, 'sort_order' => 3,
            ],
            // Tenggang VexaHost WAJIB lebih pendek dari tenggang Supplier.
            // Supplier: VPS dihentikan pada H+3, database dan aplikasi setelah 30 hari.
            'renewal_grace_days_vps' => [
                'value' => 2, 'type' => 'integer', 'group' => 'notification',
                'label' => 'Tenggang VPS (hari)', 'is_public' => false, 'sort_order' => 4,
            ],
            'renewal_grace_days_database' => [
                'value' => 25, 'type' => 'integer', 'group' => 'notification',
                'label' => 'Tenggang Database (hari)', 'is_public' => false, 'sort_order' => 5,
            ],
            'renewal_grace_days_app' => [
                'value' => 25, 'type' => 'integer', 'group' => 'notification',
                'label' => 'Tenggang Aplikasi & AI (hari)', 'is_public' => false, 'sort_order' => 6,
            ],

            // ---------- Maintenance ----------
            'maintenance_global_enabled' => [
                'value' => false, 'type' => 'boolean', 'group' => 'maintenance',
                'label' => 'Maintenance Global', 'is_public' => false, 'sort_order' => 1,
            ],
            'maintenance_message' => [
                'value' => 'Layanan ini sedang dalam pemeliharaan. Silakan coba beberapa saat lagi.',
                'type' => 'string', 'group' => 'maintenance',
                'label' => 'Pesan Maintenance', 'is_public' => true, 'sort_order' => 2,
            ],
            'maintenance_allowed_ips' => [
                'value' => '', 'type' => 'string', 'group' => 'maintenance',
                'label' => 'Daftar IP yang Diizinkan', 'is_public' => false, 'sort_order' => 3,
            ],
            'maintenance_scope_checkout' => [
                'value' => false, 'type' => 'boolean', 'group' => 'maintenance',
                'label' => 'Tutup Checkout', 'is_public' => false, 'sort_order' => 10,
            ],
            'maintenance_scope_provisioning' => [
                'value' => false, 'type' => 'boolean', 'group' => 'maintenance',
                'label' => 'Tahan Provisioning', 'is_public' => false, 'sort_order' => 11,
            ],
            'maintenance_scope_vps_actions' => [
                'value' => false, 'type' => 'boolean', 'group' => 'maintenance',
                'label' => 'Nonaktifkan Aksi VPS', 'is_public' => false, 'sort_order' => 12,
            ],
            'maintenance_scope_support' => [
                'value' => false, 'type' => 'boolean', 'group' => 'maintenance',
                'label' => 'Tutup Tiket Baru', 'is_public' => false, 'sort_order' => 13,
            ],
            'maintenance_scope_dashboard' => [
                'value' => false, 'type' => 'boolean', 'group' => 'maintenance',
                'label' => 'Tutup Dasbor Klien', 'is_public' => false, 'sort_order' => 14,
            ],
        ];
    }

    /**
     * Komponen yang statusnya tampil di halaman status publik.
     */
    protected function components(): array
    {
        return [
            [
                'slug' => 'web-panel',
                'name' => 'Web Panel',
                'description' => 'Situs utama, dasbor klien, dan proses pemesanan.',
                'uptime_percent' => 99.98,
            ],
            [
                'slug' => 'provisioning',
                'name' => 'Provisioning',
                'description' => 'Antrean penyediaan dan penyerahan layanan baru.',
                'uptime_percent' => 99.95,
            ],
            [
                'slug' => 'payment',
                'name' => 'Pembayaran',
                'description' => 'Penerimaan pembayaran dan pemrosesan webhook.',
                'uptime_percent' => 99.99,
            ],
            [
                'slug' => 'vps-network',
                'name' => 'Jaringan VPS',
                'description' => 'Konektivitas dan ketersediaan instance pelanggan.',
                'uptime_percent' => 99.97,
            ],
            [
                'slug' => 'support',
                'name' => 'Dukungan',
                'description' => 'Tiket bantuan dan kanal dukungan pelanggan.',
                'uptime_percent' => 99.99,
            ],
        ];
    }
}
