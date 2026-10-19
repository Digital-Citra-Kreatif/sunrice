<?php

declare(strict_types=1);

namespace Sunrice\Admin\Auth;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Sunrice\Admin\Events\LoginThrottled;

/**
 * Brute-force protection for the panel's login. Wrong passwords are
 * counted three ways (sunrice-admin.auth.throttle):
 *
 * - the same email from the same IP (a person mistyping, or a script);
 * - one IP across all emails (password spraying);
 * - one account across all IPs (a botnet), which locks the account a while.
 *
 * Failed logins and limits reached are written to the log with email and
 * IP, so attacks show up there (and can feed tools such as fail2ban).
 *
 * @api
 */
final class LoginThrottle
{
    /**
     * Seconds to wait when a limit is reached, and which one.
     *
     * @return array{seconds: int, scope: 'pair'|'ip'|'account'}|null
     */
    public function blocked(string $email, string $ip): ?array
    {
        foreach ($this->limits($email, $ip) as $scope => [$key, $max]) {
            if ($max > 0 && RateLimiter::tooManyAttempts($key, $max)) {
                return ['seconds' => max(1, RateLimiter::availableIn($key)), 'scope' => $scope];
            }
        }

        return null;
    }

    public function failed(string $email, string $ip): void
    {
        foreach ($this->limits($email, $ip) as $scope => [$key, $max]) {
            $decay = $this->decay($scope);
            RateLimiter::hit($key, $decay);
            if ($max > 0 && RateLimiter::attempts($key) === $max) {
                Log::warning('Sunrice: admin login limit reached', ['scope' => $scope, 'email' => $email, 'ip' => $ip, 'seconds' => $decay]);
                LoginThrottled::dispatch($email, $ip, $scope, $decay);
            }
        }

        Log::notice('Sunrice: failed admin login', ['email' => $email, 'ip' => $ip]);
    }

    /** A successful login forgives this email's mistakes (the IP's count stays). */
    public function succeeded(string $email, string $ip): void
    {
        $limits = $this->limits($email, $ip);
        RateLimiter::clear($limits['pair'][0]);
        RateLimiter::clear($limits['account'][0]);
    }

    /** Seconds this email stays locked after too many wrong passwords from any IP (0: not locked). */
    public function accountLockedFor(string $email): int
    {
        [$key, $max] = $this->limits($email, '')['account'];

        return $max > 0 && RateLimiter::tooManyAttempts($key, $max) ? max(1, RateLimiter::availableIn($key)) : 0;
    }

    /** Forget this email's wrong passwords (an administrator unlocked it). */
    public function clearAccount(string $email): void
    {
        RateLimiter::clear($this->limits($email, '')['account'][0]);
    }

    /** @param 'pair'|'ip'|'account' $scope */
    private function decay(string $scope): int
    {
        return match ($scope) {
            'pair' => self::setting('decay_seconds', 60),
            'ip' => self::setting('per_ip_decay_seconds', 60),
            'account' => self::setting('account_lock_seconds', 900),
        };
    }

    /** @return array{pair: array{0: string, 1: int}, ip: array{0: string, 1: int}, account: array{0: string, 1: int}} */
    private function limits(string $email, string $ip): array
    {
        $email = mb_strtolower(trim($email));

        return [
            'pair' => ['sunrice-login|'.$email.'|'.$ip, self::setting('attempts', 5)],
            'ip' => ['sunrice-login-ip|'.$ip, self::setting('per_ip', 20)],
            'account' => ['sunrice-login-account|'.$email, self::setting('per_account', 30)],
        ];
    }

    private static function setting(string $key, int $default): int
    {
        $value = config("sunrice-admin.auth.throttle.{$key}", $default);

        return is_numeric($value) ? (int) $value : $default;
    }
}
