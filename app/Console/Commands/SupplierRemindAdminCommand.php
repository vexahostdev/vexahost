<?php

namespace App\Console\Commands;

use App\Services\AdminAlertService;
use App\Services\SupplierCoverageService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Tugas Supplier untuk admin (auto-renew di Supplier DIMATIKAN).
 *
 * Tiap jadwal (08/12/16/20 WIB):
 *   - PERPANJANG SEGERA: pelanggan sudah bayar melewati masa aktif di Supplier,
 *     dan masa aktif Supplier habis ≤ 3 hari lagi.
 * Tambahan pada ringkasan pagi (--daily):
 *   - BELUM TERTUTUP : pelanggan sudah bayar, Supplier belum diperpanjang (belum mendesak).
 *   - PERLU KEPUTUSAN: Supplier habis ≤ 2 hari lagi dan pelanggan BELUM bayar.
 *                      Jangan perpanjang di Supplier kecuali pelanggan membayar.
 *   - TANPA CATATAN  : jumlah VPS yang belum punya catatan pembelian Supplier.
 *
 * Tidak mengirim apa pun bila tidak ada tugas.
 */
class SupplierRemindAdminCommand extends Command
{
    public const DECISION_DAYS = 2;

    protected $signature = 'supplier:remind-admin
        {--daily : Ringkasan pagi lengkap (termasuk belum tertutup, keputusan, dan tanpa catatan)}
        {--dry-run : Tampilkan isi pesan tanpa mengirim}';

    protected $description = 'Ingatkan admin (WA + email) tugas perpanjangan di Supplier.';

    public function handle(SupplierCoverageService $coverage, AdminAlertService $alerts): int
    {
        $rows = $coverage->rows();
        $daily = (bool) $this->option('daily');

        $urgent = $rows->where('status', SupplierCoverageService::STATUS_URGENT);
        $gap = $daily ? $rows->where('status', SupplierCoverageService::STATUS_GAP) : collect();
        $decision = $daily
            ? $rows->filter(fn (array $r) => $r['status'] === SupplierCoverageService::STATUS_COVERED
                && $r['supplier_expires_at']
                && $r['supplier_expires_at']->isFuture()
                && $r['supplier_expires_at']->lessThanOrEqualTo(now()->addDays(self::DECISION_DAYS)))
            : collect();
        $missing = $daily ? $rows->where('status', SupplierCoverageService::STATUS_MISSING)->count() : 0;

        if ($urgent->isEmpty() && $gap->isEmpty() && $decision->isEmpty() && $missing === 0) {
            $this->info('Tidak ada tugas Supplier.');

            return self::SUCCESS;
        }

        $lines = ['📦 *TUGAS SUPPLIER' . ($daily ? ' — RINGKASAN PAGI' : '') . '*', ''];

        $this->section($lines, '🔴 *PERPANJANG SEGERA* (pelanggan sudah bayar, Supplier hampir habis)', $urgent);
        $this->section($lines, '🟡 *BELUM TERTUTUP* (pelanggan sudah bayar, Supplier belum diperpanjang)', $gap);
        $this->section($lines, '⚪ *PERLU KEPUTUSAN* (Supplier habis ≤ ' . self::DECISION_DAYS . ' hari, pelanggan BELUM bayar — jangan perpanjang kecuali pelanggan membayar)', $decision);

        if ($missing > 0) {
            $lines[] = "ℹ️ {$missing} VPS belum punya catatan pembelian Supplier — lengkapi agar pengingat akurat.";
            $lines[] = '';
        }

        $lines[] = 'Catat pembelian setelah memperpanjang: ' . rtrim((string) config('app.url'), '/') . '/admin/supplier-purchases';
        $text = implode("\n", $lines);

        if ($this->option('dry-run')) {
            $this->line($text);

            return self::SUCCESS;
        }

        $subject = '[Supplier] ' . ($urgent->count() ? "{$urgent->count()} VPS harus diperpanjang segera" : 'Ringkasan tugas perpanjangan');

        if (!$alerts->digest($subject, $text)) {
            $this->error('Tugas Supplier gagal dikirim lewat WA maupun email.');

            return self::FAILURE;
        }

        $this->info('Tugas Supplier terkirim.');

        return self::SUCCESS;
    }

    private function section(array &$lines, string $title, Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $lines[] = $title;

        foreach ($rows->values() as $i => $row) {
            $instance = $row['instance'];
            $lines[] = ($i + 1) . '. ' . ($instance->hostname ?? ('VPS #' . $instance->id))
                . ' · ' . ($instance->customer?->full_name ?? '-')
                . ' · Supplier s/d ' . $this->date($row['supplier_expires_at'])
                . ' · Pelanggan s/d ' . $this->date($row['customer_expires_at']);
        }

        $lines[] = '';
    }

    private function date($date): string
    {
        return $date ? $date->copy()->timezone('Asia/Jakarta')->format('d M H:i') : '-';
    }
}
