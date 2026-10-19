<?php

declare(strict_types=1);

namespace Sunrice\Admin\Security;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Events\ActivityRecorded;
use Sunrice\Admin\Events\SecurityEventRecorded;
use Sunrice\Admin\Mail\SecurityAlert;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Users\PermissionRank;
use Throwable;

/**
 * Turns security events and unusual activity into email alerts (config
 * `sunrice-admin.security.alerts`): an account locked after wrong
 * passwords, one address trying many accounts, a powerful account
 * signing in from a new address or after a long absence, someone gaining
 * power over users and roles, a dangerous upload, and bursts of exports
 * or deletes.
 *
 * Each alert says what happened and what to do. The same alert (rule and
 * account) is sent once per `cooldown_minutes`, and each one sent is an
 * "alert sent" security event.
 *
 * @internal
 */
final class SecurityAlerts
{
    /** Permissions that give power over people and what they may do. */
    public const PRIVILEGED_PERMISSIONS = [
        'sunrice.users.create', 'sunrice.users.update', 'sunrice.users.delete',
        'sunrice.roles.create', 'sunrice.roles.update', 'sunrice.roles.delete',
    ];

    /** Activity log actions that take things away. */
    public const DESTRUCTIVE_ACTIONS = ['deleted', 'trashed'];

    public function __construct(private SecurityLog $log, private PermissionRank $rank, private Panel $panel) {}

    public static function enabled(): bool
    {
        $enabled = config('sunrice-admin.security.alerts.enabled', true);

        return $enabled !== false && $enabled !== 'false' && $enabled !== '0' && $enabled !== 0;
    }

    public function handleSecurityEvent(SecurityEventRecorded $event): void
    {
        if (! self::enabled() || $event->action === 'alert sent') {
            return;
        }
        try {
            $this->check($event);
        } catch (Throwable $e) {
            report($e); // an alert must never break the request
        }
    }

