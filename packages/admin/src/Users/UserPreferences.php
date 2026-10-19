<?php

declare(strict_types=1);

namespace Sunrice\Admin\Users;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * What each person chose in the panel, by key: a list's page size and
 * hidden columns ("table.products"). Plugins keep theirs under their id
 * ("shop.…").
 *
 * @api
 */
final class UserPreferences
{
    private const TABLE = 'sunrice_user_preferences';

    /** @var array<string, array<string, mixed>> user id => key => value, read once per request */
    private array $loaded = [];

    public function get(Authenticatable $user, string $key, mixed $default = null): mixed
    {
        return $this->all($user)[$key] ?? $default;
    }

    public function put(Authenticatable $user, string $key, mixed $value): void
    {
        $id = self::id($user);
        DB::table(self::TABLE)->upsert(
            [['user_id' => $id, 'key' => $key, 'value' => json_encode($value, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]],
            ['user_id', 'key'],
            ['value', 'updated_at'],
        );
        $this->loaded[$id] = [...$this->all($user), $key => $value];
    }

    /** Forget everything the user chose (when the account goes). */
    public function forget(Authenticatable $user): void
    {
        DB::table(self::TABLE)->where('user_id', self::id($user))->delete();
        unset($this->loaded[self::id($user)]);
    }

    /** @return array<string, mixed> */
    private function all(Authenticatable $user): array
    {
        $id = self::id($user);
        if (! isset($this->loaded[$id])) {
            $this->loaded[$id] = [];
            foreach (DB::table(self::TABLE)->where('user_id', $id)->get(['key', 'value']) as $row) {
                if (is_string($row->key) && is_string($row->value)) {
                    $this->loaded[$id][$row->key] = json_decode($row->value, true);
                }
            }
        }

        return $this->loaded[$id];
    }

    private static function id(Authenticatable $user): string
    {
        $id = $user->getAuthIdentifier();

        return is_scalar($id) ? (string) $id : '';
    }
}
