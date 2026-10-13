<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Notifications\AdminFulfillmentOverdueNotification;
use App\Services\FulfillmentService;
use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * PHASE 5 - Peringatan order yang melewati ambang waktu tanggap.
 *
 * Dijalankan tiap jam. Mengirim SATU surel ringkasan berisi seluruh order
 * yang baru saja melewati ambang, bukan satu surel per order.
 *
 * Idempoten: orders.sla_alerted_at dicatat setelah surel terkirim, sehingga
 * satu order hanya diperingatkan sekali meskipun command berjalan berulang.
 */
class FulfillmentSlaCheckCommand extends Command
{
    protected $signature = 'fulfillment:check-sla
        {--dry-run : Hanya tampilkan order yang terlambat, tidak mengirim apa pun}';

    protected $description = 'Peringatkan admin tentang order dibayar yang belum diserahkan melewati ambang waktu tanggap.';

    public function handle(FulfillmentService $fulfillment, SettingsService $settings): int
    {
        $threshold = now()->subMinutes($fulfillment->slaMinutes());

        $orders = Order::with(['customer', 'vpsSpec'])
            ->whereIn('status', ['paid', 'failed', 'provisioning'])
            ->whereNotNull('paid_at')
            ->where('paid_at', '<=', $threshold)
            ->whereNull('sla_alerted_at')
            ->orderBy('paid_at')
            ->get();

        $this->info("Order melewati ambang ({$fulfillment->slaMinutes()} menit): {$orders->count()}");

        if ($orders->isEmpty()) {
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            foreach ($orders as $order) {
                $this->line("  → [dry] order #{$order->id} menunggu "
                    . FulfillmentService::humanMinutes($fulfillment->minutesSincePaid($order)));
            }

            return self::SUCCESS;
        }

        $emails = \App\Support\MailIdentity::adminRecipients();

        if (empty($emails)) {
            $this->warn('Surel penerima admin belum diatur. Peringatan tidak dikirim.');

            return self::SUCCESS;
        }

        $sentAny = false;
        foreach ($emails as $email) {
            try {
                Notification::route('mail', $email)
                    ->notify(new AdminFulfillmentOverdueNotification($orders, $fulfillment->slaMinutes()));
                $this->info("Peringatan terkirim ke {$email}.");
                $sentAny = true;
            } catch (\Throwable $e) {
                Log::error('fulfillment.sla_alert_failed', ['recipient' => $email, 'error' => $e->getMessage()]);
                $this->error("Peringatan gagal dikirim ke {$email}: " . $e->getMessage());
            }
        }

        if (!$sentAny) {
            // sla_alerted_at tidak diisi agar dicoba lagi pada jam berikutnya.
            return self::FAILURE;
        }

        Order::whereIn('id', $orders->pluck('id'))->update(['sla_alerted_at' => now()]);

        return self::SUCCESS;
    }
}
