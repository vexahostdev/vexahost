<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * PHASE 3 - Digest harian ke admin.
 *
 * SATU surel berisi seluruh layanan yang jatuh tempo hari itu, bukan satu
 * surel per layanan. Dengan operator tunggal, dua puluh layanan yang jatuh
 * tempo bersamaan tidak boleh menghasilkan dua puluh surel.
 */
class AdminRenewalDigestNotification extends Notification
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    /**
     * @param array<string, array<int, array<string, string>>> $digest
     */
    public function __construct(
        public array $digest
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $counts = array_map('count', $this->digest);
        $total = array_sum($counts);

        $subject = "[Digest Perpanjangan] {$total} layanan perlu perhatian — "
            . now()->timezone('Asia/Jakarta')->format('d M Y');

        return $this->newMailMessage()
            ->subject($subject)
            ->view('emails.admin-renewal-digest', [
                'subject' => $subject,
                'digest' => $this->digest,
                'counts' => $counts,
                'total' => $total,
                'sections' => $this->sectionLabels(),
            ]);
    }

    protected function sectionLabels(): array
    {
        return [
            'h_minus_7' => 'Jatuh Tempo 7 Hari Lagi',
            'h_minus_3' => 'Jatuh Tempo 3 Hari Lagi',
            'h_minus_1' => 'Jatuh Tempo Besok',
            'h_zero' => 'Jatuh Tempo Hari Ini — Disuspend',
            'grace_ended' => 'Tenggang Berakhir — Diterminasi',
        ];
    }
}
