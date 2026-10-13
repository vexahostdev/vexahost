<?php

namespace App\Notifications;

use App\Channels\WhatsAppChannel;
use App\Models\Subscription;
use App\Services\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ServiceTerminatedNotification extends Notification
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
            ->subject("Layanan dihapus permanen — {$spec}")
            ->view('emails.service-terminated', [
                'user' => $notifiable,
                'subscription' => $this->subscription,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'status' => 'terminated',
        ];
    }

    public function toWhatsApp(object $notifiable): ?WhatsAppMessage
    {
        $this->subscription->loadMissing('vpsSpec');
        $appUrl = rtrim(config('app.url', 'https://vexahostcloud.my.id'), '/');
        $namaPelanggan = $notifiable->full_name ?? 'Pelanggan VexaHost';
        $layanan = $this->subscription->vpsSpec?->name ?? 'Cloud VPS';

        $message = implode("\n", [
            "Halo *{$namaPelanggan}*,",
            '',
            '⚠️ *PEMBERITAHUAN TERMINASI LAYANAN*',
            "Layanan server *{$layanan}* Anda telah dihentikan secara permanen karena telah melewati masa tenggang pembayaran.",
            '',
            'Jika Anda ingin memesan layanan server baru, silakan kunjungi:',
            "{$appUrl}/#pricing",
            '',
            '_VexaHost Cloud Solutions_',
        ]);

        return WhatsAppMessage::create($message);
    }
}
