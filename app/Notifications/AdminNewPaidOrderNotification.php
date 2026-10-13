<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminNewPaidOrderNotification extends Notification
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
        $amount = 'Rp ' . number_format((float) $this->order->amount, 0, ',', '.');

        $label = $this->order->isRenewal() ? 'PERPANJANGAN LUNAS' : 'ORDER LUNAS — PERLU SETUP';

        return $this->newMailMessage()
            ->subject("💰 [{$label}] Order #{$this->order->id} — {$spec} ({$amount})")
            ->view('emails.admin-new-order', [
                'order' => $this->order,
            ]);
    }
}
