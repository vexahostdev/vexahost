<?php

namespace App\Notifications;

use App\Channels\WhatsAppChannel;
use App\Models\User;
use App\Services\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeCredentialsNotification extends Notification
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function __construct(
        public ?string $plainPassword = null
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
        return $this->newMailMessage()
            ->subject("Selamat Datang di VexaHost Cloud — Informasi Akses Akun")
            ->view('emails.welcome-credentials', [
                'user' => $notifiable,
                'plainPassword' => $this->plainPassword,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'user_id' => $notifiable->id,
            'username' => $notifiable->username,
            'email' => $notifiable->email,
        ];
    }

    public function toWhatsApp(object $notifiable): ?WhatsAppMessage
    {
        $appUrl = rtrim(config('app.url', 'https://vexahostcloud.my.id'), '/');
        $namaPelanggan = $notifiable->full_name ?? 'Pelanggan VexaHost';

        $passwordLine = $this->plainPassword
            ? "• *Password Akun:* `{$this->plainPassword}`\n"
            : '';

        $message = implode("\n", [
            "Halo *{$namaPelanggan}*,",
            '',
            '👋 *SELAMAT DATANG DI VEXAHOST!*',
            'Akun pelanggan Anda telah berhasil didaftarkan. Anda dapat mulai mengelola layanan VPS dan cloud Anda.',
            '',
            "• *Username:* `{$notifiable->username}`",
            "• *Email:* `{$notifiable->email}`",
            $passwordLine,
            'Masuk ke Dashboard:',
            "{$appUrl}/login",
            '',
            '_Terima kasih telah bergabung bersama VexaHost._',
        ]);

        return WhatsAppMessage::create($message);
    }
}
