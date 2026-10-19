<?php

declare(strict_types=1);

namespace Sunrice\Admin\Security;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Events\SecurityEventRecorded;
use Sunrice\Admin\Panel;
use Throwable;

/**
 * Security events: what matters for spotting a break-in, including
 * attempts by people who aren't signed in. Each one is an activity log
 * line with the source "security" about an account (`subject_type`
 * "account"), fires SecurityEventRecorded, and is also written to the log
 * channel `sunrice-admin.security.log_channel` when set (for fail2ban or a
 * log service).
 *
 *     app(SecurityLog::class)->record('two-factor turned off', $user, null, ['note' => 'by an administrator']);
 *
 * @api
 */
final class SecurityLog
{
    public function __construct(private ActivityLog $activity) {}

    /**
     * @param  Model|Authenticatable|null  $account  the account it's about, if known
     * @param  string|null  $label  how to name it otherwise (the email typed at login)
     * @param  array<string, mixed>  $properties  e.g. ['note' => 'wrong password']
     */
    public function record(string $action, Model|Authenticatable|null $account = null, ?string $label = null, array $properties = []): ?Activity
    {
        if ($account !== null && ! $account instanceof Model) {
            $id = $account->getAuthIdentifier();

            return $this->write($action, 'account', is_scalar($id) ? (string) $id : null, $label, $properties, null);
        }

        return $this->write($action, 'account', $account, $label ?? ($account === null ? null : self::accountName($account)), $properties, $account);
    }

    /**
     * A security event about something other than an account, such as a
     * role whose permissions changed: about('permissions changed', 'roles', $role, 'Editor').
     *
     * @param  array<string, mixed>  $properties
     */
    public function about(string $action, string $subjectType, Model $subject, ?string $label, array $properties = []): ?Activity
    {
        return $this->write($action, $subjectType, $subject, $label, $properties, null);
    }

    /** @param array<string, mixed> $properties */
    private function write(string $action, string $subjectType, Model|string|null $subject, ?string $label, array $properties, ?Model $account): ?Activity
    {
        $agent = request()->userAgent();
        $properties = [...$properties, ...($agent === null ? [] : ['user_agent' => Str::limit($agent, 250, '')])];

        $activity = $this->activity->withSource('security', null, fn () => $this->activity->record($action, $subjectType, $subject, $label, $properties));
        $ip = request()->ip();

        $channel = config('sunrice-admin.security.log_channel');
        if (is_string($channel) && $channel !== '') {
            try {
                Log::channel($channel)->warning("Sunrice security: {$action}", array_filter(['account' => $label, 'ip' => $ip, 'note' => $properties['note'] ?? null]));
            } catch (Throwable) {
                // A misconfigured channel mustn't break the login.
            }
        }
        SecurityEventRecorded::dispatch($action, $account, $label, $properties, $ip, $activity);

        return $activity;
    }

    /** The account with this email on the panel's guard, if any. */
    public function findAccount(string $email): ?Model
    {
        try {
            return app(Panel::class)->getUserModel()::query()->where('email', $email)->first();
        } catch (Throwable) {
            return null;
        }
    }

    private static function accountName(Model $account): ?string
    {
        $name = $account->getAttribute('name') ?? $account->getAttribute('email');

        return is_string($name) ? $name : null;
    }
}
