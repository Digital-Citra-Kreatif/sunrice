<?php

declare(strict_types=1);

namespace Sunrice\Admin\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Sunrice\Admin\Security\SecurityLog;

/**
 * "Ask people with a weak password to change it when they sign in": the
 * password typed at login is checked against the strong rules (with the
 * leak check when it's on). A weak one marks the session, and until a
 * new password is chosen every panel page leads to "Choose a stronger
 * password" (RequireStrongPassword). Only the account's id is kept, in
 * the session, never anything about the password.
 *
 * With two-factor login, the password is checked at the first step and
 * the mark waits for the code: it only counts once that account is
 * signed in.
 *
 * @internal
 */
final class WeakPasswordAtLogin
{
    public const SESSION_KEY = 'sunrice_weak_password';

    public function __construct(private readonly SecurityLog $security) {}

    /** Checks the password just typed; `$signedIn` false while a login code is still awaited. */
    public function check(Request $request, Authenticatable $user, string $password, bool $signedIn): void
    {
        $request->session()->forget(self::SESSION_KEY);
        if (! PasswordPolicy::asksWeakToChange()) {
            return;
        }
        $problems = PasswordPolicy::problems($password, $user);
        if ($problems === []) {
            return;
        }
        $request->session()->put(self::SESSION_KEY, ['user' => self::id($user), 'problems' => array_keys($problems)]);
        if ($signedIn) {
            $this->recorded($request, $user);
        }
    }

    /** After the login code: the security event, when the password was weak. */
    public function signedIn(Request $request, Authenticatable $user): void
    {
        if ($this->flagged($request, $user)) {
            $this->recorded($request, $user);
        }
    }

    /** Whether this signed-in account must choose a stronger password before anything else. */
    public function flagged(Request $request, Authenticatable $user): bool
    {
        $mark = $request->session()->get(self::SESSION_KEY);

        return is_array($mark) && ($mark['user'] ?? null) === self::id($user) && PasswordPolicy::asksWeakToChange();
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    private function recorded(Request $request, Authenticatable $user): void
    {
        $mark = $request->session()->get(self::SESSION_KEY);
        $problems = is_array($mark) && is_array($mark['problems'] ?? null) ? $mark['problems'] : [];
        $this->security->record('weak password at sign-in', $user, null, ['note' => 'fails: '.implode(', ', array_filter($problems, 'is_string')), 'problems' => $problems]);
    }

    private static function id(Authenticatable $user): string
    {
        $id = $user->getAuthIdentifier();

        return is_scalar($id) ? (string) $id : '';
    }
}
