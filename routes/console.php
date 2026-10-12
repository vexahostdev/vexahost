<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ============================================================
// Billing lifecycle scheduler (Poin 3)
// ============================================================
// Semua command dijalankan harian. Waktu dipilih agar tidak tabrakan
// dan agar reminder terkirim di jam kerja Indonesia.

Schedule::command('billing:check-due')
    ->dailyAt('08:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Kirim reminder H-7/H-3/H-1/H+0 ke subscription yang mendekati jatuh tempo.');

Schedule::command('billing:renew')
    ->dailyAt('02:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Generate invoice renewal untuk subscription auto-renew.');

Schedule::command('billing:enter-grace')
    ->dailyAt('03:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Masukkan subscription past due ke grace period.');

Schedule::command('billing:suspend-overdue')
    ->dailyAt('04:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Suspend subscription yang grace period-nya habis.');

Schedule::command('billing:terminate-expired')
    ->dailyAt('05:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Terminate subscription yang suspended > 30 hari.');

// ============================================================
// PHASE 1 - Maintenance terjadwal (TIDAK dijadwalkan)
// ============================================================
// Fitur jadwal maintenance tidak dipakai, jadi maintenance:sync tidak lagi
// berjalan tiap 5 menit agar tidak membebani server. Jendela maintenance
// tetap bisa dimulai/diselesaikan manual dari Admin > Maintenance.
// Aktifkan kembali blok ini bila fitur jadwal otomatis mulai dipakai:
//
// Schedule::command('maintenance:sync')
//     ->everyFiveMinutes()
//     ->timezone('Asia/Jakarta')
//     ->withoutOverlapping()
//     ->onOneServer()
//     ->description('Mulai/akhiri jendela maintenance terjadwal dan kirim pemberitahuannya.');

// ============================================================
// PHASE 3 - Pengingat perpanjangan instance
// ============================================================
// Dijalankan sekali sehari. Idempotensi dijamin indeks unik di
// tabel renewal_reminders, sehingga aman bila terjadi pengulangan.
Schedule::command('renewal:remind')
    ->dailyAt('09:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Kirim pengingat H-3/H-1/H-0 instance dan pindahkan status yang jatuh tempo.');

// ============================================================
// PHASE 5 - Peringatan waktu tanggap fulfillment
// ============================================================
// Diganti pengingat bertahap (WA + email): 30 menit → 1 jam → tiap 2 jam
// sampai order diserahkan. fulfillment:check-sla (sekali, email saja) tidak
// dijadwalkan lagi agar admin tidak menerima surel ganda; command-nya tetap
// ada untuk dijalankan manual.
// Auto-renew di Supplier dimatikan: admin memperpanjang manual setelah pelanggan bayar.
// Pagi: ringkasan lengkap. Siang/sore/malam: hanya yang mendesak (≤ 3 hari).
Schedule::command('supplier:remind-admin --daily')
    ->dailyAt('08:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Ringkasan pagi tugas perpanjangan di Supplier (WA + email).');

Schedule::command('supplier:remind-admin')
    ->cron('0 12,16,20 * * *')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Pengingat VPS yang harus segera diperpanjang di Supplier.');

Schedule::command('fulfillment:remind-admin')
    ->everyFiveMinutes()
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Ingatkan admin (WA + email) order lunas yang belum disetup, bertahap sampai diserahkan.');

// ============================================================
// Provisioning reconciliation (TIDAK dijadwalkan)
// ============================================================
// Server dibeli retail tanpa API supplier, sehingga provider 'manual' tidak
// bisa membaca status mesin. Menjalankan provider:reconcile tiap 15 menit
// hanya menulis ulang setiap baris VPS tanpa manfaat, jadi tidak dijadwalkan.
// Aktifkan kembali bila sudah ada adapter provider yang benar-benar memanggil API:
//
// Schedule::command('provider:reconcile')
//     ->everyFifteenMinutes()
//     ->timezone('Asia/Jakarta')
//     ->withoutOverlapping()
//     ->onOneServer()
//     ->description('Rekonsiliasi status VPS antara database dan provider.');

// ============================================================
// Akun tertaut: perubahan akun yang belum sampai ke VexaHost WA Gateway
// dikirim ulang tiap menit. Percobaan pertamanya berjalan di ujung permintaan
// web; yang ini menangkap sisanya saat WA Gateway sedang deploy — kalau tidak,
// kata sandi yang diganti tepat saat itu tidak pernah sampai.
// ============================================================
Schedule::command('akun-tertaut:kirim')
    ->everyMinute()
    ->withoutOverlapping()
    ->description('Kirim ulang perubahan akun ke VexaHost WA Gateway.');

// ============================================================
// PILAR 3 - Auto-Expire Order pending (TTL 24 Jam)
// ============================================================
Schedule::command('orders:expire-pending')
    ->hourly()
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Batalkan secara otomatis order pending yang melebihi batas waktu pembayaran 24 jam.');

