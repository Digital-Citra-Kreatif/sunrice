<?php

declare(strict_types=1);

namespace Sunrice\Admin\Users;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Traits\HasRoles;
use Sunrice\Admin\Actions\Ai\RevokeAiTokens;
use Sunrice\Admin\Actions\Users\DeleteUser;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\CheckboxList;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\Password;
use Sunrice\Admin\Resources\Forms\Section;
use Sunrice\Admin\Resources\Forms\Select;
use Sunrice\Admin\Resources\Forms\TextInput;
use Sunrice\Admin\Resources\Resource;
use Sunrice\Admin\Resources\Tables\Action;
use Sunrice\Admin\Resources\Tables\ActionFailed;
use Sunrice\Admin\Resources\Tables\Column;
use Sunrice\Admin\Resources\Tables\Filter;
use Sunrice\Admin\Resources\Tables\Table;
use Sunrice\Admin\Security\AccountGuard;
use Sunrice\Admin\Security\SecurityLog;

/**
 * The panel's Users screen: the users of the panel's guard, their
 * passwords and roles. Its permissions are "sunrice.users.view" …; on top
 * of them, PermissionRank decides whom each user may manage and which
 * roles they may give out. Everyone may edit their own name, email and
 * password; nobody can delete their own account or change their own
 * roles here.
 *
 * Deleting a user goes through DeleteUser: the row's Delete asks what
 * happens to what they own; the edit page's and bulk delete keep it
 * without an owner.
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
            Section::make('Roles')->description('What the user may do in the panel. You can give out roles with less access than yours, and together they must leave the user with less than yours.')->schema([
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
                Column::make('locked')->state(fn (Model $user) => app(AccountGuard::class)->isLocked($user))->boolean(),
                Column::make('created_at')->label('Created')->date()->sortable(),
            ])
            ->filters([
                Filter::make('role')->options(fn () => static::roles()->mapWithKeys(fn (Model $role) => [static::name($role) => static::name($role)])->all())
                    ->query(fn (Builder $query, string $role) => $query->whereRelation('roles', 'name', $role)),
            ])
            ->actions([
                Action::make('sign-out')->label('Sign out everywhere')
                    ->confirm('End all of this user\'s sessions? They can log in again with their password.')
                    ->handle(function (Model $user): string {
                        $deleted = app(AccountGuard::class)->signOutEverywhere($user);

                        return 'Signed out everywhere'.($deleted === null ? '.' : " ({$deleted} ".($deleted === 1 ? 'session' : 'sessions').' ended).');
                    }),
                Action::make('revoke-tokens')->label('Revoke AI tokens')
                    ->confirm('Revoke all of this user\'s AI access tokens? AI agents using them stop working at once.')
                    ->handle(function (Model $user): string {
                        $count = app(RevokeAiTokens::class)->all($user);

                        return $count === 0 ? 'This user had no AI access tokens.' : "{$count} AI access ".($count === 1 ? 'token' : 'tokens').' revoked.';
                    }),
                Action::make('lock')->label('Lock account')->destructive()
                    ->confirm('Lock this account? They are signed out everywhere and can\'t log in until someone unlocks it. Nothing is deleted.')
                    ->visible(fn (Model $user) => ! app(AccountGuard::class)->isLocked($user) && ! static::isCurrentUser($user))
                    ->handle(function (Model $user): string {
                        if (static::isCurrentUser($user)) {
                            throw new ActionFailed('You can\'t lock your own account.');
                        }
                        app(AccountGuard::class)->lock($user);

                        return 'Account locked.';
                    }),
                Action::make('unlock')->label('Unlock account')
                    ->visible(fn (Model $user) => app(AccountGuard::class)->isLocked($user))
                    ->handle(function (Model $user): string {
                        app(AccountGuard::class)->unlock($user);

                        return 'Account unlocked.';
                    }),
                static::deleteAction(),
            ])
            ->defaultSort('name');
    }

    /** The row's Delete: it replaces the built-in one to ask what happens to what the user owns. */
    protected static function deleteAction(): Action
    {
        $owned = app(DeleteUser::class)->labels();

        return Action::make('delete')->label('Delete…')->ability('delete')->destructive()
            ->confirm('Delete this user? This can\'t be undone.'.($owned === [] ? '' : ' Choose what happens to their '.self::list($owned).'.'))
            ->form(
                Select::make('content')->label('Their content')->required()->default(DeleteUser::KEEP)->options([
                    DeleteUser::REASSIGN => 'Give it to another user',
                    DeleteUser::KEEP => 'Keep it, without an owner',
                    DeleteUser::DELETE => 'Delete it (entries and comments go to the trash; uploads stay)',
                ]),
                Select::make('heir')->label('Give it to')->options(static::heirs())
                    ->help('Only when giving their content to another user.'),
            )
            ->handle(function (Model $user, array $input): string {
                $heir = isset($input['heir']) && is_scalar($input['heir']) ? static::model()::query()->find($input['heir']) : null;
                $mode = is_string($input['content'] ?? null) ? $input['content'] : DeleteUser::KEEP;
                $counts = app(DeleteUser::class)->handle($user, $mode, $heir);
                $what = self::list(array_map(fn (int $count, string $label) => $count.' '.($count === 1 ? Str::singular($label) : $label), $counts, array_keys($counts)));
                $heirName = $heir instanceof Model ? static::title($heir) : '';

                return static::title($user).' deleted.'.match (true) {
                    $what === '' => '',
                    $mode === DeleteUser::REASSIGN => " Their {$what} went to {$heirName}.",
                    $mode === DeleteUser::DELETE => " Their {$what} got deleted".(isset($counts['uploads']) ? ', apart from uploads, which stay without an uploader.' : '.'),
                    default => " Their {$what} have no owner now.",
                };
            });
    }

    public static function deleteRecord(Model $record): void
    {
        app(DeleteUser::class)->handle($record, DeleteUser::KEEP);
    }

    /**
     * Who may receive a deleted user's content: every user, by name.
     *
     * @return array<string, string> id => name
     */
    protected static function heirs(): array
    {
        $heirs = [];
        foreach (static::model()::query()->orderBy('name')->limit(1000)->get() as $user) {
            $key = $user->getKey();
            if (is_scalar($key)) {
                $heirs[(string) $key] = static::title($user);
            }
        }

        return $heirs;
    }

    protected static function isCurrentUser(Model $user): bool
    {
        $current = auth(app(Panel::class)->getGuard())->user();

        return $current instanceof Model && $user->is($current);
    }

    public static function can(Authorizable $user, string $ability, ?Model $record = null): bool
    {
        if ($record === null || ! $user instanceof Model) {
            return true;
        }
        if ($record->is($user)) {
            // Their own account: name, email and password, never delete.
            return $ability !== 'delete';
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

                // Together with what the user has directly, the roles must leave them strictly below the editor.
                $actor = auth(app(Panel::class)->getGuard())->user();
                if ($actor instanceof Model) {
                    $permissions = static::namesOf($user->getRelationValue('permissions'));
                    foreach ($roles as $role) {
                        $permissions = [...$permissions, ...static::namesOf($role->getRelationValue('permissions'))];
                    }
                    if (! app(PermissionRank::class)->isStrictlyBelow($actor, array_values(array_unique($permissions)))) {
                        throw ValidationException::withMessages(['roles' => 'Together these roles would give as much access as yours or more. Ask someone with more access.']);
                    }
                }

                $before = array_map(static::name(...), [...static::rolesOf($user)]);
                $user->syncRoles($roles->all()); // @phpstan-ignore method.notFound (model() checks the user model uses HasRoles)
                $after = array_map(static::name(...), [...$roles]);
                static::recordChange('roles changed', $user, $before, $after);
            })
            ->disabled(function (?Model $user): bool {
                $current = auth(app(Panel::class)->getGuard())->user();

                return $user !== null && $current instanceof Model && $user->is($current);
            })
            ->help('Nobody can change their own roles.');
    }

    /**
     * A security event when what someone may do changed, with `added` and
     * `removed` lists in its properties and a note: "added: editor; removed: author".
     *
     * @param  list<string>  $before
     * @param  list<string>  $after
     */
    protected static function recordChange(string $action, Model $subject, array $before, array $after): void
    {
        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));
        if ($added === [] && $removed === []) {
            return;
        }
        $note = implode('; ', array_filter([
            $added === [] ? null : 'added: '.implode(', ', $added),
            $removed === [] ? null : 'removed: '.implode(', ', $removed),
        ]));
        app(SecurityLog::class)->record($action, $subject, null, ['note' => $note, 'added' => $added, 'removed' => $removed]);
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

    /** @return list<string> the names of a collection of roles or permissions */
    protected static function namesOf(mixed $models): array
    {
        if (! $models instanceof Collection) {
            return [];
        }

        return array_values(array_filter($models->map(fn (Model $m) => $m->getAttribute('name'))->all(), 'is_string'));
    }

    /**
     * "a, b and c".
     *
     * @param  list<string>  $items
     */
    private static function list(array $items): string
    {
        $last = array_pop($items);

        return $last === null ? '' : ($items === [] ? $last : implode(', ', $items).' and '.$last);
    }
}
