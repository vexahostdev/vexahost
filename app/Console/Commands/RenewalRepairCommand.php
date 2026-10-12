<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\RenewalService;
use Illuminate\Console\Command;

/**
 * Cari (dan opsional perbaiki) order perpanjangan yang SUDAH LUNAS tetapi
 * masa aktif VPS-nya belum ditambahkan.
 *
 * Penyebab historis: halaman order lebih dulu menandai lunas lewat cek status
 * Xendit, lalu webhook ditolak (paid → paid) sehingga perpanjangan tidak jalan.
 *
 * Default hanya MEMBACA. Gunakan --apply untuk menerapkan. Aman diulang:
 * RenewalService menandai orders.renewal_applied_at sehingga tidak dobel.
 */
class RenewalRepairCommand extends Command
{
    protected $signature = 'renewal:repair
        {--apply : Terapkan perpanjangan untuk order yang ditemukan}';

    protected $description = 'Periksa perpanjangan yang sudah lunas tetapi masa aktifnya belum ditambah (default: hanya baca).';

    public function handle(RenewalService $renewals): int
    {
        $orders = Order::with(['customer', 'vpsInstance'])
            ->where(fn ($q) => $q->where('order_type', 'renewal')->orWhereNotNull('vps_instance_id'))
            ->whereNotNull('paid_at')
            ->whereNull('renewal_applied_at')
            ->whereIn('status', ['paid'])
            ->orderBy('paid_at')
            ->get();

        if ($orders->isEmpty()) {
            $this->info('Aman: tidak ada perpanjangan lunas yang tertunda.');

            return self::SUCCESS;
        }

        $this->warn("Ditemukan {$orders->count()} perpanjangan lunas yang belum diterapkan:");
        $this->table(
            ['Order', 'Pelanggan', 'VPS', 'Dibayar (WIB)', 'Aktif s/d saat ini', 'Siklus'],
            $orders->map(fn (Order $o) => [
                "#{$o->id}",
                $o->customer?->full_name ?? '-',
                $o->resolved_instance?->hostname ?? '-',
                $o->paid_at?->timezone('Asia/Jakarta')->format('d M Y H:i'),
                $o->resolved_instance?->expires_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '-',
                $o->billing_cycle ?? '-',
            ])->all()
        );

        if (!$this->option('apply')) {
            $this->line('Jalankan ulang dengan --apply untuk menerapkan perpanjangan di atas.');

            return self::SUCCESS;
        }

        foreach ($orders as $order) {
            try {
                $instance = $renewals->handleRenewalPayment($order);
                $this->info($instance
                    ? "✓ Order #{$order->id}: aktif hingga " . $instance->expires_at?->timezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB'
                    : "✗ Order #{$order->id}: instance VPS tidak ditemukan, cek manual.");
            } catch (\Throwable $e) {
                $this->error("✗ Order #{$order->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
