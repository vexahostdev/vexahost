<?php

namespace App\Notifications;

use App\Channels\WhatsAppChannel;
use App\Models\RenewalReminder;
use App\Models\VpsInstance;
use App\Services\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * PHASE 3 - Pengingat perpanjangan layanan ke pelanggan.
 *
 * Nada pesan berbeda per tahap. Tahap akhir tenggang menyatakan dengan tegas
 * bahwa data sudah tidak dapat dipulihkan, karena di sisi supplier memang
 * sudah terhapus permanen.
 */
class InstanceRenewalReminderNotification extends Notification
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function __construct(
        public VpsInstance $instance,
        public string $stage,
        public int $daysUntil,
        public int $graceDays
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
        $name = $this->instance->hostname ?: 'Layanan Cloud';
        $due = $this->instance->expires_at?->timezone('Asia/Jakarta')->format('d M Y') ?? '-';
        $graceEnd = $this->instance->grace_period_ends_at?->timezone('Asia/Jakarta')->format('d M Y') ?? '-';

        [$subject, $badge, $heading, $body] = $this->content($name, $due, $graceEnd);

        return $this->newMailMessage()
            ->subject($subject)
            ->view('emails.renewal-reminder', [
                'user' => $notifiable,
                'instance' => $this->instance,
                'subject' => $subject,
                'badgeText' => $badge,
                'headingText' => $heading,
                'bodyText' => $body,
                'dueText' => $due,
                'graceEndText' => $graceEnd,
                'stage' => $this->stage,
                'isTerminal' => $this->stage === RenewalReminder::STAGE_GRACE_ENDED,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'vps_instance_id' => $this->instance->id,
            'stage' => $this->stage,
            'days_until' => $this->daysUntil,
            'expires_at' => $this->instance->expires_at?->toIso8601String(),
        ];
    }

    /**
     * Subjek, lencana, judul, dan isi untuk setiap tahap.
     */
    protected function content(string $name, string $due, string $graceEnd): array
    {
        return match ($this->stage) {
            RenewalReminder::STAGE_H7 => [
                "H-7: Pengingat Perpanjangan {$name}",
                'PENGINGAT PERPANJANGAN',
                'Layanan Anda Jatuh Tempo 7 Hari Lagi',
                "Layanan {$name} akan jatuh tempo pada {$due}. Perpanjang lebih awal agar layanan "
                    . 'tetap berjalan tanpa jeda dan data Anda aman.',
            ],
            RenewalReminder::STAGE_H3 => [
                "H-3: Pengingat Perpanjangan {$name}",
                'PENGINGAT PERPANJANGAN',
                'Layanan Anda Jatuh Tempo 3 Hari Lagi',
                "Layanan {$name} akan jatuh tempo pada {$due}. Mohon lakukan perpanjangan "
                    . 'sebelum tanggal tersebut agar layanan Anda tetap berjalan tanpa terhenti.',
            ],
            RenewalReminder::STAGE_H1 => [
                "H-1: Besok Jatuh Tempo — {$name}",
                'PERINGATAN TERAKHIR',
                'Besok Layanan Anda Jatuh Tempo',
                "Layanan {$name} jatuh tempo besok, {$due}. Ini adalah pengingat terakhir "
                    . 'sebelum layanan memasuki masa suspensi.',
            ],
            RenewalReminder::STAGE_H0 => [
                "Layanan Disuspend — {$name}",
                'LAYANAN DISUSPEND',
                'Masa Aktif Layanan Telah Berakhir',
                "Masa aktif layanan {$name} berakhir pada {$due} dan layanan kini disuspend. "
                    . "Anda masih dapat memulihkannya hingga {$graceEnd} ({$this->graceDays} hari). "
                    . 'Setelah tanggal tersebut, seluruh data akan dihapus permanen dan tidak dapat dipulihkan.',
            ],
            RenewalReminder::STAGE_GRACE_ENDED => [
                "Layanan Diterminasi — {$name}",
                'LAYANAN DITERMINASI',
                'Masa Tenggang Telah Berakhir',
                "Masa tenggang layanan {$name} berakhir pada {$graceEnd} tanpa perpanjangan, "
                    . 'sehingga layanan telah diterminasi dan seluruh datanya dihapus permanen. '
                    . 'Data yang sudah terhapus tidak dapat kami pulihkan. '
                    . 'Untuk melanjutkan, silakan lakukan pemesanan layanan baru.',
            ],
            default => [
                "Pengingat Perpanjangan {$name}",
                'PENGINGAT',
                'Pengingat Perpanjangan Layanan',
                "Layanan {$name} akan jatuh tempo pada {$due}.",
            ],
        };
    }

    public function toWhatsApp(object $notifiable): ?WhatsAppMessage
    {
        $appUrl = rtrim(config('app.url', 'https://vexahostcloud.my.id'), '/');
        $namaPelanggan = $notifiable->full_name ?? 'Pelanggan VexaHost';
        $name = $this->instance->hostname ?: 'Layanan Cloud';
        $due = $this->instance->expires_at?->timezone('Asia/Jakarta')->translatedFormat('d F Y') ?? '-';
        $graceEnd = $this->instance->grace_period_ends_at?->timezone('Asia/Jakarta')->translatedFormat('d F Y') ?? '-';
        $billingUrl = "{$appUrl}/dashboard/billing";

        [$subject, $badge, $heading, $body] = $this->content($name, $due, $graceEnd);

        $lines = [
            "Halo *{$namaPelanggan}*,",
            '',
            "⚠️ *{$heading}*",
            $body,
            '',
            "• *Server:* `{$name}`",
            "• *IP Public:* `{$this->instance->public_ip}`",
            "• *Jatuh Tempo:* {$due}",
        ];

        if ($this->stage === RenewalReminder::STAGE_H0) {
            $lines[] = "• *Batas Masa Tenggang:* {$graceEnd} ({$this->graceDays} hari)";
        }

        if ($this->stage !== RenewalReminder::STAGE_GRACE_ENDED) {
            $lines[] = '';
            $lines[] = 'Perpanjang layanan Anda sekarang:';
            $lines[] = $billingUrl;
        }

        $lines[] = '';
        $lines[] = '_VexaHost Cloud Solutions_';

        return WhatsAppMessage::create(implode("\n", $lines));
    }
}
