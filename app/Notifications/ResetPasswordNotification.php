<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword
{
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function toMail($notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return $this->newMailMessage()
            ->subject('Atur Ulang Kata Sandi Akun VexaHost')
            ->view('emails.reset-password', [
                'user' => $notifiable,
                'resetUrl' => $url,
            ]);
    }
}
