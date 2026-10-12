<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Template serah terima (fulfillment_handover) tidak lagi menulis "root" mati:
 * diganti variabel {{pengguna}} dari vps_instances.ssh_username.
 *
 * Aman untuk template yang sudah diedit admin: hanya teks yang persis cocok
 * yang diganti, sisanya dibiarkan. Variabel 'pengguna' ditambahkan ke daftar.
 */
return new class extends Migration
{
    public function up(): void
    {
        $row = DB::table('notification_templates')->where('code', 'fulfillment_handover')->first();

        if (!$row) {
            return;
        }

        $body = str_replace(
            ['Pengguna: root', 'Kata sandi: sesuai yang Anda buat saat pemesanan'],
            ['Pengguna: {{pengguna}}', 'Kata sandi: lihat di dasbor, tab Akses (klik ikon mata)'],
            (string) $row->body
        );

        $variables = json_decode((string) $row->variables, true) ?: [];
        if (!in_array('pengguna', $variables, true)) {
            $variables[] = 'pengguna';
        }

        DB::table('notification_templates')->where('id', $row->id)->update([
            'body' => $body,
            'variables' => json_encode(array_values($variables)),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Tidak dikembalikan: {{pengguna}} tetap dirender 'root' untuk VPS lama.
    }
};