    public function handleActivity(ActivityRecorded $event): void
    {
        $line = $event->activity;
        if (! self::enabled() || $line->source === 'security' || ! in_array($line->action, self::DESTRUCTIVE_ACTIONS, true)) {
            return;
        }
        try {
            $actor = $line->user_id ?? ($line->via !== null ? "via:{$line->via}" : null);
            if ($actor === null) {
                return;
            }
            $limit = self::number('mass_deletes', 20);
            $count = Activity::query()->whereIn('action', self::DESTRUCTIVE_ACTIONS)->where('source', '!=', 'security')
                ->where('created_at', '>=', now()->subMinutes(10))
                ->when($line->user_id !== null, fn (Builder $q) => $q->where('user_id', $line->user_id), fn (Builder $q) => $q->where('via', $line->via))
                ->count();
            if ($count >= $limit) {
                $who = $line->user_name ?? $line->via ?? 'Someone';
                $this->alert('mass-deletes', $actor, 'Many things deleted in a short time',
                    "{$who} deleted or trashed {$count} things within 10 minutes (the last: {$line->subject_label}).",
                    'If this wasn\'t planned clean-up, the account may be in someone else\'s hands: lock it on the Users screen, then restore what you need from the trash or a backup.',
                    ['Who' => $who.($line->via !== null ? " (AI agent {$line->via})" : '')]);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function check(SecurityEventRecorded $e): void
    {
        $account = $e->label ?? 'an unknown account';
        $note = is_string($e->properties['note'] ?? null) ? $e->properties['note'] : '';
        $details = array_filter(['Account' => $e->label, 'IP address' => $e->ip, 'Details' => $note]);

        switch ($e->action) {
            case 'account locked':
                $this->alert('account-locked', $account, 'An account was locked after wrong passwords',
                    "Someone kept typing wrong passwords for {$account}, from different addresses ({$note}).",
                    'If the account\'s owner didn\'t do this, someone is guessing the password. Make sure the account has a strong, unique password.', $details);
                break;
            case 'login blocked':
                if (($e->properties['scope'] ?? null) === 'ip') {
                    $this->alert('password-spraying', (string) $e->ip, 'One address is trying many accounts',
                        "The address {$e->ip} tried passwords for many accounts and was blocked. The last one was {$account}.",
                        'This is how password-guessing tools work. Nothing to do if every account has a strong password; consider blocking the address in your firewall.', $details);
                }
                break;
            case 'logged in':
                $this->loggedIn($e, $details);
                break;
            case 'roles changed':
            case 'permissions changed':
                $this->gainedPower($e, $note, $details);
                break;
            case 'upload blocked':
                $key = $e->account?->getKey();
                $this->alert('upload-blocked', is_scalar($key) ? (string) $key : (string) $e->ip, 'A dangerous upload was blocked',
                    "Someone tried to upload {$note}.",
                    'Uploading scripts is a common way to take over a website. If the person who tried doesn\'t know why, their account may be in someone else\'s hands.', $details);
                break;
            case 'token created':
                $this->alert('token-created', (string) $e->label.'|'.$note, 'A new AI access token was created',
                    "{$e->label} created an AI access token ({$note}). Whoever holds it can work in the panel as them.",
                    'If they didn\'t mean to, revoke it on the Users screen (Revoke AI tokens) and change their password.', $details);
                break;
            case 'token used from new IP':
                $this->alert('token-new-ip', (string) $e->label.'|'.$note, 'An AI access token was used from a new address',
                    "{$e->label}'s AI access token was used {$note}.",
                    'If nobody moved to a new network or computer, the token may have been copied: revoke it on the Users screen.', $details);
                break;
            case 'exported':
                $this->exports($e, $details);
                break;
            case 'two-factor turned off':
                $this->alert('two-factor-off', 'two-factor', 'Two-factor login was turned off',
                    'Two-factor login was turned off'.($e->label !== null ? " by {$e->label}" : '').': a password alone opens the panel again.',
                    'If nobody meant to, turn it back on in Settings → Security, and check who changed it in the activity log.', $details);
                break;
        }
    }

    /** @param array<string, string> $details */
    private function loggedIn(SecurityEventRecorded $e, array $details): void
    {
        if ($e->account === null || $e->ip === null) {
            return;
        }
        $accountId = $e->account->getKey();
        if (! is_scalar($accountId)) {
            return;
        }
        $earlier = Activity::query()->where('source', 'security')->where('action', 'logged in')
            ->where('subject_type', 'account')->where('subject_id', (string) $accountId)
            ->when($e->activity !== null, fn (Builder $q) => $q->where('id', '<', $e->activity?->id))
            ->latest('id')->limit(50)->get(['ip_address', 'created_at']);
        $last = $earlier->first();
        if ($last === null) {
            return; // a first login
        }

        $dormantDays = self::number('dormant_days', 90);
        if ($last->created_at !== null && $last->created_at->lt(now()->subDays($dormantDays))) {
            $this->alert('dormant-login', (string) $e->label, 'An account signed in after a long time',
                "{$e->label} signed in for the first time since {$last->created_at->toFormattedDateString()}.",
                'Unused accounts are easy to take over unnoticed. If nobody expected this person back, lock the account on the Users screen.', $details);

            return;
        }
        if ($this->isPrivileged($e->account) && ! $earlier->contains(fn (Activity $line) => $line->ip_address === $e->ip)) {
            $this->alert('new-ip', (string) $e->label.'|'.$e->ip, 'A powerful account signed in from a new address',
                "{$e->label}, who can manage users or roles, signed in from {$e->ip}, an address not used before.",
                'If this wasn\'t them (a new network or device), sign the account out everywhere and lock it on the Users screen.', $details);
        }
    }

    /** @param array<string, string> $details */
    private function gainedPower(SecurityEventRecorded $e, string $note, array $details): void
    {
        if (! str_contains($note, 'added:')) {
            return;
        }
        $added = trim(explode(';', explode('added:', $note, 2)[1])[0]);
        $names = array_map('trim', explode(',', $added));
        $powerful = in_array(SyncPermissions::superAdminRole(), $names, true)
            || array_intersect($names, self::PRIVILEGED_PERMISSIONS) !== []
            || ($e->action === 'roles changed' && $e->account !== null && $this->isPrivileged($e->account));
        if (! $powerful) {
            return;
        }
        $this->alert('gained-power', (string) $e->label.'|'.$added, $e->action === 'roles changed' ? 'Someone was given power over users or roles' : 'A role was given power over users or roles',
            "{$e->label} now has: {$added}.",
            'Whoever has this can give themselves or others any access. If nobody on your team meant to do this, undo it on the Users or Roles screen and check who did it in the activity log.', $details);
    }

    /** @param array<string, string> $details */
    private function exports(SecurityEventRecorded $e, array $details): void
    {
        $actor = $e->account?->getKey();
        if (! is_scalar($actor)) {
            return;
        }
        $count = Activity::query()->where('source', 'security')->where('action', 'exported')
            ->where('subject_id', (string) $actor)->where('created_at', '>=', now()->subHour())->count();
        if ($count >= self::number('exports', 5)) {
            $this->alert('exports', (string) $actor, 'Many exports in a short time',
                "{$e->label} downloaded {$count} CSV exports within an hour.",
                'Exports copy data, often personal data, out of the panel. Check that this was expected; if not, lock the account.', $details);
        }
    }

    private function isPrivileged(Model $account): bool
    {
        return $this->rank->isSuperAdmin($account) || array_intersect($this->rank->permissionsOf($account), self::PRIVILEGED_PERMISSIONS) !== [];
    }

    /** @param array<string, string> $details */
    private function alert(string $rule, string $key, string $headline, string $what, string $advice, array $details): void
    {
        $cooldown = self::number('cooldown_minutes', 60);
        if (! Cache::add('sunrice-admin.alert:'.$rule.':'.sha1($key), true, now()->addMinutes(max(1, $cooldown)))) {
            return;
        }
        $recipients = $this->recipients();
        if ($recipients === []) {
            return;
        }

        $mail = new SecurityAlert($headline, $what, $advice, [...$details, 'When' => now()->toDayDateTimeString()],
            $this->panel->getBrand(), route('sunrice.admin.activity.index', ['source' => 'security']));
        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(clone $mail);
            } catch (Throwable $e) {
                report($e);
            }
        }
        $this->log->record('alert sent', null, $headline, ['note' => "{$rule} to ".count($recipients).' '.(count($recipients) === 1 ? 'person' : 'people')]);
    }

    /** @return list<string> super admins, users with the configured roles, and the configured emails */
    private function recipients(): array
    {
        $roles = config('sunrice-admin.security.alerts.roles');
        $roles = [SyncPermissions::superAdminRole(), ...(is_array($roles) ? array_filter($roles, 'is_string') : [])];
        $emails = config('sunrice-admin.security.alerts.emails');
        $emails = is_array($emails) ? array_filter($emails, 'is_string') : [];

        try {
            // Through spatie/laravel-permission's tables, for any user model.
            $model = $this->panel->getUserModel();
            $roleIds = Role::query()->whereIn('name', $roles)->where('guard_name', $this->panel->getGuard())->pluck('id');
            $table = config('permission.table_names.model_has_roles');
            $morphKey = config('permission.column_names.model_morph_key');
            $roleKey = config('permission.column_names.role_pivot_key');
            $ids = DB::table(is_string($table) ? $table : 'model_has_roles')
                ->whereIn(is_string($roleKey) ? $roleKey : 'role_id', $roleIds)
                ->where('model_type', (new $model)->getMorphClass())
                ->pluck(is_string($morphKey) ? $morphKey : 'model_id');
            foreach ($model::query()->whereKey($ids)->pluck('email') as $email) {
                if (is_string($email)) {
                    $emails[] = $email;
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        return array_values(array_unique(array_filter($emails, fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)));
    }

    private static function number(string $key, int $default): int
    {
        $value = config("sunrice-admin.security.alerts.{$key}");

        return is_numeric($value) ? max(1, (int) $value) : $default;
    }
}
