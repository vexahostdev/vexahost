<?php

namespace App\Notifications;

use App\Channels\WhatsAppChannel;
use App\Models\VpsInstance;
use App\Services\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VpsProvisionedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public VpsInstance $instance
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
        $hostname = $this->instance->hostname;
        $ip = $this->instance->public_ip ?? 'Aktif';

        return (new MailMessage)
            ->subject("🚀 Server VPS Anda Telah Aktif — {$hostname} ({$ip})")
            ->view('emails.vps-provisioned', [
                'user' => $notifiable,
                'instance' => $this->instance,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'vps_instance_id' => $this->instance->id,
            'hostname' => $this->instance->hostname,
            'public_ip' => $this->instance->public_ip,
            'status' => $this->instance->status,
        ];
    }

    public function toWhatsApp(object $notifiable): ?WhatsAppMessage
    {
        $this->instance->loadMissing(['customer', 'order.vpsSpec']);
        $appUrl = rtrim(config('app.url', 'https://vexahostcloud.my.id'), '/');
        $namaPelanggan = $notifiable->full_name ?? 'Pelanggan VexaHost';
        $paket = $this->instance->order?->vpsSpec?->name ?? 'VPS Instance';
        $ip = $this->instance->public_ip ?? 'Pending';
        $port = $this->instance->ssh_port ?? 22;
        $sshUser = $this->instance->ssh_user;
        $os = strtoupper($this->instance->os ?? 'Ubuntu');
        $password = $this->instance->initial_root_password ? "`{$this->instance->initial_root_password}`" : '(Tersimpan di dashboard)';
        $dashboardUrl = "{$appUrl}/dashboard/vps/{$this->instance->id}";

        $message = implode("\n", [
            "Halo *{$namaPelanggan}*,",
            '',
            '🚀 *SERVER VPS ANDA TELAH AKTIF*',
            'Layanan Virtual Private Server (VPS) Anda telah selesai dikonfigurasi dan berstatus *Online (Running)*.',
            '',
            '📋 *Detail Kredensial Server:*',
            "• *Paket:* {$paket}",
            "• *Hostname:* `{$this->instance->hostname}`",
            "• *IP Public:* `{$ip}`",
            "• *Port SSH:* `{$port}`",
            "• *Username:* `{$sshUser}`",
            "• *Password:* {$password}",
            "• *Sistem Operasi:* {$os}",
            '',
            '💻 *Akses Cepat Terminal (SSH):*',
            "`ssh {$sshUser}@{$ip} -p {$port}`",
            '',
            '⚠️ _Demi keamanan, segera ganti password default Anda setelah berhasil login pertama kali._',
            '',
            'Buka Dashboard Server:',
            $dashboardUrl,
            '',
            '_VexaHost Cloud Solutions_',
        ]);

        return WhatsAppMessage::create($message);
    }
}
