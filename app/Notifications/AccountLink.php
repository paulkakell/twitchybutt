<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AccountLink extends Notification
{
    public function __construct(public string $purpose, public string $url) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reset = $this->purpose === 'reset';

        return (new MailMessage)
            ->subject($reset ? 'Reset your site password' : 'Verify your email address')
            ->line($reset ? 'A password reset was requested for your account.' : 'Confirm this email address belongs to you.')
            ->action($reset ? 'Reset password' : 'Verify email', $this->url)
            ->line($reset ? 'This link expires in 30 minutes. Resetting your password signs out existing sessions.' : 'This link expires in 60 minutes and requires signing in to the matching account.')
            ->line('Ignore this message if you did not request it. Do not forward the link.');
    }
}
