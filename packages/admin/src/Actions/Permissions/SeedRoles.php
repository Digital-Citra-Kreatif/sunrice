<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Permissions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
            // "!pattern" leaves those out.
            $include = array_values(array_filter($patterns, fn (string $p): bool => ! str_starts_with($p, '!')));
            $exclude = array_values(array_map(fn (string $p): string => substr($p, 1), array_filter($patterns, fn (string $p): bool => str_starts_with($p, '!'))));
            $wanted = array_values(array_filter($all, fn (string $permission): bool => Str::is($include, $permission) && ($exclude === [] || ! Str::is($exclude, $permission))));
            $role = Role::findOrCreate($name, $guard);
            $has = [];
            foreach ($role->permissions as $permission) {
                $permissionName = $permission->getAttribute('name');
                if (is_string($permissionName)) {
                    $has[] = $permissionName;
                }
            }
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
