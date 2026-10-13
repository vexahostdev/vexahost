<?php

namespace App\Notifications;

use App\Channels\WhatsAppChannel;
use App\Models\Subscription;
use App\Services\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BillingReminderNotification extends Notification
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function __construct(
        public Subscription $subscription,
        public int $daysUntil
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
        $sub = $this->subscription;
        $spec = $sub->vpsSpec?->name ?? 'Cloud VPS';
        $due = $sub->next_billing_at?->timezone('Asia/Jakarta')->format('d M Y') ?? '-';

        $subjectText = $this->daysUntil === 0
            ? "Jatuh tempo hari ini — {$spec}"
            : "H-{$this->daysUntil}: Pengingat Jatuh Tempo {$spec}";

        $headingText = $this->daysUntil === 0
            ? "Tagihan Jatuh Tempo Hari Ini"
            : "Pengingat Perpanjangan Layanan (H-{$this->daysUntil})";

        $bodyText = $this->daysUntil === 0
            ? "Langganan {$spec} Anda jatuh tempo HARI INI ({$due})."
            : "Langganan {$spec} Anda akan jatuh tempo dalam {$this->daysUntil} hari ({$due}).";

        return $this->newMailMessage()
            ->subject($subjectText)
            ->view('emails.billing-reminder', [
                'user' => $notifiable,
                'subscription' => $this->subscription,
                'daysUntil' => $this->daysUntil,
                'subjectText' => $subjectText,
                'headingText' => $headingText,
                'bodyText' => $bodyText,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'days_until' => $this->daysUntil,
            'amount' => (float) $this->subscription->unit_amount,
            'due_at' => $this->subscription->next_billing_at?->toIso8601String(),
        ];
    }

    public function toWhatsApp(object $notifiable): ?WhatsAppMessage
    {
        $this->subscription->loadMissing(['vpsSpec']);
        $appUrl = rtrim(config('app.url', 'https://vexahostcloud.my.id'), '/');
        $namaPelanggan = $notifiable->full_name ?? 'Pelanggan VexaHost';
        $layanan = $this->subscription->vpsSpec?->name ?? 'Cloud VPS';
        $nominal = number_format((float) $this->subscription->unit_amount, 0, ',', '.');
        $batasWaktu = $this->subscription->next_billing_at?->timezone('Asia/Jakarta')->translatedFormat('d F Y') ?? '-';
        $billingUrl = "{$appUrl}/dashboard/billing";

        $statusWaktu = $this->daysUntil === 0
            ? '*Jatuh Tempo Hari Ini!*'
            : "Akan jatuh tempo dalam *{$this->daysUntil} hari lagi*.";

        $message = implode("\n", [
            "Halo *{$namaPelanggan}*,",
            '',
            '⏰ *PENGINGAT JATUH TEMPO TAGIHAN*',
            "Tagihan perpanjangan layanan {$statusWaktu}",
            '',
            "• *Layanan:* {$layanan}",
            "• *Nominal:* Rp {$nominal}",
            "• *Batas Waktu:* {$batasWaktu}",
            '',
            'Untuk menghindari penghentian layanan otomatis, silakan selesaikan pembayaran di:',
            $billingUrl,
            '',
            '_VexaHost Cloud Solutions_',
        ]);

        return WhatsAppMessage::create($message);
    }
}
