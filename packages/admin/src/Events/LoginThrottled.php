<?php

declare(strict_types=1);

namespace Sunrice\Admin\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A login limit was just reached (fired once, when it is reached, not on
 * every blocked attempt). `scope` says which: "pair" (one email from one
 * IP), "ip" (one IP trying many emails) or "account" (one account from
 * many IPs, which locks the account for a while).
 *
 * Laravel's own auth events (Failed, Login, Logout, PasswordReset) fire
 * as usual too.
 *
 * @api
 */
final class LoginThrottled
{
    use Dispatchable;

    /** @param 'pair'|'ip'|'account' $scope */
    public function __construct(
        public readonly string $email,
        public readonly string $ip,
        public readonly string $scope,
        public readonly int $seconds,
    ) {}
}
