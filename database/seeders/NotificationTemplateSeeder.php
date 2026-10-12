<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

/**
 * PHASE 2 - Template surel bawaan.
 *
 * Dua template teratas WAJIB terpisah dan tidak boleh digabung.
 * Template insiden tidak pernah menuduh pelanggan melanggar, karena
 * mayoritas penerimanya tidak melakukan pelanggaran apa pun.
 */
class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $template) {
            NotificationTemplate::firstOrCreate(
                ['code' => $template['code']],
                $template
            );
        }
    }

    protected function templates(): array
    {
        return [
            [
                'code' => NotificationTemplate::ABUSE_SUSPENSION,
                'name' => 'Suspensi Karena Pelanggaran',
                'description' => 'Dikirim HANYA ke pelanggan yang terbukti melanggar Acceptable Use Policy. '
                    . 'Jangan dipakai untuk pemberitahuan massal.',
                'subject' => 'Layanan Anda Disuspend — Pelanggaran Ketentuan Penggunaan',
                'body' => "Halo {{nama}},\n\n"
                    . "Layanan {{layanan}} milik Anda telah kami suspend karena terindikasi melanggar "
                    . "Ketentuan Penggunaan VexaHost.\n\n"
                    . "Jenis pelanggaran: {{jenis_pelanggaran}}\n"
                    . "Waktu terdeteksi: {{waktu}}\n\n"
                    . "Keterangan:\n{{keterangan}}\n\n"
                    . "Untuk mengajukan keberatan atau meminta pemulihan, silakan balas surel ini "
                    . "disertai penjelasan paling lambat {{batas_waktu}}.\n\n"
                    . "Perlu diketahui bahwa data pada layanan yang disuspend akan dihapus permanen "
                    . "setelah masa tenggang berakhir dan tidak dapat dipulihkan.\n\n"
                    . "Hormat kami,\nTim VexaHost",
                'variables' => ['nama', 'layanan', 'jenis_pelanggaran', 'waktu', 'keterangan', 'batas_waktu'],
                'is_active' => true,
            ],
            [
                'code' => NotificationTemplate::INFRA_INCIDENT,
                'name' => 'Insiden Infrastruktur (Broadcast Massal)',
                'description' => 'Dikirim ke seluruh pelanggan saat terjadi gangguan di sisi upstream. '
                    . 'TIDAK menuduh siapa pun melanggar, karena mayoritas penerima tidak bersalah.',
                'subject' => 'Pemberitahuan Gangguan Layanan VexaHost',
                'body' => "Halo {{nama}},\n\n"
                    . "Saat ini terjadi gangguan di tingkat infrastruktur upstream kami. "
                    . "Layanan Anda ikut terdampak dan sedang kami tangani.\n\n"
                    . "Waktu mulai gangguan: {{waktu_mulai}}\n"
                    . "Status penanganan: {{status}}\n\n"
                    . "{{keterangan}}\n\n"
                    . "Kami akan mengabarkan perkembangannya secara berkala. "
                    . "Perkembangan terbaru juga dapat Anda pantau di halaman status kami.\n\n"
                    . "Kami mohon maaf atas ketidaknyamanan ini.\n\n"
                    . "Hormat kami,\nTim VexaHost",
                'variables' => ['nama', 'waktu_mulai', 'status', 'keterangan'],
                'is_active' => true,
            ],
            [
                'code' => NotificationTemplate::INFRA_RECOVERED,
                'name' => 'Pemulihan Setelah Insiden',
                'description' => 'Dikirim setelah gangguan infrastruktur selesai ditangani.',
                'subject' => 'Layanan VexaHost Telah Pulih',
                'body' => "Halo {{nama}},\n\n"
                    . "Gangguan yang sebelumnya kami informasikan telah selesai ditangani. "
                    . "Seluruh layanan kini kembali berjalan normal.\n\n"
                    . "Waktu pemulihan: {{waktu_pulih}}\n"
                    . "Total durasi gangguan: {{durasi}}\n\n"
                    . "{{keterangan}}\n\n"
                    . "Bila layanan Anda masih bermasalah, mohon segera hubungi kami melalui tiket bantuan.\n\n"
                    . "Terima kasih atas kesabaran Anda.\n\n"
                    . "Hormat kami,\nTim VexaHost",
                'variables' => ['nama', 'waktu_pulih', 'durasi', 'keterangan'],
                'is_active' => true,
            ],
            [
                'code' => NotificationTemplate::MAINTENANCE_SCHEDULED,
                'name' => 'Maintenance Terjadwal',
                'description' => 'Pemberitahuan maintenance terencana sebelum jadwal berjalan.',
                'subject' => 'Pemberitahuan Maintenance Terjadwal — {{tanggal}}',
                'body' => "Halo {{nama}},\n\n"
                    . "Kami akan melakukan pemeliharaan terjadwal pada infrastruktur VexaHost.\n\n"
                    . "Kegiatan: {{judul}}\n"
                    . "Mulai: {{waktu_mulai}}\n"
                    . "Selesai: {{waktu_selesai}}\n\n"
                    . "{{keterangan}}\n\n"
                    . "Selama periode tersebut sebagian layanan mungkin tidak dapat diakses sementara. "
                    . "Data dan konfigurasi Anda tetap aman.\n\n"
                    . "Hormat kami,\nTim VexaHost",
                'variables' => ['nama', 'judul', 'tanggal', 'waktu_mulai', 'waktu_selesai', 'keterangan'],
                'is_active' => true,
            ],
            [
                'code' => NotificationTemplate::FULFILLMENT_HANDOVER,
                'name' => 'Serah Terima Layanan (Salin ke WhatsApp)',
                'description' => 'Teks seragam untuk menyerahkan akses layanan. Sengaja tanpa kata sandi: '
                    . 'pelanggan melihatnya sendiri di dasbor (tab Akses).',
                'subject' => 'Layanan {{layanan}} Siap Digunakan',
                'body' => "Halo {{nama}},\n\n"
                    . "Layanan Anda di VexaHost sudah siap digunakan.\n\n"
                    . "Layanan: {{layanan}}\n"
                    . "Alamat IP: {{ip}}\n"
                    . "Port SSH: {{port_ssh}}\n"
                    . "Pengguna: {{pengguna}}\n"
                    . "Kata sandi: lihat di dasbor, tab Akses (klik ikon mata)\n"
                    . "Sistem operasi: {{os}}\n"
                    . "{{url_aplikasi}}\n\n"
                    . "Panduan dan detail lengkap dapat dilihat di dasbor: {{dasbor}}\n\n"
                    . "Masa aktif hingga {{masa_aktif}}. Mohon lakukan pencadangan data secara berkala.\n\n"
                    . "Terima kasih,\nTim VexaHost",
                'variables' => ['nama', 'layanan', 'ip', 'port_ssh', 'pengguna', 'os', 'url_aplikasi', 'dasbor', 'masa_aktif'],
                'is_active' => true,
            ],
            [
                'code' => NotificationTemplate::RENEWAL_REMINDER,
                'name' => 'Pengingat Perpanjangan',
                'description' => 'Pengingat jatuh tempo layanan pada H-3, H-1, dan hari jatuh tempo.',
                'subject' => 'Pengingat Perpanjangan Layanan — {{layanan}}',
                'body' => "Halo {{nama}},\n\n"
                    . "Layanan {{layanan}} milik Anda akan jatuh tempo pada {{tanggal_jatuh_tempo}}.\n\n"
                    . "Sisa waktu: {{sisa_hari}}\n"
                    . "Nominal perpanjangan: {{nominal}}\n\n"
                    . "Mohon lakukan perpanjangan sebelum tanggal tersebut agar layanan Anda tidak terhenti.\n\n"
                    . "Hormat kami,\nTim VexaHost",
                'variables' => ['nama', 'layanan', 'tanggal_jatuh_tempo', 'sisa_hari', 'nominal'],
                'is_active' => true,
            ],
        ];
    }
}
