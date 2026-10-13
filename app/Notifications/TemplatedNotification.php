<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pengirim surel berdasarkan template yang sudah dirender.
 *
 * Subjek dan isi sudah berupa teks jadi, bukan template mentah.
 * Isi di-escape saat dirender di blade, sehingga aman dari penyisipan HTML.
 */
class TemplatedNotification extends Notification
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function __construct(
        public string $mailSubject,
        public string $mailBody,
        public ?string $badgeText = null,
        public string $senderType = 'noreply'
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->newMailMessage($this->senderType)
            ->subject($this->mailSubject)
            ->view('emails.templated', [
                'user' => $notifiable,
                'subject' => $this->mailSubject,
                'bodyText' => $this->mailBody,
                'badgeText' => $this->badgeText,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'subject' => $this->mailSubject,
            'preview' => mb_substr($this->mailBody, 0, 160),
        ];
    }
}
