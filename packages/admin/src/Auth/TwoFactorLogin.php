<?php

declare(strict_types=1);

namespace Sunrice\Admin\Auth;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Sunrice\Admin\Notifications\LoginCode;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Security\SecuritySettings;

/**
 * Two-factor login by email: after the right password the user isn't
 * signed in yet. A 6-digit code is emailed, and the pending login waits in
 * the session until the code is typed.
 *
 * Only a keyed hash of the code is kept. A code works for
 * LIFETIME_MINUTES and allows MAX_ATTEMPTS guesses; wrong codes also count
 * per account across logins (LOCKOUT_*), so logging in again for a fresh
 * code doesn't give endless guesses.
 *
 * @internal
 */
final class TwoFactorLogin
{
    public const SESSION_KEY = 'sunrice_two_factor';

    public const LIFETIME_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_SECONDS = 60;

    public const LOCKOUT_ATTEMPTS = 10;

    public const LOCKOUT_SECONDS = 900;

    public function __construct(private readonly Panel $panel) {}

    /** On in Settings → Security, unless the sunrice-admin.auth.two_factor config forces it on or off. */
    public static function enabled(): bool
    {
        $forced = config('sunrice-admin.auth.two_factor');

        return is_bool($forced) ? $forced : app(SecuritySettings::class)->twoFactor;
    }

    /** Seconds until this account may try codes again; 0 when it may. */
    public function lockedFor(Authenticatable $user): int
    {
        $key = $this->lockoutKey($user->getAuthIdentifier());

        return RateLimiter::tooManyAttempts($key, self::LOCKOUT_ATTEMPTS) ? RateLimiter::availableIn($key) : 0;
    }

    /** Starts a pending login and emails its code. Nothing is kept when sending fails (it throws). */
    public function start(Request $request, Authenticatable $user, bool $remember): void
    {
        $this->send($request, $user, ['user_id' => $user->getAuthIdentifier(), 'remember' => $remember]);
    }

    /** Emails a new code for the pending login; the old one stops working. */
    public function resend(Request $request): void
    {
        $pending = $this->pending($request);
        $user = $this->user($request);
        if ($pending !== null && $user !== null) {
            $this->send($request, $user, $pending);
        }
    }

    /**
     * The pending login: user_id, remember, code (hash), expires_at, sent_at, attempts.
     *
     * @return array{user_id: mixed, remember: bool, code: string, expires_at: int, sent_at: int, attempts: int}|null
     */
    public function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::SESSION_KEY);
        if (! is_array($pending) || ! isset($pending['user_id'], $pending['code']) || ! is_string($pending['code'])) {
            return null;
        }

        return [
            'user_id' => $pending['user_id'],
            'remember' => (bool) ($pending['remember'] ?? false),
            'code' => $pending['code'],
            'expires_at' => is_int($pending['expires_at'] ?? null) ? $pending['expires_at'] : 0,
            'sent_at' => is_int($pending['sent_at'] ?? null) ? $pending['sent_at'] : 0,
            'attempts' => is_int($pending['attempts'] ?? null) ? $pending['attempts'] : 0,
        ];
    }

    public function user(Request $request): ?Authenticatable
    {
        $pending = $this->pending($request);

        return $pending === null ? null : $this->guard()->getProvider()->retrieveById($pending['user_id']);
    }

    /** Seconds until another code may be sent. */
    public function resendIn(Request $request): int
    {
        $pending = $this->pending($request);

        return $pending === null ? 0 : max(0, $pending['sent_at'] + self::RESEND_SECONDS - now()->getTimestamp());
    }

    /**
     * Checks a code; the right one completes the pending login.
     *
     * @return 'ok'|'wrong'|'expired'|'locked'|'missing'
     */
    public function verify(Request $request, string $code): string
    {
        $pending = $this->pending($request);
        $user = $this->user($request);
        if ($pending === null || $user === null) {
            return 'missing';
        }
        if ($pending['expires_at'] < now()->getTimestamp()) {
            $this->forget($request);

            return 'expired';
        }
        if ($this->lockedFor($user) > 0) {
            $this->forget($request);

            return 'locked';
        }

        if (! hash_equals($pending['code'], $this->hash(preg_replace('/\D/', '', $code) ?? ''))) {
            RateLimiter::hit($this->lockoutKey($pending['user_id']), self::LOCKOUT_SECONDS);
            $pending['attempts']++;
            if ($pending['attempts'] >= self::MAX_ATTEMPTS || RateLimiter::tooManyAttempts($this->lockoutKey($pending['user_id']), self::LOCKOUT_ATTEMPTS)) {
                $this->forget($request);

                return 'locked';
            }
            $request->session()->put(self::SESSION_KEY, $pending);

            return 'wrong';
        }

        $this->forget($request);
        RateLimiter::clear($this->lockoutKey($pending['user_id']));
        $this->guard()->login($user, $pending['remember']);
        $request->session()->regenerate();

        return 'ok';
    }

    public function forget(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    /** @param array{user_id: mixed, remember: bool} $pending */
    private function send(Request $request, Authenticatable $user, array $pending): void
    {
        $email = data_get($user, 'email');
        if (! is_string($email) || $email === '') {
            throw new \RuntimeException('The account has no email address to send a login code to.');
        }
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Not queued: this throws when the mail can't be sent.
        Notification::route('mail', $email)->notifyNow(new LoginCode($code, $this->panel->brandName()));

        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $pending['user_id'],
            'remember' => $pending['remember'],
            'code' => $this->hash($code),
            'expires_at' => now()->getTimestamp() + self::LIFETIME_MINUTES * 60,
            'sent_at' => now()->getTimestamp(),
            'attempts' => 0,
        ]);
    }

    private function hash(string $code): string
    {
        $key = config('app.key');

        return hash_hmac('sha256', $code, is_string($key) ? $key : '');
    }

    private function lockoutKey(mixed $userId): string
    {
        return 'sunrice-two-factor|'.(is_scalar($userId) ? (string) $userId : '');
    }

    private function guard(): SessionGuard
    {
        $guard = Auth::guard($this->panel->getGuard());
        if (! $guard instanceof SessionGuard) {
            throw new \LogicException("The panel's guard \"{$this->panel->getGuard()}\" must be a session guard.");
        }

        return $guard;
    }
}
