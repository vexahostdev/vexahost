<?php

namespace App\Notifications;

use App\Channels\WhatsAppChannel;
use App\Models\RenewalReminder;
use App\Models\VpsInstance;
use App\Services\RenewalService;
use App\Services\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * PHASE 3 - Pengingat perpanjangan layanan ke pelanggan.
 *
 * Isi pesan sengaja singkat dan langsung: layanan mana (IP), kapan dihapus,
 * dan ke mana harus memperpanjang.
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
        $due = $this->instance->expires_at?->timezone('Asia/Jakarta')->format('d M Y') ?? '-';
        $graceEnd = $this->graceEndText();

        [$subject, $badge, $heading, $body] = $this->content($due, $graceEnd);

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
                'renewUrl' => $this->renewUrl(),
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
    protected function content(string $due, string $graceEnd): array
    {
        $type = app(RenewalService::class)->typeLabel(app(RenewalService::class)->productType($this->instance));
        $target = $this->targetText($type);
        $deletion = "Jika belum diperpanjang, maka akan kami hapus pada {$graceEnd}.";

        return match ($this->stage) {
            RenewalReminder::STAGE_H3 => [
                "{$type} Anda Akan Expired 3 Hari Lagi",
                'PENGINGAT PERPANJANGAN',
                "{$type} Anda Akan Expired 3 Hari Lagi",
                "{$target} akan expired pada {$due}. {$deletion}",
            ],
            RenewalReminder::STAGE_GRACE_FINAL => [
                "{$type} Anda Akan Segera Dihapus",
                'PERINGATAN TERAKHIR',
                "{$type} Anda Akan Segera Dihapus",
                "{$target} sudah expired dan belum diperpanjang, maka akan kami hapus dalam waktu kurang "
                    . "dari 24 jam, pada {$graceEnd}. Setelah itu pembayaran ditutup dan data tidak dapat dipulihkan.",
            ],
            RenewalReminder::STAGE_GRACE_ENDED => [
                "{$type} Anda Telah Dihapus",
                'LAYANAN DITERMINASI',
                "{$type} Anda Telah Dihapus",
                "{$target} sudah melewati batas perpanjangan pada {$graceEnd}, sehingga sudah kami hapus "
                    . 'dan datanya tidak dapat dipulihkan. Jika ingin melanjutkan, silakan pesan layanan baru.',
            ],
            default => [
                "{$type} Anda Akan Expired",
                'PENGINGAT',
                "{$type} Anda Akan Expired",
                "{$target} akan expired pada {$due}. {$deletion}",
            ],
        };
    }

    public function toWhatsApp(object $notifiable): ?WhatsAppMessage
    {
        $due = $this->instance->expires_at?->timezone('Asia/Jakarta')->translatedFormat('d F Y') ?? '-';

        [, , $heading, $body] = $this->content($due, $this->graceEndText());

        $lines = [
            'Halo *' . ($notifiable->full_name ?? 'Pelanggan VexaHost') . '*,',
            '',
            "⚠️ *{$heading}*",
            '',
            $body,
        ];

        if ($this->stage !== RenewalReminder::STAGE_GRACE_ENDED) {
            $lines[] = '';
            $lines[] = 'Jika ingin melanjutkan, segera perpanjang di:';
            $lines[] = $this->renewUrl();
        }

        $lines[] = '';
        $lines[] = 'Terima kasih';
        $lines[] = '_VexaHost Cloud Solutions_';

        return WhatsAppMessage::create(implode("\n", $lines));
    }

    private function targetText(string $type): string
    {
        if (!empty($this->instance->public_ip)) {
            return "{$type} Anda dengan IP {$this->instance->public_ip}";
        }

        return "{$type} Anda (" . ($this->instance->hostname ?: 'Layanan Cloud') . ')';
    }

    private function renewUrl(): string
    {
        return route('dashboard.vps.show', $this->instance->id);
    }

    /** Batasnya dihitung sampai jam, jadi jam-nya harus tertulis. */
    private function graceEndText(): string
    {
        $end = $this->instance->grace_period_ends_at;

        return $end ? $end->copy()->timezone('Asia/Jakarta')->format('d M Y, H:i') . ' WIB' : '-';
    }
}
