<?php

declare(strict_types=1);

namespace Sunrice\Admin\Users;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\CheckboxList;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\Password;
use Sunrice\Admin\Resources\Forms\Section;
use Sunrice\Admin\Resources\Forms\TextInput;
use Sunrice\Admin\Resources\Resource;
use Sunrice\Admin\Resources\Tables\Column;
use Sunrice\Admin\Resources\Tables\Filter;
use Sunrice\Admin\Resources\Tables\Table;

/**
 * The panel's Users screen: the users of the panel's guard, their
 * passwords and roles. Its permissions are "sunrice.users.view" …; on top
 * of them, PermissionRank decides whom each user may manage and which
 * roles they may give out. Nobody can delete their own account or change
 * their own roles here.
 *
 * Extend it to change the form or the list, and register yours with
 * `$panel->userResource(MyUserResource::class)`.
 *
 * @api
 */
class UserResource extends Resource
{
    public static function model(): string
    {
        $model = app(Panel::class)->getUserModel();
        if (! in_array(HasRoles::class, class_uses_recursive($model), true)) {
            throw new PanelMisconfigured("The user model {$model} needs the ".HasRoles::class.' trait for the panel\'s roles.');
        }

        return $model;
    }

    public static function key(): string
    {
        return 'users';
    }

    public static function label(): string
    {
        return 'Users';
    }

    public static function singularLabel(): string
    {
        return 'User';
    }

    public static function navigationGroup(): ?string
    {
        return 'Administration';
    }

    public static function navigationIcon(): string
    {
        return 'users';
    }

    public static function navigationSort(): int
    {
        return 900;
    }

    public static function query(): Builder
    {
        return parent::query()->with(['roles.permissions', 'permissions']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Account')->columns(2)->schema([
                TextInput::make('name')->required(),
                TextInput::make('email')->email()->required()->unique(),
                Password::make('password')->required()->span(2)
                    ->help('When editing, leave it empty to keep the current password.'),
            ]),
            Section::make('Roles')->description('What the user may do in the panel. You can give out roles whose permissions you have yourself.')->schema([
                static::rolesField(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Column::make('name')->searchable()->sortable(),
                Column::make('email')->searchable()->sortable(),
                Column::make('roles.name')->label('Roles')->badge(),
                Column::make('created_at')->label('Created')->date()->sortable(),
            ])
            ->filters([
                Filter::make('role')->options(fn () => static::roles()->mapWithKeys(fn (Model $role) => [static::name($role) => static::name($role)])->all())
                    ->query(fn (Builder $query, string $role) => $query->whereRelation('roles', 'name', $role)),
            ])
            ->defaultSort('name');
    }

    public static function can(Authorizable $user, string $ability, ?Model $record = null): bool
    {
        if ($record === null || ! $user instanceof Model) {
            return true;
        }
        if ($ability === 'delete' && $record->is($user)) {
            return false;
        }

        return app(PermissionRank::class)->canManageUser($user, $record);
    }

    /** The roles checkboxes: the roles the signed-in user may give out. */
    protected static function rolesField(): CheckboxList
    {
        return CheckboxList::make('roles')
            ->options(function (): array {
                $actor = auth(app(Panel::class)->getGuard())->user();
                $rank = app(PermissionRank::class);

                return static::roles()
                    ->filter(fn (Model $role) => $actor instanceof Model && $rank->canManageRole($actor, $role))
                    ->mapWithKeys(fn (Model $role) => [static::name($role) => static::name($role)])
                    ->all();
            })
            ->loadUsing(fn (Model $user) => array_map(static::name(...), [...static::rolesOf($user)]))
            ->saveUsing(function (Model $user, mixed $chosen): void {
                // Roles that aren't offered (another guard's, or ones out of
                // the editor's reach) stay as they are.
                $offered = static::rolesField()->values();
                $chosen = is_array($chosen) ? $chosen : [];
                $keep = static::rolesOf($user)->filter(fn (Model $role) => ! in_array(static::name($role), $offered, true));
                $roles = static::roles()->filter(fn (Model $role) => in_array(static::name($role), $chosen, true))->merge($keep);

                $user->syncRoles($roles->all()); // @phpstan-ignore method.notFound (model() checks the user model uses HasRoles)
            })
            ->disabled(function (?Model $user): bool {
                $current = auth(app(Panel::class)->getGuard())->user();

                return $user !== null && $current instanceof Model && $user->is($current);
            })
            ->help('Nobody can change their own roles.');
    }

    /**
     * The roles of the panel's guard, with their permissions.
     *
     * @return Collection<int, Model>
     */
    protected static function roles(): Collection
    {
        return RoleResource::model()::query()->where('guard_name', app(Panel::class)->getGuard())->with('permissions')->orderBy('name')->get();
    }

    /** @return Collection<int, Model> */
    protected static function rolesOf(Model $user): Collection
    {
        $roles = $user->getRelationValue('roles');

        return $roles instanceof Collection ? $roles : new Collection;
    }

    protected static function name(Model $role): string
    {
        $name = $role->getAttribute('name');

        return is_string($name) ? $name : '';
    }
}
