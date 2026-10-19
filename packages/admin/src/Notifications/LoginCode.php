<?php

declare(strict_types=1);

namespace Sunrice\Admin\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Sunrice\Admin\Auth\TwoFactorLogin;

/**
 * The emailed code for two-factor login. Sent right away, never queued:
 * the person is waiting for it.
 *
 * @internal
 */
final class LoginCode extends Notification
{
    public function __construct(#[\SensitiveParameter] public readonly string $code, public readonly string $brand) {}

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your {$this->brand} login code: {$this->code}")
            ->line('Type this code to finish logging in to the admin panel:')
            ->line("**{$this->code}**")
            ->line('It works for '.TwoFactorLogin::LIFETIME_MINUTES.' minutes.')
            ->line('If you didn\'t just log in, change your password: someone else knows it.');
    }
}
