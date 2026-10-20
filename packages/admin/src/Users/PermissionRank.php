<?php

declare(strict_types=1);

namespace Sunrice\Admin\Users;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;

/**
 * Who may manage whom, so nobody can give out as much as they have, let
 * alone more. Roles have no levels: a user or a role is "strictly below"
 * someone when every permission it has, they have too, and they have at
 * least one more. Side-by-side roles (an Editor and a Shop Manager, each
 * with permissions the other lacks) are below neither.
 *
 * - super admins manage everyone and every role;
 * - anyone else manages a user, or gives out or edits a role, only if it
 *   is strictly below them. Super admins and the super admin role are out
 *   of their reach.
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

    /** Whether `$actor` may edit, delete or change the roles of `$user`: the user is strictly below them. */
    public function canManageUser(Model $actor, Model $user): bool
    {
        if ($this->isSuperAdmin($actor)) {
            return true;
        }

        return ! $this->isSuperAdmin($user) && $this->isStrictlyBelow($actor, $this->permissionsOf($user));
    }

    /** Whether `$actor` may give out this role, and edit or delete it: the role is strictly below them. */
    public function canManageRole(Model $actor, Model $role): bool
    {
        if ($this->isSuperAdmin($actor)) {
            return true;
        }

        return ! $this->isSuperAdminRole($role) && $this->isStrictlyBelow($actor, $this->names($role->getRelationValue('permissions')));
    }

    /**
     * Whether `$actor` has every one of these permissions (super admins have
     * all). Having them all isn't enough to make a role or a user out of
     * them: see isStrictlyBelow().
     *
     * @param  array<mixed>  $permissions
     */
    public function canGrantPermissions(Model $actor, array $permissions): bool
    {
        return $this->isSuperAdmin($actor) || $this->covers($actor, array_values(array_filter($permissions, 'is_string')));
    }

    /**
     * Whether a set of permissions (a role's, or everything a user would
     * have) is strictly below `$actor`'s: all of them are theirs, and at
     * least one of theirs is missing. Super admins are above every set.
     *
     * @param  array<mixed>  $permissions
     */
    public function isStrictlyBelow(Model $actor, array $permissions): bool
    {
        if ($this->isSuperAdmin($actor)) {
            return true;
        }
        $permissions = array_values(array_filter($permissions, 'is_string'));

        return $this->covers($actor, $permissions) && array_diff($this->permissionsOf($actor), $permissions) !== [];
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
