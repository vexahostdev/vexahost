<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminSlaBreachNotification extends Notification
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function __construct(
        public SupportTicket $ticket
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->newMailMessage()
            ->subject("[PERINGATAN SLA] Tiket #{$this->ticket->id} Melewati Batas Respons — {$this->ticket->subject}")
            ->view('emails.admin-sla-breach', [
                'ticket' => $this->ticket,
            ]);
    }
}
