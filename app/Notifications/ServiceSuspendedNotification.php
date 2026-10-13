<?php

namespace App\Notifications;

use App\Channels\WhatsAppChannel;
use App\Models\Subscription;
use App\Services\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ServiceSuspendedNotification extends Notification
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function __construct(
        public Subscription $subscription
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
        $spec = $this->subscription->vpsSpec?->name ?? 'VPS';

        return $this->newMailMessage()
            ->subject("Layanan ditangguhkan (overdue) — {$spec}")
            ->view('emails.service-suspended', [
                'user' => $notifiable,
                'subscription' => $this->subscription,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'status' => 'suspended',
            'amount' => (float) $this->subscription->unit_amount,
        ];
    }

    public function toWhatsApp(object $notifiable): ?WhatsAppMessage
    {
        $this->subscription->loadMissing('vpsSpec');
        $appUrl = rtrim(config('app.url', 'https://vexahostcloud.my.id'), '/');
        $namaPelanggan = $notifiable->full_name ?? 'Pelanggan VexaHost';
        $layanan = $this->subscription->vpsSpec?->name ?? 'Cloud VPS';
        $nominal = number_format((float) $this->subscription->unit_amount, 0, ',', '.');
        $billingUrl = "{$appUrl}/dashboard/billing";

        $message = implode("\n", [
            "Halo *{$namaPelanggan}*,",
            '',
            '🛑 *PEMBERITAHUAN PENANGGUHAN LAYANAN*',
            'Layanan server VPS Anda telah ditangguhkan sementara karena tagihan melewati tanggal jatuh tempo.',
            '',
            "• *Layanan:* {$layanan}",
            "• *Tagihan Tertunggak:* Rp {$nominal}",
            "• *Status:* Suspended",
            '',
            'ℹ️ _Seluruh data server Anda masih aman tersimpan. Lunasi tagihan sekarang untuk mengaktifkan kembali server secara otomatis:_',
            $billingUrl,
        ]);

        return WhatsAppMessage::create($message);
    }
}
