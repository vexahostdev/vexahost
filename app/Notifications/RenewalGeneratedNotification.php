<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RenewalGeneratedNotification extends Notification
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function __construct(
        public Subscription $subscription,
        public Invoice $invoice
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $spec = $this->subscription->vpsSpec?->name ?? 'VPS';
        $amount = 'Rp ' . number_format((float) $this->invoice->amount, 0, ',', '.');

        return $this->newMailMessage()
            ->subject("Invoice perpanjangan {$spec} — {$this->invoice->invoice_number}")
            ->greeting("Halo {$notifiable->full_name},")
            ->line("Invoice perpanjangan {$spec} telah dibuat.")
            ->line("Nomor invoice: {$this->invoice->invoice_number}")
            ->line("Nominal: {$amount}")
            ->line("Batas pembayaran: " . ($this->invoice->due_at ? $this->invoice->due_at->timezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB' : '-'))
            ->action('Bayar Sekarang', url('/dashboard/billing'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'amount' => (float) $this->invoice->amount,
        ];
    }
}
