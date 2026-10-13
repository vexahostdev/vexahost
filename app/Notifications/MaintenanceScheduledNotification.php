<?php

namespace App\Notifications;

use App\Channels\WhatsAppChannel;
use App\Models\MaintenanceWindow;
use App\Services\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pemberitahuan maintenance terjadwal ke pelanggan.
 */
class MaintenanceScheduledNotification extends Notification
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function __construct(
        public MaintenanceWindow $window
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
        $start = $this->window->starts_at->timezone('Asia/Jakarta');
        $end = $this->window->ends_at->timezone('Asia/Jakarta');

        $subject = 'Pemberitahuan Maintenance Terjadwal — ' . $start->format('d M Y');

        return $this->newMailMessage()
            ->subject($subject)
            ->view('emails.maintenance-scheduled', [
                'user' => $notifiable,
                'window' => $this->window,
                'startText' => $start->format('d M Y, H:i') . ' WIB',
                'endText' => $end->format('d M Y, H:i') . ' WIB',
                'durationText' => $this->durationText(),
                'subject' => $subject,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'maintenance_window_id' => $this->window->id,
            'title' => $this->window->title,
            'starts_at' => $this->window->starts_at->toIso8601String(),
            'ends_at' => $this->window->ends_at->toIso8601String(),
        ];
    }

    protected function durationText(): string
    {
        $minutes = $this->window->duration_minutes;

        if ($minutes < 60) {
            return $minutes . ' menit';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest === 0 ? $hours . ' jam' : $hours . ' jam ' . $rest . ' menit';
    }

    public function toWhatsApp(object $notifiable): ?WhatsAppMessage
    {
        $namaPelanggan = $notifiable->full_name ?? 'Pelanggan VexaHost';
        $start = $this->window->starts_at->timezone('Asia/Jakarta')->translatedFormat('d F Y, H:i') . ' WIB';
        $end = $this->window->ends_at->timezone('Asia/Jakarta')->translatedFormat('d F Y, H:i') . ' WIB';

        $message = implode("\n", [
            "Halo *{$namaPelanggan}*,",
            '',
            '🔧 *PEMBERITAHUAN PEMELIHARAAN SISTEM*',
            "*{$this->window->title}*",
            '',
            "• *Waktu Mulai:* {$start}",
            "• *Perkiraan Selesai:* {$end} ({$this->durationText()})",
            '',
            $this->window->description ?: 'Pemeliharaan rutin infrastruktur server cloud VexaHost.',
            '',
            '_Kami mengupayakan pemeliharaan selesai tepat waktu untuk meminimalkan dampak pada server Anda._',
            '_VexaHost Operations Team_',
        ]);

        return WhatsAppMessage::create($message);
    }
}
