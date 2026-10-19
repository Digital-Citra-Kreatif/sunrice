<?php

declare(strict_types=1);

namespace Sunrice\Admin\Users;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;
use Sunrice\Admin\Actions\Ai\RevokeAiTokens;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\CheckboxList;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\Password;
use Sunrice\Admin\Resources\Forms\Section;
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
                Password::make('password')->required()->confirmed()->span(2)
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
            ])
            ->defaultSort('name');
    }

    /** The account's security, and what it owns in the panel and its plugins. */
    public static function aside(Model $record): array
    {
        $id = $record->getKey();
        $id = is_scalar($id) ? (string) $id : '';
        $facts = [];
        $login = Activity::query()->where('source', 'security')->where('action', 'logged in')
            ->where('subject_type', 'account')->where('subject_id', $id)->latest('id')->first(['created_at', 'ip_address']);
        $facts[] = ['label' => 'Last login', 'value' => $login?->created_at === null ? 'Never' : $login->created_at->diffForHumans().($login->ip_address !== null ? " from {$login->ip_address}" : '')];
        $state = app(AccountGuard::class)->state($record);
        if ($state?->locked_at !== null) {
            $facts[] = ['label' => 'Locked', 'value' => $state->locked_at->diffForHumans().($state->locked_by !== null ? " by {$state->locked_by}" : '').($state->locked_reason !== null ? ": {$state->locked_reason}" : '')];
        }
        if ($state?->signed_out_at !== null) {
            $facts[] = ['label' => 'Signed out everywhere', 'value' => $state->signed_out_at->diffForHumans()];
        }
        $tokens = AiToken::query()->where('user_id', $id)->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count();
        if ($tokens > 0) {
            $facts[] = ['label' => 'AI access tokens', 'value' => (string) $tokens];
        }
        $owns = [];
        foreach (app(Panel::class)->getOwnedContent() as $content) {
            $count = $content->countFor($record);
            if ($count > 0) {
                $owns[] = $content->describe($count);
            }
        }
        $facts[] = ['label' => 'Owns', 'value' => $owns === [] ? 'Nothing yet' : implode(', ', $owns)];

        return $facts;
    }

    /**
     * Deleted, after handing what they own to whoever deletes them, so
     * nothing is left without an owner.
     */
    public static function deleteRecord(Model $record, Authorizable $by): void
    {
        if ($by instanceof Model && ! $by->is($record)) {
            $handed = [];
            foreach (app(Panel::class)->getOwnedContent() as $content) {
                $count = $content->countFor($record);
                if ($count > 0) {
                    $content->handOverFrom($record, $by);
                    $handed[] = $content->describe($count);
                }
            }
            if ($handed !== []) {
                $name = $by->getAttribute('name');
                app(ActivityLog::class)->record('handed over', 'users', $record, static::title($record), ['note' => implode(', ', $handed).' to '.(is_string($name) ? $name : 'the one who deleted them')]);
            }
        }
        $record->delete();
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
     * A security event when what someone may do changed: "added: editor; removed: author".
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
        app(SecurityLog::class)->record($action, $subject, null, ['note' => $note]);
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
