<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Permissions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Settings\SettingsStore;

/**
 * Creates the default roles the panel and its plugins declare
 * ($panel->defaultRole()), or gives them the permissions they're missing.
 * It only adds, and only what it hasn't given that role before (kept in
 * sunrice_settings under "seeded-roles"): a permission taken from one of
 * these roles in the panel stays off, while a new one (a new collection's,
 * a new plugin's) is given the next time.
 *
 * @api
 */
final class SeedRoles
{
    /** The sunrice_settings row that remembers what each role was given. */
    private const SEEDED = 'seeded-roles';

    public function __construct(private Panel $panel, private SyncPermissions $sync, private PermissionRegistrar $registrar) {}

    /** @return array<string, list<string>> role => the permissions it was given */
    public function handle(): array
    {
        $this->sync->handle();
        $guard = $this->panel->getGuard();
        $all = array_keys($this->panel->getPermissions());
        $seeded = $this->seeded();
        $given = [];
        foreach ($this->panel->getDefaultRoles() as $name => $patterns) {
            $wanted = self::matching($patterns, $all);
            $role = Role::findOrCreate($name, $guard);
            $has = self::permissionsOf($role);
            // Roles seeded before this was remembered: what they have counts as given.
            $missing = array_values(array_diff($wanted, $has, $seeded[$name] ?? []));
            if ($missing !== []) {
                $role->givePermissionTo($missing);
            }
            $given[$name] = $missing;
            $seeded[$name] = array_values(array_unique([...$seeded[$name] ?? [], ...$wanted]));
        }
        $now = now();
        DB::table(SettingsStore::TABLE)->upsert(
            [['key' => self::SEEDED, 'values' => json_encode($seeded, JSON_THROW_ON_ERROR), 'created_at' => $now, 'updated_at' => $now]],
            ['key'], ['values', 'updated_at'],
        );
        app(SettingsStore::class)->forget();
        $this->registrar->forgetCachedPermissions();

        return $given;
    }

    /**
     * What the default roles have beyond what their defaults give today,
     * role => permissions (only roles that have some): left over from an
     * older default (the editor's `cms.menus.create` and `delete`, the
     * administrator's `sunrice.ai.run-commands`), or given on purpose.
     * Only reported (`sunrice:roles` lists them): nothing is taken away.
     *
     * @return array<string, list<string>>
     */
    public function extras(): array
    {
        $guard = $this->panel->getGuard();
        $all = array_keys($this->panel->getPermissions());
        $extras = [];
        foreach ($this->panel->getDefaultRoles() as $name => $patterns) {
            $role = Role::query()->where('name', $name)->where('guard_name', $guard)->with('permissions')->first();
            if ($role instanceof Role) {
                $beyond = array_values(array_intersect(array_diff(self::permissionsOf($role), self::matching($patterns, $all)), $all));
                sort($beyond);
                if ($beyond !== []) {
                    $extras[$name] = $beyond;
                }
            }
        }

        return $extras;
    }

    /**
     * The permissions the patterns give ("!pattern" leaves those out).
     *
     * @param  list<string>  $patterns
     * @param  list<string>  $all
     * @return list<string>
     */
    private static function matching(array $patterns, array $all): array
    {
        $include = array_values(array_filter($patterns, fn (string $p): bool => ! str_starts_with($p, '!')));
        $exclude = array_values(array_map(fn (string $p): string => substr($p, 1), array_filter($patterns, fn (string $p): bool => str_starts_with($p, '!'))));

        return array_values(array_filter($all, fn (string $permission): bool => Str::is($include, $permission) && ($exclude === [] || ! Str::is($exclude, $permission))));
    }

    /** @return list<string> */
    private static function permissionsOf(RoleContract $role): array
    {
        $has = [];
        $permissions = $role instanceof Model ? $role->getAttribute('permissions') : [];
        foreach (is_iterable($permissions) ? $permissions : [] as $permission) {
            $name = $permission instanceof Model ? $permission->getAttribute('name') : null;
            if (is_string($name)) {
                $has[] = $name;
            }
        }

        return $has;
    }

    /** @return array<string, list<string>> role => the permissions given to it so far */
    private function seeded(): array
    {
        $json = DB::table(SettingsStore::TABLE)->where('key', self::SEEDED)->value('values');
        $decoded = is_string($json) ? json_decode($json, true) : null;
        $seeded = [];
        foreach (is_array($decoded) ? $decoded : [] as $role => $permissions) {
            $seeded[(string) $role] = is_array($permissions) ? array_values(array_filter($permissions, 'is_string')) : [];
        }

        return $seeded;
    }
}
