<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Permissions;

use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Sunrice\Admin\Ai\DeveloperTools;
use Sunrice\Admin\Panel;

/**
 * Creates the default roles the panel and its plugins declare
 * ($panel->defaultRole()), or gives them the permissions they're missing.
 * It only adds: permissions taken from these roles in the panel stay off.
 *
 * @api
 */
final class SeedRoles
{
    public function __construct(private Panel $panel, private SyncPermissions $sync, private PermissionRegistrar $registrar) {}

    /** @return array<string, list<string>> role => the permissions it was given */
    public function handle(): array
    {
        $this->sync->handle();
        $guard = $this->panel->getGuard();
        $all = array_keys($this->panel->getPermissions());
        $given = [];
        foreach ($this->panel->getDefaultRoles() as $name => $patterns) {
            // The developer tools' permissions run code on the server: only by name.
            $wanted = array_values(array_filter($all, fn (string $permission): bool => in_array($permission, DeveloperTools::PERMISSIONS, true)
                ? in_array($permission, $patterns, true)
                : Str::is($patterns, $permission)));
            $role = Role::findOrCreate($name, $guard);
            $has = [];
            foreach ($role->permissions as $permission) {
                $permissionName = $permission->getAttribute('name');
                if (is_string($permissionName)) {
                    $has[] = $permissionName;
                }
            }
            $missing = array_values(array_diff($wanted, $has));
            if ($missing !== []) {
                $role->givePermissionTo($missing);
            }
            $given[$name] = $missing;
        }
        $this->registrar->forgetCachedPermissions();

        return $given;
    }
}
