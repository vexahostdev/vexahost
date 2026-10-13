<?php

namespace App\Notifications;

use App\Models\LoginActivity;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewLoginAlertNotification extends Notification
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function __construct(public LoginActivity $activity) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->newMailMessage()
            ->subject('Peringatan Keamanan: Login Baru Terdeteksi')
            ->view('emails.new-login-alert', [
                'user' => $notifiable,
                'activity' => $this->activity,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'activity_id' => $this->activity->id,
            'ip' => $this->activity->ip_address,
            'device' => $this->activity->device_label,
        ];
    }
}
