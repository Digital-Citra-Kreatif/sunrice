<?php

declare(strict_types=1);

namespace Sunrice\Admin\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The invitation to a new user (InviteUser): a link to the panel's reset
 * page to choose their own password.
 *
 * @internal
 */
final class PanelInvitation extends ResetPassword
{
    public function __construct(
        #[\SensitiveParameter] string $token,
        public readonly string $email,
        public readonly string $brand,
        public readonly ?string $inviter,
        public readonly int $minutes,
    ) {
        parent::__construct($token);
    }

    /** @param string $url */
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject("You're invited to {$this->brand}")
            ->line(($this->inviter === null ? 'You have an account' : "{$this->inviter} made you an account")." on {$this->brand}, for {$this->email}.")
            ->line('Choose your password to sign in:')
            ->action('Choose your password', $url)
            ->line("The link works for {$this->minutes} minutes. After that, use \"Forgot password?\" on the login page for a new one.")
            ->salutation("Regards,  \n{$this->brand}");
    }

    protected function resetUrl(mixed $notifiable): string
    {
        return url(route('sunrice.admin.password.reset', ['token' => $this->token, 'email' => $this->email], false));
    }
}
