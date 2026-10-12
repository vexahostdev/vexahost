<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\AdminAlertService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pengingat bertahap ke admin (WA + email) untuk order lunas yang belum diserahkan.
 *
 * Jadwal per order, dihitung dari waktu bayar:
 *   pengingat ke-1 : 30 menit
 *   pengingat ke-2 : 60 menit
 *   berikutnya     : tiap 2 jam sejak pengingat terakhir
 * sampai status order bukan lagi paid/provisioning/failed (sudah diserahkan/dibatalkan).
 *
 * Dijalankan tiap 5 menit. Semua order yang jatuh tempo digabung dalam SATU
 * pesan agar tidak membanjiri WA. Order yang dibayar lebih dari 7 hari lalu
 * tidak diingatkan lagi (data lama/uji coba tidak ikut mengirim pesan).
 */
class FulfillmentRemindAdminCommand extends Command
{
    public const FIRST_AFTER_MINUTES = 30;
    public const SECOND_AFTER_MINUTES = 60;
    public const REPEAT_EVERY_MINUTES = 120;
    public const MAX_AGE_DAYS = 7;

    protected $signature = 'fulfillment:remind-admin
        {--dry-run : Tampilkan order yang akan diingatkan tanpa mengirim apa pun}';

    protected $description = 'Ingatkan admin (WA + email) tentang order lunas yang belum disetup, bertahap sampai diserahkan.';

    public function handle(AdminAlertService $alerts): int
    {
        $now = now();

        $orders = Order::with(['customer', 'vpsSpec'])
            ->whereIn('status', ['paid', 'provisioning', 'failed'])
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $now->copy()->subDays(self::MAX_AGE_DAYS))
            // Perpanjangan tidak perlu setup (lihat Order::isRenewal()).
            ->where(fn (Builder $q) => $q->whereNull('order_type')->orWhere('order_type', '!=', 'renewal'))
            ->whereNull('vps_instance_id')
            ->where(function (Builder $q) use ($now) {
                $q->where(fn (Builder $q) => $q->where('admin_reminder_count', 0)
                    ->where('paid_at', '<=', $now->copy()->subMinutes(self::FIRST_AFTER_MINUTES)))
                  ->orWhere(fn (Builder $q) => $q->where('admin_reminder_count', 1)
                    ->where('paid_at', '<=', $now->copy()->subMinutes(self::SECOND_AFTER_MINUTES)))
                  ->orWhere(fn (Builder $q) => $q->where('admin_reminder_count', '>=', 2)
                    ->where(fn (Builder $q) => $q->whereNull('admin_reminded_at')
                        ->orWhere('admin_reminded_at', '<=', $now->copy()->subMinutes(self::REPEAT_EVERY_MINUTES))));
            })
            ->orderBy('paid_at')
            ->get();

        $this->info("Order lunas belum disetup yang perlu diingatkan: {$orders->count()}");

        if ($orders->isEmpty()) {
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            foreach ($orders as $order) {
                $this->line("  → [dry] order #{$order->id} (pengingat ke-" . ($order->admin_reminder_count + 1) . ')');
            }

            return self::SUCCESS;
        }

        if (!$alerts->fulfillmentReminder($orders)) {
            // Tidak ada kanal yang berhasil: jangan tandai, dicoba lagi 5 menit berikutnya.
            $this->error('Pengingat gagal dikirim lewat WA maupun email. Dicoba lagi pada jadwal berikutnya.');

            return self::FAILURE;
        }

        Order::whereIn('id', $orders->pluck('id'))->update([
            'admin_reminder_count' => \Illuminate\Support\Facades\DB::raw('admin_reminder_count + 1'),
            'admin_reminded_at' => $now,
            'sla_alerted_at' => \Illuminate\Support\Facades\DB::raw('COALESCE(sla_alerted_at, CURRENT_TIMESTAMP)'),
        ]);

        $this->info('Pengingat terkirim.');

        return self::SUCCESS;
    }
}
