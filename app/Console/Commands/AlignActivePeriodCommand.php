<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\VpsInstance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rapikan masa aktif VPS yang dihitung dari waktu aktivasi admin, padahal
 * seharusnya dari waktu pelunasan (bug sebelum 13 Okt 2026).
 *
 * Contoh: lunas 12 Okt 10:00, disetup 13 Okt 08:00 → expires 13 Nov 08:00.
 * Setelah dirapikan → masa aktif mulai 12 Okt 10:00, expires 12 Nov 10:00.
 *
 * Default hanya MEMBACA. --apply untuk menerapkan.
 * Yang dilewati demi keamanan: VPS yang sudah pernah diperpanjang, sudah
 * terminated, selisihnya di bawah --min-minutes, atau setupnya lewat SLA
 * 24 jam (masa aktif sejak diserahkan = kompensasi, pasal 5.5).
 */
class AlignActivePeriodCommand extends Command
{
    protected $signature = 'vps:align-active-period
        {--order= : Hanya order tertentu (ID order)}
        {--min-minutes=60 : Selisih minimal antara lunas dan aktivasi agar dirapikan}
        {--apply : Terapkan perubahan}';

    protected $description = 'Samakan awal masa aktif VPS dengan waktu pelunasan (default: hanya baca).';

    public function handle(): int
    {
        $minMinutes = max(1, (int) $this->option('min-minutes'));

        $query = VpsInstance::with('order.customer')
            ->whereNotNull('order_id')
            ->whereNotNull('starts_at')
            ->whereNotNull('expires_at')
            ->where('status', '!=', 'terminated')
            ->whereHas('order', fn ($q) => $q->whereNotNull('paid_at')
                ->where(fn ($q) => $q->whereNull('order_type')->orWhere('order_type', '!=', 'renewal')));

        if ($this->option('order')) {
            $query->where('order_id', (int) $this->option('order'));
        }

        $rows = $query->get()->filter(function (VpsInstance $vps) use ($minMinutes) {
            $paidAt = $vps->order->paid_at;

            $lateMinutes = -$vps->starts_at->diffInMinutes($paidAt, false);

            if ($lateMinutes < $minMinutes) {
                return false; // selisih kecil / sudah benar
            }

            // Lewat SLA setup: masa aktif sejak diserahkan adalah kompensasi yang sah.
            if ($lateMinutes > Order::SETUP_SLA_HOURS * 60) {
                return false;
            }

            // Pernah diperpanjang → tanggal sudah bergeser karena renewal, jangan disentuh.
            return !Order::where('vps_instance_id', $vps->id)->whereNotNull('paid_at')->exists();
        })->values();

        if ($rows->isEmpty()) {
            $this->info('Aman: tidak ada VPS yang masa aktifnya perlu dirapikan.');

            return self::SUCCESS;
        }

        $fmt = fn ($d) => $d?->copy()->timezone('Asia/Jakarta')->format('d M Y H:i');

        $this->warn("Ditemukan {$rows->count()} VPS yang masa aktifnya dihitung dari waktu aktivasi:");
        $this->table(
            ['Order', 'Pelanggan', 'VPS', 'Lunas (WIB)', 'Aktivasi (WIB)', 'Expires sekarang', 'Expires seharusnya'],
            $rows->map(function (VpsInstance $vps) use ($fmt) {
                $shift = (int) $vps->order->paid_at->diffInSeconds($vps->starts_at);

                return [
                    '#' . $vps->order_id,
                    $vps->order->customer?->full_name ?? '-',
                    $vps->hostname ?? ('#' . $vps->id),
                    $fmt($vps->order->paid_at),
                    $fmt($vps->starts_at),
                    $fmt($vps->expires_at),
                    $fmt($vps->expires_at->copy()->subSeconds($shift)),
                ];
            })->all()
        );

        if (!$this->option('apply')) {
            $this->line('Jalankan ulang dengan --apply untuk menerapkan (bisa dibatasi --order=ID).');

            return self::SUCCESS;
        }

        foreach ($rows as $vps) {
            DB::transaction(function () use ($vps, $fmt) {
                $vps = VpsInstance::whereKey($vps->id)->lockForUpdate()->with('order')->first();
                $order = $vps->order;
                $paidAt = $order->paid_at->copy();
                $shift = (int) $paidAt->diffInSeconds($vps->starts_at);

                if ($shift <= 0) {
                    return;
                }

                $oldExpires = $vps->expires_at->copy();

                $vps->starts_at = $paidAt;
                $vps->expires_at = $vps->expires_at->copy()->subSeconds($shift);
                if ($vps->grace_period_ends_at) {
                    $vps->grace_period_ends_at = $vps->grace_period_ends_at->copy()->subSeconds($shift);
                }
                $vps->save();

                $order->starts_at = $paidAt;
                if ($order->expires_at) {
                    $order->expires_at = $order->expires_at->copy()->subSeconds($shift);
                }
                if ($order->grace_period_ends_at) {
                    $order->grace_period_ends_at = $order->grace_period_ends_at->copy()->subSeconds($shift);
                }
                $order->save();

                $vps->logActivity(
                    action: 'active_period_aligned',
                    description: 'Masa aktif disamakan dengan waktu pelunasan (' . $fmt($paidAt) . ' WIB). '
                        . 'Jatuh tempo: ' . $fmt($oldExpires) . ' → ' . $fmt($vps->expires_at) . ' WIB.',
                    status: 'completed'
                );

                $this->info("✓ Order #{$order->id}: jatuh tempo " . $fmt($vps->expires_at) . ' WIB');
            });
        }

        return self::SUCCESS;
    }
}
