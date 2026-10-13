<?php

namespace App\Notifications\Concerns;

use App\Support\MailIdentity;
use Illuminate\Notifications\Messages\MailMessage;

trait UsesMailIdentity
{
    /**
     * Buat instance MailMessage dengan From dan Reply-To sesuai identitas domain.
     *
     * @param string $senderType 'noreply'|'support'
     */
    protected function newMailMessage(string $senderType = 'noreply'): MailMessage
    {
        $sender = $senderType === 'support' ? MailIdentity::support() : MailIdentity::noreply();
        $replyTo = MailIdentity::replyTo();

        return (new MailMessage)
            ->from($sender['address'], $sender['name'])
            ->replyTo($replyTo['address'], $replyTo['name']);
    }
}
