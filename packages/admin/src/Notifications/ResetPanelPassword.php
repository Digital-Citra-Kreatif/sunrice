<?php

declare(strict_types=1);

namespace Sunrice\Admin\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The password reset email. Laravel's own links to the app's
 * `password.reset` route, which may not exist (or may be the app's own
 * front-end auth); this one links to the panel's reset page and names
 * the panel (its branded name).
 *
 * @internal
 */
final class ResetPanelPassword extends ResetPassword
{
    /** Sent to the address on demand, so any user model works (Notifiable or not). */
    public function __construct(#[\SensitiveParameter] string $token, public readonly string $email, public readonly string $brand)
    {
        parent::__construct($token);
    }

    /** @param string $url */
    protected function buildMailMessage($url): MailMessage
    {
        return parent::buildMailMessage($url)
            ->subject("Reset your {$this->brand} password")
            ->salutation("Regards,  \n{$this->brand}");
    }

    protected function resetUrl(mixed $notifiable): string
    {
        return url(route('sunrice.admin.password.reset', ['token' => $this->token, 'email' => $this->email], false));
    }
}
