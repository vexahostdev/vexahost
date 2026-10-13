<?php

namespace App\Notifications;

use App\Services\FulfillmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * PHASE 5 - Ringkasan order yang melewati ambang waktu tanggap.
 */
class AdminFulfillmentOverdueNotification extends Notification
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function __construct(
        public Collection $orders,
        public int $slaMinutes
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->orders->count();
        $subject = "[Fulfillment] {$count} order melewati ambang waktu tanggap";

        $rows = $this->orders->map(fn ($order) => [
            'id' => $order->id,
            'package' => $order->vpsSpec?->name ?? 'Paket',
            'customer' => $order->customer?->full_name ?? '-',
            'paid_at' => $order->paid_at?->timezone('Asia/Jakarta')->format('d M H:i') ?? '-',
            'waited' => FulfillmentService::humanMinutes(
                $order->paid_at ? (int) $order->paid_at->diffInMinutes(now()) : null
            ),
        ])->all();

        return $this->newMailMessage()
            ->subject($subject)
            ->view('emails.admin-fulfillment-overdue', [
                'subject' => $subject,
                'rows' => $rows,
                'slaText' => FulfillmentService::humanMinutes($this->slaMinutes),
            ]);
    }
}
