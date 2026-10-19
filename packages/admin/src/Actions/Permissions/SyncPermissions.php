<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Permissions;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Sunrice\Admin\Panel;

/**
 * Makes the database match the permissions the panel and its plugins
 * register: creates missing ones, deletes ones that are gone (only under
 * the panel's and the plugins' prefixes: the app's own permissions are
 * left alone), and creates the super admin role.
 *
 * @api
 */
final class SyncPermissions
{
    public function __construct(private Panel $panel, private PermissionRegistrar $registrar) {}

    /** @return array{created: list<string>, deleted: list<string>} */
    public function handle(): array
    {
        $guard = $this->panel->getGuard();
        $registered = array_keys($this->panel->getPermissions());
        $owned = [Panel::CORE, ...array_keys($this->panel->getPlugins())];

        $existing = Permission::query()->where('guard_name', $guard)->get()->map(fn (Permission $permission): string => $permission->name)->all();

        $created = array_values(array_diff($registered, $existing));
        foreach ($created as $name) {
            Permission::query()->create(['name' => $name, 'guard_name' => $guard]);
        }

        $deleted = array_values(array_filter(
            array_diff($existing, $registered),
            fn (string $name) => in_array(explode('.', $name, 2)[0], $owned, true),
        ));
        if ($deleted !== []) {
            Permission::query()->where('guard_name', $guard)->whereIn('name', $deleted)->delete();
        }

        Role::findOrCreate(self::superAdminRole(), $guard);
        $this->registrar->forgetCachedPermissions();

        return ['created' => $created, 'deleted' => $deleted];
    }

    public static function superAdminRole(): string
    {
        $role = config('sunrice-admin.super_admin_role', 'super-admin');

        return is_string($role) && $role !== '' ? $role : 'super-admin';
    }
}
