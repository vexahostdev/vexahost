<?php

namespace App\Notifications;

use App\Models\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationInvitationNotification extends Notification
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function __construct(public OrganizationInvitation $invitation) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $org = $this->invitation->organization;
        $role = $this->invitation->role;
        $url = url('/invitations/' . $this->invitation->token);

        return $this->newMailMessage()
            ->subject("Undangan bergabung ke {$org->name}")
            ->line("Anda diundang bergabung ke organisasi {$org->name} di VexaHost sebagai {$role->name}.")
            ->action('Terima Undangan', $url)
            ->line('Undangan berlaku sampai ' . ($this->invitation->expires_at ? $this->invitation->expires_at->timezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB' : '-'));
    }
}
