<?php

declare(strict_types=1);

namespace Sunrice\Admin\Users;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;

/**
 * Who may manage whom, so nobody can give out more than they have:
 *
 * - super admins manage everyone and every role;
 * - anyone else manages a user, or gives out or edits a role, only if
 *   every permission it has is one they have too. Super admins and the
 *   super admin role are out of their reach.
 *
 * It reads users' and roles' `roles` and `permissions` relations (eager
 * load them for lists: `with(['roles.permissions', 'permissions'])`).
 *
 * @api
 */
final class PermissionRank
{
    public function isSuperAdmin(Model $user): bool
    {
        return in_array(SyncPermissions::superAdminRole(), $this->roleNames($user), true);
    }

    public function isSuperAdminRole(Model $role): bool
    {
        return $role->getAttribute('name') === SyncPermissions::superAdminRole();
    }

    public function canManageUser(Model $actor, Model $user): bool
    {
        if ($this->isSuperAdmin($actor)) {
            return true;
        }

        return ! $this->isSuperAdmin($user) && $this->covers($actor, $this->permissionsOf($user));
    }

    /** Whether `$actor` may give out this role, and edit or delete it. */
    public function canManageRole(Model $actor, Model $role): bool
    {
        if ($this->isSuperAdmin($actor)) {
            return true;
        }

        return ! $this->isSuperAdminRole($role) && $this->covers($actor, $this->names($role->getRelationValue('permissions')));
    }

    /**
     * Whether `$actor` has every one of these permissions (super admins have all).
     *
     * @param  array<mixed>  $permissions
     */
    public function canGrantPermissions(Model $actor, array $permissions): bool
    {
        return $this->isSuperAdmin($actor) || $this->covers($actor, array_values(array_filter($permissions, 'is_string')));
    }

    /**
     * Every permission the user has, directly or through a role.
     *
     * @return list<string>
     */
    public function permissionsOf(Model $user): array
    {
        $roles = $user->getRelationValue('roles');
        $viaRoles = [];
        if ($roles instanceof Collection) {
            foreach ($roles as $role) {
                $viaRoles = [...$viaRoles, ...$this->names($role->getRelationValue('permissions'))];
            }
        }

        return array_values(array_unique([...$this->names($user->getRelationValue('permissions')), ...$viaRoles]));
    }

    /** @param list<string> $permissions */
    private function covers(Model $actor, array $permissions): bool
    {
        return array_diff($permissions, $this->permissionsOf($actor)) === [];
    }

    /** @return list<string> */
    private function roleNames(Model $user): array
    {
        return $this->names($user->getRelationValue('roles'));
    }

    /** @return list<string> the names of a collection of roles or permissions */
    private function names(mixed $models): array
    {
        if (! $models instanceof Collection) {
            return [];
        }
        $names = [];
        foreach ($models as $model) {
            $name = $model->getAttribute('name');
            if (is_string($name)) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
