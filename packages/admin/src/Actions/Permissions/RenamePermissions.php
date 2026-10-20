<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Permissions;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Sunrice\Admin\Panel;

/**
 * Renames stored permissions in place, so every role and user that has one
 * keeps it under its new name: for permissions named after data that was
 * renamed (the CMS's "cms.entries.{collection}.view" when a collection's
 * handle changes). Call it before anything syncs permissions, and flush
 * the panel's resolved permissions (`Panel::flushPermissions()`) after.
 * A permission whose new name is already stored is left alone.
 *
 * @api
 */
final class RenamePermissions
{
    public function __construct(private Panel $panel, private PermissionRegistrar $registrar) {}

    /**
     * @param  array<string, string>  $names  old name => new name
     * @return list<string> the old names that were renamed
     */
    public function handle(array $names): array
    {
        $guard = $this->panel->getGuard();
        $names = array_filter($names, fn (string $new, string $old): bool => $new !== $old, ARRAY_FILTER_USE_BOTH);
        if ($names === []) {
            return [];
        }
        $taken = Permission::query()->where('guard_name', $guard)->whereIn('name', array_values($names))->pluck('name')->all();

        $renamed = [];
        foreach (Permission::query()->where('guard_name', $guard)->whereIn('name', array_keys($names))->get() as $permission) {
            $new = $names[$permission->name];
            if (in_array($new, $taken, true)) {
                continue;
            }
            $renamed[] = $permission->name;
            $permission->name = $new;
            $permission->save();
        }
        if ($renamed !== []) {
            $this->registrar->forgetCachedPermissions();
        }

        return $renamed;
    }
}
