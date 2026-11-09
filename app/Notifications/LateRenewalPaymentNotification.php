<?php

namespace App\Notifications;

use App\Channels\WhatsAppChannel;
use App\Models\Order;
use App\Models\VpsInstance;
use App\Services\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pembayaran perpanjangan masuk setelah batas akhir, sehingga tidak diproses
 * dan dananya akan dikembalikan.
 */
class LateRenewalPaymentNotification extends Notification
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function __construct(
        public Order $order,
        public VpsInstance $instance,
        public $deadline
    ) {
    }

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
        $subject = 'Pembayaran Perpanjangan Tidak Dapat Diproses — ' . $this->serviceName();

        return $this->newMailMessage()
            ->subject($subject)
            ->view('emails.late-renewal-payment', [
                'user' => $notifiable,
                'subject' => $subject,
                'bodyText' => $this->body(),
                'serviceName' => $this->serviceName(),
                'amountText' => $this->amount(),
                'deadlineText' => $this->deadlineText(),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'vps_instance_id' => $this->instance->id,
            'stage' => 'late_payment',
        ];
    }

    public function toWhatsApp(object $notifiable): ?WhatsAppMessage
    {
        return WhatsAppMessage::create(implode("\n", [
            'Halo *' . ($notifiable->full_name ?? 'Pelanggan VexaHost') . '*,',
            '',
            '⚠️ *Pembayaran Perpanjangan Tidak Dapat Diproses*',
            $this->body(),
            '',
            '• *Server:* `' . $this->serviceName() . '`',
            '• *Nominal:* ' . $this->amount(),
            '• *Batas Akhir Perpanjangan:* ' . $this->deadlineText(),
            '',
            '_VexaHost Cloud Solutions_',
        ]));
    }

    private function body(): string
    {
        return 'Pembayaran perpanjangan layanan ' . $this->serviceName() . ' kami terima setelah batas akhir '
            . $this->deadlineText() . ', sehingga perpanjangan tidak dapat diproses dan layanan tidak dapat dipulihkan. '
            . 'Dana Anda akan dikembalikan oleh tim kami ke rekening atau dompet digital asal.';
    }

    private function serviceName(): string
    {
        return $this->instance->hostname ?: 'Layanan Cloud';
    }

    private function amount(): string
    {
        return 'Rp ' . number_format((float) $this->order->amount, 0, ',', '.');
    }

    private function deadlineText(): string
    {
        return $this->deadline
            ? $this->deadline->copy()->timezone('Asia/Jakarta')->format('d M Y, H:i') . ' WIB'
            : '-';
    }
}
