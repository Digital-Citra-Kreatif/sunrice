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
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Auth\LoginThrottle;
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
use Sunrice\Admin\Support\Card;
use Sunrice\Admin\Support\Say;

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
                    ->help('When editing: leave empty to keep it.'),
            ]),
            Section::make('Roles')->description('What this person can do in the panel.')->schema([
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

                        return match (true) {
                            $deleted === null => __('Signed out everywhere.'),
                            $deleted === 1 => __('Signed out everywhere (1 session ended).'),
                            default => __('Signed out everywhere (:count sessions ended).', ['count' => $deleted]),
                        };
                    }),
                Action::make('revoke-tokens')->label('Revoke AI tokens')
                    ->confirm('Revoke all of this user\'s AI access tokens? AI agents using them stop working at once.')
                    ->handle(function (Model $user): string {
                        $count = app(RevokeAiTokens::class)->all($user);

                        return match ($count) {
                            0 => __('This user had no AI access tokens.'),
                            1 => __('1 AI access token revoked.'),
                            default => __(':count AI access tokens revoked.', ['count' => $count]),
                        };
                    }),
                Action::make('lock')->label('Lock account')->destructive()
                    ->confirm('Lock this account? They are signed out everywhere and can\'t log in until someone unlocks it. Nothing is deleted.')
                    ->form(TextInput::make('reason')->label('Reason (optional)')->maxLength(250)->placeholder('e.g. Password may have leaked'))
                    ->visible(fn (Model $user) => ! app(AccountGuard::class)->isLocked($user) && ! static::isCurrentUser($user))
                    ->handle(function (Model $user, array $input): string {
                        if (static::isCurrentUser($user)) {
                            throw new ActionFailed(__('You can\'t lock your own account.'));
                        }
                        app(AccountGuard::class)->lock($user, is_string($input['reason'] ?? null) ? $input['reason'] : null);

                        return __('Account locked.');
                    }),
                Action::make('unlock')->label('Unlock account')
                    ->visible(fn (Model $user) => app(AccountGuard::class)->isLocked($user))
                    ->handle(function (Model $user): string {
                        app(AccountGuard::class)->unlock($user);

                        return __('Account unlocked.');
                    }),
                static::deleteAction(),
            ])
            ->defaultSort('name');
    }

    /**
     * The Security card: lock state, wrong-password lock, sign-outs, AI
     * tokens, the last login and the latest security events. Four queries.
     */
    public static function aside(Model $record, Authorizable $user): array
    {
        $id = (string) (is_scalar($record->getKey()) ? $record->getKey() : '');
        $state = app(AccountGuard::class)->state($record);
        $email = $record->getAttribute('email');
        $passwordLock = is_string($email) ? app(LoginThrottle::class)->accountLockedFor($email) : 0;
        $events = Activity::query()->where('source', 'security')->where('subject_type', 'account')->where('subject_id', $id)
            ->where('action', '!=', 'alert sent')
            ->orderByDesc('id')->limit(8)->get();
        $lastLogin = Activity::query()->where('source', 'security')->where('subject_type', 'account')->where('subject_id', $id)
            ->where('action', 'logged in')->orderByDesc('id')->first();

        $card = Card::make('Security')->description('If this account may be in the wrong hands: sign it out everywhere, revoke its AI tokens or lock it from the users list.');
        if ($state?->locked_at !== null) {
            $card->notice(
                $state->locked_by === null
                    ? __('Locked :date', ['date' => $state->locked_at->toDayDateTimeString()])
                    : __('Locked :date by :name', ['date' => $state->locked_at->toDayDateTimeString(), 'name' => $state->locked_by]),
                Card::DANGER,
                trim(($state->locked_reason === null ? '' : $state->locked_reason.' · ').__('This account can\'t log in, open the panel or use its AI tokens until it\'s unlocked.')),
            );
        } elseif ($passwordLock > 0) {
            $minutes = (int) ceil($passwordLock / 60);
            $card->notice('Locked after too many wrong passwords', Card::WARNING, $minutes === 1
                ? __('It unlocks by itself in 1 minute.')
                : __('It unlocks by itself in :count minutes.', ['count' => $minutes]));
        }

        $card->rows([
            'Last login' => match (true) {
                $lastLogin === null => __('No login recorded'),
                $lastLogin->ip_address === null => $lastLogin->created_at?->toDayDateTimeString() ?? '',
                default => __(':date from :ip', ['date' => $lastLogin->created_at?->toDayDateTimeString() ?? '', 'ip' => $lastLogin->ip_address]),
            },
            'AI access tokens' => AiToken::query()->where('user_id', $id)->count(),
            'Signed out everywhere' => $state?->signed_out_at,
        ]);
        $card->list('Latest security events', $events->map(fn (Activity $event) => [
            'label' => Say::it(Str::ucfirst($event->action)),
            'meta' => implode(' · ', array_filter([
                is_string($event->properties['note'] ?? null) ? $event->properties['note'] : null,
                $event->user_id !== null && $event->user_id !== $id && $event->user_name !== null ? __('by :name', ['name' => $event->user_name]) : null,
                $event->ip_address === null ? null : 'IP '.$event->ip_address,
            ], fn (mixed $part): bool => is_string($part) && $part !== '')) ?: null,
            'at' => $event->created_at,
        ]));
        // The list's row actions, which check the right to update this user again.
        $actions = '/'.trim(route('sunrice.admin.users.index', [], false), '/')."/{$id}/actions";
        $card->button('Sign out everywhere', "{$actions}/sign-out", 'End all of this user\'s sessions? They can log in again with their password.');
        $card->button('Revoke AI tokens', "{$actions}/revoke-tokens", 'Revoke all of this user\'s AI access tokens? AI agents using them stop working at once.');
        if ($user->can('sunrice.activity.view')) {
            $card->link('See all in the activity log', route('sunrice.admin.activity.index', ['type' => 'account', 'subject' => $id], false));
        }

        return [$card];
    }

    /** The row's Delete: it replaces the built-in one to ask what happens to what the user owns. */
    protected static function deleteAction(): Action
    {
        $owned = app(DeleteUser::class)->labels();

        return Action::make('delete')->label('Delete…')->ability('delete')->destructive()
            ->confirm($owned === [] ? 'Delete this user? This can\'t be undone.'
                : __('Delete this user? This can\'t be undone. Choose what happens to their :content.', ['content' => self::list(array_map(Say::it(...), $owned))]))
            ->form(
                Select::make('content')->label('Their content')->required()->default(DeleteUser::KEEP)->options([
                    DeleteUser::REASSIGN => 'Give it to another user',
                    DeleteUser::KEEP => 'Keep it, without an owner',
                    DeleteUser::DELETE => 'Delete it (entries and comments go to the trash; uploads stay)',
                ]),
                Select::make('heir')->label('Give it to')->options(static fn (): array => static::heirs())
                    ->help('Only when giving their content to another user.'),
            )
            ->handle(function (Model $user, array $input): string {
                $heir = isset($input['heir']) && is_scalar($input['heir']) ? static::model()::query()->find($input['heir']) : null;
                $mode = is_string($input['content'] ?? null) ? $input['content'] : DeleteUser::KEEP;
                $counts = app(DeleteUser::class)->handle($user, $mode, $heir);
                $what = self::list(array_map(fn (int $count, string $label) => $count.' '.Say::it($count === 1 ? Str::singular($label) : $label), $counts, array_keys($counts)));
                $heirName = $heir instanceof Model ? static::title($heir) : '';
                $replace = ['title' => static::title($user), 'content' => $what, 'heir' => $heirName];

                return match (true) {
                    $what === '' => __(':title deleted.', $replace),
                    $mode === DeleteUser::REASSIGN => __(':title deleted. Their :content went to :heir.', $replace),
                    $mode === DeleteUser::DELETE && isset($counts['uploads']) => __(':title deleted. Their :content got deleted, apart from uploads, which stay without an uploader.', $replace),
                    $mode === DeleteUser::DELETE => __(':title deleted. Their :content got deleted.', $replace),
                    default => __(':title deleted. Their :content have no owner now.', $replace),
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
                    ->mapWithKeys(fn (Model $role) => [static::name($role) => Str::headline(static::name($role))])
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
                        throw ValidationException::withMessages(['roles' => __('Together these roles would give as much access as yours or more. Ask someone with more access.')]);
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
            ->help('You can give roles with less access than your own. Nobody can change their own roles.');
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

        return $last === null ? '' : ($items === [] ? $last : __(':items and :last', ['items' => implode(', ', $items), 'last' => $last]));
    }
}
