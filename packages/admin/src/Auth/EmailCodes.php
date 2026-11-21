<?php

declare(strict_types=1);

namespace Sunrice\Admin\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

/**
 * Codes sent by email to prove someone reads an address: the engine of
 * the panel's two-factor login (TwoFactorLogin), for plugins' own uses of
 * the same kind (signing in on the site with an emailed code).
 *
 * A code is 6 digits and belongs to this session: only a keyed hash of it
 * is kept there, with who it is for and what the caller adds. It works for
 * its lifetime (LIFETIME_MINUTES by default) and allows MAX_ATTEMPTS
 * guesses; wrong codes also count per user across codes (LOCKOUT_*), so
 * asking for a fresh code doesn't give endless guesses. Each purpose
 * ("two-factor", "account-login") has its own pending code and lockout.
 *
 * Sending is the caller's: issue() returns the code to email.
 *
 *     $code = app(EmailCodes::class)->issue($request, 'shop-login', $user->getKey(), ['remember' => true]);
 *     $user->notify(new ShopLoginCode($code));
 *     // later, in the same session:
 *     $pending = $codes->pending($request, 'shop-login');      // who, and the data
 *     $result = $codes->check($request, 'shop-login', $input);  // 'ok', 'wrong', 'expired', 'locked' or 'missing'
 *
 * @api
 */
final class EmailCodes
{
    public const LIFETIME_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_SECONDS = 60;

    public const LOCKOUT_ATTEMPTS = 10;

    public const LOCKOUT_SECONDS = 900;

    /**
     * Starts a pending code for a purpose in this session and returns it,
     * to be sent. An earlier one for the purpose stops working.
     *
     * @param  array<string, mixed>  $data  kept with it, given back by pending()
     */
    public function issue(Request $request, string $purpose, int|string $userId, array $data = [], int $minutes = self::LIFETIME_MINUTES): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $request->session()->put(self::sessionKey($purpose), [
            'user_id' => $userId,
            'data' => $data,
            'code' => $this->hash($code),
            'expires_at' => now()->getTimestamp() + max(1, $minutes) * 60,
            'sent_at' => now()->getTimestamp(),
            'attempts' => 0,
        ]);

        return $code;
    }

    /**
     * The pending code of a purpose in this session, without the code.
     *
     * @return array{user_id: int|string, data: array<string, mixed>, expires_at: int, sent_at: int, attempts: int}|null
     */
    public function pending(Request $request, string $purpose): ?array
    {
        $pending = $this->stored($request, $purpose);
        if ($pending === null) {
            return null;
        }
        unset($pending['code']);

        return $pending;
    }

    /** Seconds until another code may be sent for the purpose. */
    public function resendIn(Request $request, string $purpose): int
    {
        $pending = $this->stored($request, $purpose);

        return $pending === null ? 0 : max(0, $pending['sent_at'] + self::RESEND_SECONDS - now()->getTimestamp());
    }

    /** Seconds until this user may try codes for the purpose again; 0 when they may. */
    public function lockedFor(string $purpose, int|string $userId): int
    {
        $key = self::lockoutKey($purpose, $userId);

        return RateLimiter::tooManyAttempts($key, self::LOCKOUT_ATTEMPTS) ? RateLimiter::availableIn($key) : 0;
    }

    /**
     * Checks a code (spaces and dashes don't matter). The right one ends the
     * pending code: read pending() first for what it carried.
     *
     * @return 'ok'|'wrong'|'expired'|'locked'|'missing'
     */
    public function check(Request $request, string $purpose, string $code): string
    {
        $pending = $this->stored($request, $purpose);
        if ($pending === null) {
            return 'missing';
        }
        if ($pending['expires_at'] < now()->getTimestamp()) {
            $this->forget($request, $purpose);

            return 'expired';
        }
        $lockout = self::lockoutKey($purpose, $pending['user_id']);
        if (RateLimiter::tooManyAttempts($lockout, self::LOCKOUT_ATTEMPTS)) {
            $this->forget($request, $purpose);

            return 'locked';
        }

        if (! hash_equals($pending['code'], $this->hash(preg_replace('/\D/', '', $code) ?? ''))) {
            RateLimiter::hit($lockout, self::LOCKOUT_SECONDS);
            $pending['attempts']++;
            if ($pending['attempts'] >= self::MAX_ATTEMPTS || RateLimiter::tooManyAttempts($lockout, self::LOCKOUT_ATTEMPTS)) {
                $this->forget($request, $purpose);

                return 'locked';
            }
            $request->session()->put(self::sessionKey($purpose), $pending);

            return 'wrong';
        }

        $this->forget($request, $purpose);
        RateLimiter::clear($lockout);

        return 'ok';
    }

    public function forget(Request $request, string $purpose): void
    {
        $request->session()->forget(self::sessionKey($purpose));
    }

    /** Where a purpose's pending code is kept in the session: `sunrice_{purpose}` (dashes as underscores). */
    public static function sessionKey(string $purpose): string
    {
        return 'sunrice_'.str_replace('-', '_', self::purpose($purpose));
    }

    /** @return array{user_id: int|string, data: array<string, mixed>, code: string, expires_at: int, sent_at: int, attempts: int}|null */
    private function stored(Request $request, string $purpose): ?array
    {
        $pending = $request->session()->get(self::sessionKey($purpose));
        if (! is_array($pending) || ! isset($pending['user_id'], $pending['code']) || ! is_string($pending['code'])
            || ! (is_int($pending['user_id']) || is_string($pending['user_id']))) {
            return null;
        }
        $data = $pending['data'] ?? [];
        $values = [];
        foreach (is_array($data) ? $data : [] as $key => $value) {
            $values[(string) $key] = $value;
        }

        return [
            'user_id' => $pending['user_id'],
            'data' => $values,
            'code' => $pending['code'],
            'expires_at' => is_int($pending['expires_at'] ?? null) ? $pending['expires_at'] : 0,
            'sent_at' => is_int($pending['sent_at'] ?? null) ? $pending['sent_at'] : 0,
            'attempts' => is_int($pending['attempts'] ?? null) ? $pending['attempts'] : 0,
        ];
    }

    private function hash(string $code): string
    {
        $key = config('app.key');

        return hash_hmac('sha256', $code, is_string($key) ? $key : '');
    }

    private static function lockoutKey(string $purpose, int|string $userId): string
    {
        return 'sunrice-'.self::purpose($purpose).'|'.$userId;
    }

    private static function purpose(string $purpose): string
    {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $purpose) !== 1) {
            throw new InvalidArgumentException("An email code's purpose is a lowercase name like \"shop-login\", not \"{$purpose}\".");
        }

        return $purpose;
    }
}
