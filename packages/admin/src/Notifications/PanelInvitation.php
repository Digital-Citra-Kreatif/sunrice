<?php

declare(strict_types=1);

namespace Sunrice\Admin\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Sunrice\Admin\Support\AppUrl;

/**
 * The invitation to a new user (InviteUser): a link to the panel's reset
 * page to choose their own password (`invited=1`: the page says "Choose
 * your password", not "a new" one).
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
            ->subject(__('You\'re invited to :brand', ['brand' => $this->brand]))
            ->line($this->inviter === null
                ? __('You have an account on :brand, for :email.', ['brand' => $this->brand, 'email' => $this->email])
                : __(':name made you an account on :brand, for :email.', ['name' => $this->inviter, 'brand' => $this->brand, 'email' => $this->email]))
            ->line(__('Choose your password to sign in:'))
            ->action(__('Choose your password'), $url)
            ->line(__('The link works for :minutes minutes. After that, use "Forgot password?" on the login page for a new one.', ['minutes' => $this->minutes]))
            ->salutation("Regards,  \n{$this->brand}");
    }

    protected function resetUrl(mixed $notifiable): string
    {
        // From APP_URL, like ResetPanelPassword: never the request's Host.
        return AppUrl::route('sunrice.admin.password.reset', ['token' => $this->token, 'email' => $this->email, 'invited' => 1]);
    }
}
