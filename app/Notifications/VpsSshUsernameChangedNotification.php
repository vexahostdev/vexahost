<?php

namespace App\Notifications;

use App\Channels\WhatsAppChannel;
use App\Models\VpsInstance;
use App\Services\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke pelanggan saat admin mengubah username SSH pada VPS aktif.
 */
class VpsSshUsernameChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public VpsInstance $instance,
        public string $oldUsername,
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

    private function serverName(): string
    {
        return $this->instance->hostname ?: ('VPS #' . $this->instance->id);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = 'Username SSH Server Anda Diperbarui — ' . $this->serverName();

        $body = "Halo " . ($notifiable->full_name ?? 'Pelanggan VexaHost') . ",\n\n"
            . "Tim VexaHost telah memperbarui username login SSH untuk server {$this->serverName()}.\n\n"
            . "Username lama: {$this->oldUsername}\n"
            . "Username baru: {$this->instance->ssh_user}\n"
            . "Perintah login: {$this->instance->ssh_command}\n\n"
            . "Password tidak berubah dan dapat dilihat di Dashboard > Detail VPS > tab Akses (klik ikon mata).\n\n"
            . "Jika Anda tidak merasa meminta perubahan ini, silakan hubungi support kami.\n\n"
            . "Terima kasih,\nTim VexaHost";

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.templated', [
                'user' => $notifiable,
                'subject' => $subject,
                'bodyText' => $body,
                'badgeText' => 'AKSES SERVER',
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'vps_instance_id' => $this->instance->id,
            'hostname' => $this->instance->hostname,
            'old_ssh_username' => $this->oldUsername,
            'ssh_username' => $this->instance->ssh_user,
        ];
    }

    public function toWhatsApp(object $notifiable): ?WhatsAppMessage
    {
        $appUrl = rtrim((string) config('app.url', 'https://vexahostcloud.my.id'), '/');

        return WhatsAppMessage::create(implode("\n", [
            'Halo *' . ($notifiable->full_name ?? 'Pelanggan VexaHost') . '*,',
            '',
            '🔐 *USERNAME SSH SERVER DIPERBARUI*',
            "Server: `{$this->serverName()}`",
            '',
            "• *Username lama:* `{$this->oldUsername}`",
            "• *Username baru:* `{$this->instance->ssh_user}`",
            '',
            '💻 *Login SSH:*',
            "`{$this->instance->ssh_command}`",
            '',
            'Password tidak berubah, lihat di Dashboard > Detail VPS > tab Akses (klik ikon mata):',
            "{$appUrl}/dashboard/vps/{$this->instance->id}",
            '',
            '_VexaHost Cloud Solutions_',
        ]));
    }
}
