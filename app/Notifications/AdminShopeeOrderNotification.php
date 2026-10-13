<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminShopeeOrderNotification extends Notification
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function __construct(
        public Order $order
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $spec = $this->order->vpsSpec->name ?? 'VPS';
        $shopeeId = $this->order->shopee_order_id ?? 'N/A';

        return $this->newMailMessage()
            ->subject("[SHOPEE] Pesanan Baru #{$shopeeId} — {$spec}")
            ->view('emails.admin-shopee-order', [
                'order' => $this->order,
            ]);
    }
}
