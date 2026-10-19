<?php

declare(strict_types=1);

namespace Sunrice\Admin\Security;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Sunrice\Admin\Auth\LoginThrottle;
use Sunrice\Admin\Panel;
use Throwable;

/**
 * What an administrator can do about an account that may be in the wrong
 * hands: sign it out everywhere, and lock it (no login and no panel pages
 * until it's unlocked). Each is a security event.
 *
 * Signing out works with any session driver: sessions that signed in
 * before it end on their next panel page, and "remember me" cookies stop
 * working. With the database session driver the other sessions are
 * deleted right away too.
 *
 * @api
 */
final class AccountGuard
{
    /** Session key: when this session signed in (a Unix time). */
    public const LOGIN_AT = 'sunrice.login_at';

    /** @var list<string>|null this request's locked account ids */
    private ?array $locked = null;

    public function __construct(private SecurityLog $security) {}

    public function isLocked(Model|Authenticatable $user): bool
    {
        if ($this->locked === null) {
            $this->locked = [];
            try {
                foreach (AccountSecurity::query()->whereNotNull('locked_at')->pluck('user_id') as $id) {
                    if (is_scalar($id)) {
                        $this->locked[] = (string) $id;
                    }
                }
            } catch (Throwable) {
                // Not migrated yet: nothing is locked.
            }
        }

        return in_array($this->key($user), $this->locked, true);
    }

    public function state(Model|Authenticatable $user): ?AccountSecurity
    {
        return AccountSecurity::query()->where('user_id', $this->key($user))->first();
    }

    /** Whether this request's session may stay signed in as `$user`. */
    public function sessionAllowed(Request $request, Authenticatable $user): bool
    {
        $state = $this->state($user);
        if ($state === null) {
            return true;
        }
        if ($state->locked_at !== null) {
            return false;
        }
        if ($state->signed_out_at === null || ! $request->hasSession()) {
            return true;
        }
        $loginAt = $request->session()->get(self::LOGIN_AT);

        return is_numeric($loginAt) && (int) $loginAt >= $state->signed_out_at->getTimestamp();
    }

    /**
     * End the account's sessions and "remember me" logins. Signing out
     * yourself keeps this session. How many sessions were deleted
     * (database session driver), or null with other drivers.
     */
    public function signOutEverywhere(Model $user, bool $record = true): ?int
    {
        $current = $this->isCurrentUser($user) ? request()->session() : null;
        $now = now();
        AccountSecurity::query()->updateOrCreate(['user_id' => $this->key($user)], ['signed_out_at' => $now]);
        $current?->put(self::LOGIN_AT, $now->getTimestamp());

        $deleted = null;
        if (config('session.driver') === 'database') {
            try {
                $connection = config('session.connection');
                $table = config('session.table', 'sessions');
                $deleted = DB::connection(is_string($connection) ? $connection : null)->table(is_string($table) ? $table : 'sessions')
                    ->where('user_id', $user->getKey())
                    ->when($current !== null, fn (Builder $query) => $query->where('id', '!=', $current?->getId()))
                    ->delete();
            } catch (Throwable $e) {
                Log::warning('Sunrice: could not delete sessions: '.$e->getMessage());
            }
        }

        // A new remember token: "remember me" cookies stop working.
        if ($user instanceof Authenticatable && $user->getRememberTokenName() !== '') {
            try {
                $user->newQuery()->whereKey($user->getKey())->update([$user->getRememberTokenName() => Str::random(60)]);
            } catch (Throwable $e) {
                Log::warning('Sunrice: could not reset the remember token: '.$e->getMessage());
            }
        }

        if ($record) {
            $this->security->record('signed out everywhere', $user, null, $deleted === null ? [] : ['note' => $deleted.' '.Str::plural('session', $deleted).' ended']);
        }

        return $deleted;
    }

    /** Lock the account and sign it out everywhere. */
    public function lock(Model $user, ?string $reason = null): void
    {
        $by = auth(app(Panel::class)->getGuard())->user();
        $byName = $by === null ? null : (data_get($by, 'name') ?? data_get($by, 'email'));
        $reason = $reason === null || trim($reason) === '' ? null : Str::limit(trim($reason), 250, '');
        AccountSecurity::query()->updateOrCreate(['user_id' => $this->key($user)], [
            'locked_at' => now(),
            'locked_by' => is_string($byName) ? Str::limit($byName, 250, '') : null,
            'locked_reason' => $reason,
        ]);
        $this->locked = null;
        $this->signOutEverywhere($user, record: false);
        $this->security->record('account locked by admin', $user, null, $reason === null ? [] : ['note' => $reason]);
    }

    /** Unlock: also forgets a lock after too many wrong passwords. */
    public function unlock(Model $user): void
    {
        AccountSecurity::query()->where('user_id', $this->key($user))->update(['locked_at' => null, 'locked_by' => null, 'locked_reason' => null]);
        $this->locked = null;
        $email = $user->getAttribute('email');
        if (is_string($email)) {
            app(LoginThrottle::class)->clearAccount($email);
        }
        $this->security->record('account unlocked', $user);
    }

    private function isCurrentUser(Model $user): bool
    {
        $current = auth(app(Panel::class)->getGuard())->user();

        return $current !== null && request()->hasSession() && $this->key($current) === $this->key($user);
    }

    private function key(Model|Authenticatable $user): string
    {
        $key = $user instanceof Model ? $user->getKey() : $user->getAuthIdentifier();

        return is_scalar($key) ? (string) $key : '';
    }
}
