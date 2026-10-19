<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Auth;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Auth\LoginThrottle;
use Sunrice\Admin\Auth\TwoFactorLogin;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Security\AccountGuard;
use Sunrice\Admin\Security\SecurityLog;

/**
 * Signs a user in to the panel: throttled, and only accounts that may
 * open the panel. Throws a ValidationException (on "email") otherwise.
 * With two-factor login on, the right password emails a code instead
 * (TwoFactorLogin) and nobody is signed in yet.
 *
 * @internal
 */
final class AttemptLogin
{
    public function __construct(private Panel $panel, private LoginThrottle $throttle, private SecurityLog $security, private TwoFactorLogin $twoFactor) {}

    /** @return bool whether a login code was emailed (two-factor) rather than signed in */
    public function handle(string $email, string $password, bool $remember, string $ip, ?Request $request = null): bool
    {
        if (($blocked = $this->throttle->blocked($email, $ip)) !== null) {
            throw ValidationException::withMessages([
                'email' => $blocked['seconds'] > 120
                    ? __('Too many login attempts. Try again in :minutes minutes.', ['minutes' => (int) ceil($blocked['seconds'] / 60)])
                    : __('Too many login attempts. Try again in :seconds seconds.', ['seconds' => $blocked['seconds']]),
            ]);
        }

        $guard = Auth::guard($this->panel->getGuard());
        if (! $guard instanceof SessionGuard) {
            throw new \LogicException("The panel's guard \"{$this->panel->getGuard()}\" must be a session guard.");
        }

        $twoFactor = $request !== null && TwoFactorLogin::enabled();
        $credentials = ['email' => $email, 'password' => $password];
        // With two-factor login the password only checks; the code signs in.
        if (! ($twoFactor ? $guard->validate($credentials) : $guard->attempt($credentials, $remember))) {
            $account = $this->security->findAccount($email);
            $this->security->record('login failed', $account, $account === null ? $email : null, ['note' => $account === null ? 'no account with this email' : 'wrong password']);
            $this->throttle->failed($email, $ip);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $this->throttle->succeeded($email, $ip);

        // The right password, but some accounts (an app's customers) may not open the panel.
        $user = $twoFactor ? $guard->getLastAttempted() : $guard->user();
        $account = $user instanceof Model ? $user : null;
        if (! $user instanceof Authorizable || ! $user->can(Panel::ACCESS)) {
            $twoFactor || $guard->logout();
            $this->security->record('login refused', $account, $account === null ? $email : null, ['note' => 'no access to the panel']);

            throw ValidationException::withMessages(['email' => __('This account can\'t use the admin panel.')]);
        }

        // Locked by an administrator: the right password isn't enough.
        if ($account !== null && app(AccountGuard::class)->isLocked($account)) {
            $twoFactor || $guard->logout();
            $this->security->record('login blocked', $account, null, ['note' => 'the account is locked']);

            throw ValidationException::withMessages(['email' => __('This account is locked. Ask an administrator to unlock it.')]);
        }

        if ($twoFactor) {
            if (($wait = $this->twoFactor->lockedFor($user)) > 0) {
                $this->security->record('login blocked', $account, null, ['note' => 'too many wrong login codes']);

                throw ValidationException::withMessages(['email' => __('Too many wrong login codes. Try again in :minutes minutes.', ['minutes' => (int) ceil($wait / 60)])]);
            }
            try {
                $this->twoFactor->start($request, $user, $remember);
            } catch (\Throwable $e) {
                report($e);

                throw ValidationException::withMessages(['email' => __('We couldn\'t email your login code. Ask an administrator to check that the app can send email.')]);
            }

            return true;
        }

        $this->security->record('logged in', $account, $account === null ? $email : null);

        return false;
    }
}
