<?php

namespace App\Notifications;

use App\Channels\WhatsAppChannel;
use App\Models\SupportTicket;
use App\Services\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketRepliedNotification extends Notification
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function __construct(
        public SupportTicket $ticket,
        public string $replyMessage
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
        return $this->newMailMessage('support')
            ->subject("[Tiket #{$this->ticket->id}] Balasan dari Tim Support: {$this->ticket->subject}")
            ->view('emails.ticket-replied', [
                'user' => $notifiable,
                'ticket' => $this->ticket,
                'replyMessage' => $this->replyMessage,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'subject' => $this->ticket->subject,
            'message' => mb_strimwidth($this->replyMessage, 0, 100, '...'),
        ];
    }

    public function toWhatsApp(object $notifiable): ?WhatsAppMessage
    {
        $appUrl = rtrim(config('app.url', 'https://vexahostcloud.my.id'), '/');
        $namaPelanggan = $notifiable->full_name ?? 'Pelanggan VexaHost';
        $ticketUrl = "{$appUrl}/dashboard/support/{$this->ticket->id}";

        $excerpt = mb_strlen($this->replyMessage) > 300
            ? mb_substr($this->replyMessage, 0, 300) . '...'
            : $this->replyMessage;

        $message = implode("\n", [
            "Halo *{$namaPelanggan}*,",
            '',
            "💬 *BALASAN TIKET BANTUAN #{$this->ticket->id}*",
            "Tim teknis VexaHost telah membalas tiket Anda mengenai: *\"{$this->ticket->subject}\"*",
            '',
            '📝 *Pesan Balasan:*',
            "\"{$excerpt}\"",
            '',
            'Lihat percakapan lengkap atau balas di Dashboard:',
            $ticketUrl,
            '',
            '_VexaHost Support Team_',
        ]);

        return WhatsAppMessage::create($message);
    }
}
