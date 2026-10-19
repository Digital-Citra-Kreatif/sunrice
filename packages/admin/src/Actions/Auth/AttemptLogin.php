<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Auth;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Auth\LoginThrottle;
use Sunrice\Admin\Panel;

/**
 * Signs a user in to the panel: throttled, and only accounts that may
 * open the panel. Throws a ValidationException (on "email") otherwise.
 *
 * @internal
 */
final class AttemptLogin
{
    public function __construct(private Panel $panel, private LoginThrottle $throttle) {}

    public function handle(string $email, string $password, bool $remember, string $ip): void
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

        if (! $guard->attempt(['email' => $email, 'password' => $password], $remember)) {
            $this->throttle->failed($email, $ip);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $this->throttle->succeeded($email, $ip);

        // Signed in, but some accounts (an app's customers) may not open the panel.
        $user = $guard->user();
        if (! $user instanceof Authorizable || ! $user->can(Panel::ACCESS)) {
            $guard->logout();

            throw ValidationException::withMessages(['email' => __('This account can\'t use the admin panel.')]);
        }
    }
}
