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
            ->subject(__('Your :brand login code: :code', ['brand' => $this->brand, 'code' => $this->code]))
            ->line(__('Type this code to finish logging in to the admin panel:'))
            ->line("**{$this->code}**")
            ->line(__('It works for :minutes minutes.', ['minutes' => TwoFactorLogin::LIFETIME_MINUTES]))
            ->line(__('If you didn\'t just log in, change your password: someone else knows it.'))
            ->salutation(__('Regards,')."  \n{$this->brand}");
    }
}
