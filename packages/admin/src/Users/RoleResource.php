<?php

declare(strict_types=1);

namespace Sunrice\Admin\Users;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\CheckboxList;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\Section;
use Sunrice\Admin\Resources\Forms\TextInput;
use Sunrice\Admin\Resources\Resource;
use Sunrice\Admin\Resources\Tables\ActionFailed;
use Sunrice\Admin\Resources\Tables\Column;
use Sunrice\Admin\Resources\Tables\Table;
use Sunrice\Admin\Security\SecurityLog;

/**
 * The panel's Roles screen: roles of the panel's guard and the permissions
 * the panel and its plugins register. Its permissions are
 * "sunrice.roles.view" …; on top of them, PermissionRank keeps each user
 * to roles strictly below their own access. The super admin role can't be
 * edited or deleted: it may do everything anyway. A role still given to
 * users can't be deleted.
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
            TextInput::make('name')->required()->unique()->maxLength(125)
                ->warnWhenChanged('Users keep this role and its permissions. Code that checks it by name (hasRole(\':old\'), @role(\':old\')) needs the new one, and if ":old" is one of the panel\'s default roles, sunrice:roles creates it again.'),
            Section::make('Permissions')->description('What users with this role may do. You can give out permissions you have yourself, and a role must have less access than yours.')->schema([
                static::permissionsField(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Column::make('name')->searchable()->sortable()->format(fn (string|int|float|bool|null $name): string|int|float|bool|null => is_string($name) ? static::displayName($name) : $name),
                // The super admin role has no permissions of its own: it may do everything.
                Column::make('permissions_count')->label('Permissions')
                    ->state(fn (Model $role): string|int => app(PermissionRank::class)->isSuperAdminRole($role) ? (is_string($all = __('All')) ? $all : 'All') : (is_numeric($count = $role->getAttribute('permissions_count')) ? (int) $count : 0)),
            ])
            ->defaultSort('name');
    }

    /**
     * A role's name as people read it: a slug like "super-admin" becomes
     * "Super admin"; a name with capitals or spaces stays as it is.
     */
    public static function displayName(string $name): string
    {
        return preg_match('/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', $name) === 1 ? Str::ucfirst(str_replace(['-', '_'], ' ', $name)) : $name;
    }

    public static function title(Model $record): string
    {
        $name = $record->getAttribute('name');

        return is_string($name) && $name !== '' ? static::displayName($name) : parent::title($record);
    }

    public static function can(Authorizable $user, string $ability, ?Model $record = null): bool
    {
        if ($record === null || ! $user instanceof Model) {
            return true;
        }
        $rank = app(PermissionRank::class);

        return ! $rank->isSuperAdminRole($record) && $rank->canManageRole($user, $record);
    }

    /** Refuses while users still have the role: it would silently take their access away. */
    public static function deleteRecord(Model $record): void
    {
        $users = static::usersWith($record);
        if ($users > 0) {
            throw new ActionFailed($users === 1
                ? __('The role ":role" is still given to 1 user. Take it away from them first.', ['role' => static::title($record)])
                : __('The role ":role" is still given to :count users. Take it away from them first.', ['role' => static::title($record), 'count' => $users]));
        }
        $record->delete();
    }

    /** How many models (of any type) have the role. */
    protected static function usersWith(Model $role): int
    {
        $table = config('permission.table_names.model_has_roles');
        $roleKey = config('permission.column_names.role_pivot_key');

        return DB::table(is_string($table) ? $table : 'model_has_roles')
            ->where(is_string($roleKey) ? $roleKey : 'role_id', $role->getKey())
            ->count();
    }

    /** The permissions checkboxes, by group: the ones the signed-in user may give out. */
    protected static function permissionsField(): CheckboxList
    {
        return CheckboxList::make('permissions')->label('Permissions')->matrix()
            ->options(function (): array {
                $actor = auth(app(Panel::class)->getGuard())->user();
                $existing = static::permissions()->map(fn (Model $permission) => $permission->getAttribute('name'))->all();
                $groups = [];
                foreach (app(Panel::class)->getPermissions() as $permission) {
                    if (in_array($permission->name, $existing, true) && $actor instanceof Model && app(PermissionRank::class)->canGrantPermissions($actor, [$permission->name])) {
                        $groups[$permission->group][$permission->name] = $permission->saidLabel();
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
                $names = array_values(array_unique([...array_intersect($offered, $chosen), ...$keep]));

                // Nobody makes a role equal to or above their own access.
                $actor = auth(app(Panel::class)->getGuard())->user();
                if ($actor instanceof Model && ! app(PermissionRank::class)->isStrictlyBelow($actor, $names)) {
                    throw ValidationException::withMessages(['permissions' => __('A role must have less access than yours: leave out at least one of your permissions.')]);
                }

                $before = static::namesOf($role->getRelationValue('permissions'));
                $role->syncPermissions(static::permissions()->filter(fn (Model $p) => in_array($p->getAttribute('name'), $names, true))->all()); // @phpstan-ignore method.notFound (the role model uses HasPermissions)
                $added = array_values(array_diff($names, $before));
                $removed = array_values(array_diff($before, $names));
                if ($added !== [] || $removed !== []) {
                    $note = implode('; ', array_filter([
                        $added === [] ? null : 'added: '.implode(', ', $added),
                        $removed === [] ? null : 'removed: '.implode(', ', $removed),
                    ]));
                    $name = $role->getAttribute('name');
                    app(SecurityLog::class)->about('permissions changed', 'roles', $role, is_string($name) ? $name : null, ['note' => $note, 'added' => $added, 'removed' => $removed]);
                }
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
