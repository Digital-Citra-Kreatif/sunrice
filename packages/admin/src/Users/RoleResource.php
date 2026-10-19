<?php

declare(strict_types=1);

namespace Sunrice\Admin\Users;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\CheckboxList;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\Section;
use Sunrice\Admin\Resources\Forms\TextInput;
use Sunrice\Admin\Resources\Resource;
use Sunrice\Admin\Resources\Tables\Column;
use Sunrice\Admin\Resources\Tables\Table;

/**
 * The panel's Roles screen: roles of the panel's guard and the permissions
 * the panel and its plugins register. Its permissions are
 * "sunrice.roles.view" …; on top of them, PermissionRank keeps each user
 * to roles and permissions they have themselves. The super admin role
 * can't be edited or deleted: it may do everything anyway.
 *
 * Extend it and register yours with `$panel->roleResource(MyRoleResource::class)`.
 *
 * @api
 */
class RoleResource extends Resource
{
    public static function model(): string
    {
        return static::modelClass(app(PermissionRegistrar::class)->getRoleClass());
    }

    public static function key(): string
    {
        return 'roles';
    }

    public static function label(): string
    {
        return 'Roles';
    }

    public static function singularLabel(): string
    {
        return 'Role';
    }

    public static function navigationGroup(): ?string
    {
        return 'Administration';
    }

    public static function navigationIcon(): string
    {
        return 'shield';
    }

    public static function navigationSort(): int
    {
        return 910;
    }

    public static function query(): Builder
    {
        return parent::query()->where('guard_name', app(Panel::class)->getGuard())->with('permissions')->withCount('permissions');
    }

    public static function newRecord(): Model
    {
        $role = parent::newRecord();
        $role->setAttribute('guard_name', app(Panel::class)->getGuard());

        return $role;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required()->unique()->maxLength(125),
            Section::make('Permissions')->description('What users with this role may do. You can give out permissions you have yourself.')->schema([
                static::permissionsField(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Column::make('name')->searchable()->sortable(),
                Column::make('permissions_count')->label('Permissions'),
            ])
            ->defaultSort('name');
    }

    public static function can(Authorizable $user, string $ability, ?Model $record = null): bool
    {
        if ($record === null || ! $user instanceof Model) {
            return true;
        }
        $rank = app(PermissionRank::class);

        return ! $rank->isSuperAdminRole($record) && $rank->canManageRole($user, $record);
    }

    /** The permissions checkboxes, by group: the ones the signed-in user may give out. */
    protected static function permissionsField(): CheckboxList
    {
        return CheckboxList::make('permissions')->label('Permissions')
            ->options(function (): array {
                $actor = auth(app(Panel::class)->getGuard())->user();
                $existing = static::permissions()->map(fn (Model $permission) => $permission->getAttribute('name'))->all();
                $groups = [];
                foreach (app(Panel::class)->getPermissions() as $permission) {
                    if (in_array($permission->name, $existing, true) && $actor instanceof Model && app(PermissionRank::class)->canGrantPermissions($actor, [$permission->name])) {
                        $groups[$permission->group][$permission->name] = $permission->label;
                    }
                }

                return $groups;
            })
            ->loadUsing(fn (Model $role) => static::namesOf($role->getRelationValue('permissions')))
            ->saveUsing(function (Model $role, mixed $chosen): void {
                // Permissions that aren't offered (the app's own, or ones out of
                // the editor's reach) stay as they are.
                $offered = static::permissionsField()->values();
                $chosen = is_array($chosen) ? array_filter($chosen, 'is_string') : [];
                $keep = array_diff(static::namesOf($role->getRelationValue('permissions')), $offered);
                $names = [...array_intersect($offered, $chosen), ...$keep];

                $role->syncPermissions(static::permissions()->filter(fn (Model $p) => in_array($p->getAttribute('name'), $names, true))->all()); // @phpstan-ignore method.notFound (the role model uses HasPermissions)
            });
    }

    /**
     * The permissions of the panel's guard.
     *
     * @return Collection<int, Model>
     */
    protected static function permissions(): Collection
    {
        return static::modelClass(app(PermissionRegistrar::class)->getPermissionClass())::query()->where('guard_name', app(Panel::class)->getGuard())->get();
    }

    /** @return class-string<Model> */
    protected static function modelClass(string $class): string
    {
        if (! is_a($class, Model::class, true)) {
            throw new PanelMisconfigured("{$class} (config/permission.php) isn't an Eloquent model.");
        }

        return $class;
    }

    /** @return list<string> */
    protected static function namesOf(mixed $models): array
    {
        if (! $models instanceof Collection) {
            return [];
        }

        return array_values(array_filter($models->map(fn (Model $m) => $m->getAttribute('name'))->all(), 'is_string'));
    }
}
