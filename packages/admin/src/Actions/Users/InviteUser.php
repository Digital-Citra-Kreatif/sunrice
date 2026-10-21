<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Users;

use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Sunrice\Admin\Notifications\PanelInvitation;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Tables\ActionFailed;
use Sunrice\Admin\Security\SecurityLog;

/**
 * Emails a user a link to choose their own password: the invitation for
 * a new account (Users → New user → "Email an invitation"), or a fresh
 * link later (the row's "Email a password link"). The link is a password
 * reset link of the panel's password broker (`sunrice-admin.auth.passwords`),
 * so it lasts as long as those do (`auth.passwords.*.expire`). Recorded as
 * the security event "invitation sent". Authorize first.
 *
 *     app(InviteUser::class)->handle($user);
 *
 * @api
 */
final class InviteUser
{
    public function __construct(private Panel $panel, private SecurityLog $security) {}

    /** @throws ActionFailed when the user model can't reset passwords or has no email address */
    public function handle(Model $user, ?Model $inviter = null): void
    {
        $email = $user->getAttribute('email');
        if (! $user instanceof CanResetPassword || ! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ActionFailed(__('This user can\'t be sent a password link: they need an email address, and the user model Laravel\'s CanResetPassword.'));
        }
        $name = config('sunrice-admin.auth.passwords');
        $broker = Password::broker(is_string($name) ? $name : null);
        if (! $broker instanceof PasswordBroker) {
            throw new ActionFailed(__('The panel needs Laravel\'s password broker to send password links.'));
        }
        $default = config('auth.defaults.passwords');
        $config = config('auth.passwords.'.(is_string($name) ? $name : (is_string($default) ? $default : 'users')).'.expire', 60);
        $inviterName = $inviter?->getAttribute('name');

        Notification::route('mail', $email)->notify(new PanelInvitation(
            $broker->createToken($user),
            $email,
            $this->panel->brandName(),
            is_string($inviterName) && $inviterName !== '' ? $inviterName : null,
            is_numeric($config) ? (int) $config : 60,
        ));
        $this->security->record('invitation sent', $user);
    }
}
