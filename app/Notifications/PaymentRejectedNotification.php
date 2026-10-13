<?php

namespace App\Notifications;

use App\Channels\WhatsAppChannel;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentRejectedNotification extends Notification
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function __construct(
        public Order $order,
        public ?Invoice $invoice = null,
        public string $reason = 'Mutasi pembayaran tidak ditemukan atau tidak sesuai'
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['mail', 'database'];

        if (config('whatsapp.enabled') && ! empty($notifiable->phone)) {
            $channels[] = WhatsAppChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invNo = $this->invoice?->invoice_number ?? ('INV-' . $this->order->id);

        return $this->newMailMessage('support')
            ->subject("Pembayaran Tidak Terverifikasi — {$invNo}")
            ->view('emails.payment-rejected', [
                'order' => $this->order,
                'invoice' => $this->invoice,
                'reason' => $this->reason,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'invoice_id' => $this->invoice?->id,
            'amount' => (float) $this->order->amount,
            'reason' => $this->reason,
        ];
    }

    public function toWhatsApp(object $notifiable): ?WhatsAppMessage
    {
        $appUrl = rtrim(config('app.url', 'https://vexahostcloud.my.id'), '/');
        $namaPelanggan = $notifiable->full_name ?? 'Pelanggan VexaHost';
        $bayarUrl = "{$appUrl}/order/payment/{$this->order->id}";

        $message = implode("\n", [
            "Halo *{$namaPelanggan}*,",
            '',
            '❌ *PEMBAYARAN DIBATALKAN / KADALUARSA*',
            "Transaksi pembayaran untuk pesanan #{$this->order->id} belum terkonfirmasi oleh gateway atau telah dibatalkan.",
            '',
            "• *Alasan:* {$this->reason}",
            '',
            'Silakan periksa detail pesanan Anda melalui tautan berikut:',
            $bayarUrl,
            '',
            '_Jika ada kendala, tim VexaHost siap membantu Anda._',
        ]);

        return WhatsAppMessage::create($message);
    }
}
