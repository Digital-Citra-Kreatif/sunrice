<?php

declare(strict_types=1);

namespace Sunrice\Admin\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Sunrice\Admin\Security\AccountSecurity;
use Sunrice\Admin\Security\SecurityLog;
use Throwable;

/**
 * "Ask people with a weak password to change it when they sign in": the
 * password typed at login is checked against the strong rules (with the
 * leak check when it's on). A weak one marks the account, and until a
 * new password is chosen every panel page leads to "Choose a stronger
 * password" (RequireStrongPassword).
 *
 * The mark is kept with the account's security state (AccountSecurity),
 * not in the session, so it holds in every session: one signed back in
 * by "Remember me" too. It is a keyed fingerprint of the password hash
 * the account signed in with, never anything about the password itself,
 * so it ends by itself when the password changes however it's changed
 * (choosing a stronger one, Your account, an administrator, a reset).
 * Turning the setting off stops asking; marks of accounts still on the
 * same password count again if it's turned back on. Deleting the user
 * deletes it with the rest of their security state.
 *
 * With two-factor login, the password is checked at the first step and
 * the mark waits in the session for the code: it only counts once that
 * account is signed in.
 *
 * @internal
 */
final class WeakPasswordAtLogin
{
    /** Session key: the mark waiting for the login code, and what the password failed (for the security event). */
    public const SESSION_KEY = 'sunrice_weak_password';

    /** Cache key: the marked accounts' fingerprints by user id, read on every panel page. */
    private const CACHE = 'sunrice-admin:weak-passwords';

    /** @var array<string, string>|null this request's copy */
    private ?array $marks = null;

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
            // A strong password now: an older mark has nothing left to ask.
            $this->clear($user);

            return;
        }
        $request->session()->put(self::SESSION_KEY, ['user' => self::id($user), 'problems' => array_keys($problems)]);
        if ($signedIn) {
            $this->mark($user);
            $this->recorded($request, $user);
        }
    }

    /** After the login code: the mark and the security event, when the password was weak. */
    public function signedIn(Request $request, Authenticatable $user): void
    {
        $pending = $request->session()->get(self::SESSION_KEY);
        if (is_array($pending) && ($pending['user'] ?? null) === self::id($user) && PasswordPolicy::asksWeakToChange()) {
            $this->mark($user);
            $this->recorded($request, $user);
        }
    }

    /** Whether this signed-in account must choose a stronger password before anything else. */
    public function flagged(Authenticatable $user): bool
    {
        $mark = $this->marks()[self::id($user)] ?? null;
        if ($mark === null || ! PasswordPolicy::asksWeakToChange()) {
            return false;
        }
        if (hash_equals($mark, self::fingerprint($user))) {
            return true;
        }
        // The password changed since (outside "Choose a stronger password"): nothing left to ask.
        $this->clear($user);

        return false;
    }

    /** Forgets the account's mark (a stronger password was chosen, or the account is deleted). */
    public function clear(Authenticatable $user): void
    {
        if (! array_key_exists(self::id($user), $this->marks())) {
            return;
        }
        AccountSecurity::query()->where('user_id', self::id($user))->update(['weak_password' => null]);
        $this->changed();
    }

    private function mark(Authenticatable $user): void
    {
        AccountSecurity::query()->updateOrCreate(['user_id' => self::id($user)], ['weak_password' => self::fingerprint($user)]);
        $this->changed();
    }

    /**
     * Every marked account, cached until one changes, so panel pages
     * don't ask the database.
     *
     * @return array<string, string>
     */
    private function marks(): array
    {
        if ($this->marks !== null) {
            return $this->marks;
        }
        try {
            $marks = Cache::remember(self::CACHE, 3600, fn (): array => AccountSecurity::query()
                ->whereNotNull('weak_password')->pluck('weak_password', 'user_id')->all());
        } catch (Throwable) {
            // Not migrated yet: nobody is marked.
            $marks = [];
        }
        $clean = [];
        foreach ($marks as $id => $mark) {
            if (is_string($mark)) {
                $clean[(string) $id] = $mark;
            }
        }

        return $this->marks = $clean;
    }

    private function changed(): void
    {
        $this->marks = null;
        Cache::forget(self::CACHE);
    }

    /** The password hash the account has now, fingerprinted with the app key (never stored as it is). */
    private static function fingerprint(Authenticatable $user): string
    {
        $key = config('app.key');

        return hash_hmac('sha256', $user->getAuthPassword(), 'sunrice-weak-password|'.(is_string($key) ? $key : ''));
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
