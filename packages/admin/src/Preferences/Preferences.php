<?php

declare(strict_types=1);

namespace Sunrice\Admin\Preferences;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Each user's own choices in the panel, kept by key as JSON (a table's
 * columns and page size, a folded card):
 *
 *     $preferences->set($user, 'table.shop.orders', ['columns' => ['number', 'total'], 'perPage' => 50]);
 *     $preferences->get($user, 'table.shop.orders', []);
 *     $preferences->table($user, 'table.shop.orders');   // ['columns' => [...]|null, 'perPage' => 50|null]
 *
 * The browser reads and writes them through `GET`/`PUT admin/preferences/{key}`
 * (`useTablePreferences()` in `@imajiner/admin-sdk/ui`). Keys are letters,
 * digits, dots, dashes and underscores (`KEY_PATTERN`); prefix yours with
 * your plugin's id. A value is at most `MAX_BYTES` of JSON.
 *
 * @api
 */
final class Preferences
{
    public const KEY_PATTERN = '[A-Za-z0-9][A-Za-z0-9._-]{0,99}';

    public const MAX_BYTES = 8192;

    private const TABLE = 'sunrice_user_preferences';

    /** The user's value for the key, or the default when they have none. */
    public function get(Authenticatable $user, string $key, mixed $default = null): mixed
    {
        $stored = DB::table(self::TABLE)->where('user_id', self::id($user))->where('key', self::key($key))->value('value');
        if (! is_string($stored)) {
            return $default;
        }
        $value = json_decode($stored, true);

        return $value ?? $default;
    }

    /** Keeps the value for the user (null forgets it). */
    public function set(Authenticatable $user, string $key, mixed $value): void
    {
        if ($value === null) {
            $this->forget($user, $key);

            return;
        }
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($json) > self::MAX_BYTES) {
            throw new InvalidArgumentException('A preference is at most '.self::MAX_BYTES.' bytes of JSON.');
        }
        $now = now();
        DB::table(self::TABLE)->upsert(
            [['user_id' => self::id($user), 'key' => self::key($key), 'value' => $json, 'created_at' => $now, 'updated_at' => $now]],
            ['user_id', 'key'],
            ['value', 'updated_at'],
        );
    }

    public function forget(Authenticatable $user, string $key): void
    {
        DB::table(self::TABLE)->where('user_id', self::id($user))->where('key', self::key($key))->delete();
    }

    /** Forgets everything a user chose (when the user is deleted). */
    public function forgetUser(int|string $userId): void
    {
        DB::table(self::TABLE)->where('user_id', (string) $userId)->delete();
    }

    /**
     * A table's saved columns (keys, in order) and page size, each null
     * when not chosen, as `useTablePreferences()` keeps them.
     *
     * @return array{columns: list<string>|null, perPage: int|null}
     */
    public function table(Authenticatable $user, string $key): array
    {
        $value = $this->get($user, $key);
        $value = is_array($value) ? $value : [];
        $columns = is_array($value['columns'] ?? null) ? array_values(array_filter($value['columns'], is_string(...))) : null;
        $perPage = $value['perPage'] ?? null;

        return ['columns' => $columns, 'perPage' => is_int($perPage) && $perPage > 0 ? $perPage : null];
    }

    public static function validKey(string $key): bool
    {
        return preg_match('/^'.self::KEY_PATTERN.'$/', $key) === 1;
    }

    private static function key(string $key): string
    {
        if (! self::validKey($key)) {
            throw new InvalidArgumentException("\"{$key}\" isn't a preference key: letters, digits, dots, dashes and underscores.");
        }

        return $key;
    }

    private static function id(Authenticatable $user): string
    {
        $id = $user->getAuthIdentifier();

        return is_scalar($id) ? (string) $id : '';
    }
}
