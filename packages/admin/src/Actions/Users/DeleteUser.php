<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Users;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Sunrice\Admin\Actions\Ai\RevokeAiTokens;
use Sunrice\Admin\Locks\KeptEdit;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Preferences\Preferences;
use Sunrice\Admin\Resources\Tables\ActionFailed;
use Sunrice\Admin\Security\AccountSecurity;

/**
 * Deletes a user and decides what happens to what they own (what the
 * panel and its plugins registered with `$panel->userContent()`: uploads,
 * the CMS's entries and comments …), so nothing is left pointing at a
 * user who is gone:
 *
 * - `reassign`: it moves to another user (`$heir`);
 * - `keep`: it stays, without an owner;
 * - `delete`: it is deleted, as each kind sees fit (entries go to the
 *   trash; uploads stay without an uploader, since other content may use
 *   them).
 *
 * Their AI access tokens, lock state, kept edits and preferences go too. Authorize
 * first (UserResource: nobody deletes themselves, and only users strictly
 * below them).
 *
 *     app(DeleteUser::class)->handle($user, DeleteUser::REASSIGN, $heir);
 *
 * @api
 */
final class DeleteUser
{
    public const REASSIGN = 'reassign';

    public const KEEP = 'keep';

    public const DELETE = 'delete';

    public const MODES = [self::REASSIGN, self::KEEP, self::DELETE];

    public function __construct(private Panel $panel, private RevokeAiTokens $tokens) {}

    /**
     * What users may own, as registered: "uploads", "entries" …
     *
     * @return list<string>
     */
    public function labels(): array
    {
        return array_values(array_map(fn ($content) => $content->label, $this->panel->getUserContent()));
    }

    /**
     * How much of each kind this user owns, leaving out what they have none of.
     *
     * @return array<string, int> label => count
     */
    public function counts(Model $user): array
    {
        $counts = [];
        foreach ($this->panel->getUserContent() as $content) {
            $count = ($content->count)($user);
            if ($count > 0) {
                $counts[$content->label] = $count;
            }
        }

        return $counts;
    }

    /**
     * @param  string  $mode  reassign, keep or delete (the constants)
     * @return array<string, int> what was dealt with: label => count
     *
     * @throws ActionFailed when reassigning without another user to give it to
     */
    public function handle(Model $user, string $mode = self::KEEP, ?Model $heir = null): array
    {
        if (! in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException("DeleteUser: the mode is reassign, keep or delete, not \"{$mode}\".");
        }
        if ($mode === self::REASSIGN && ($heir === null || $heir->is($user))) {
            throw new ActionFailed(__('Choose another user to give their content to.'));
        }
        $heir = $mode === self::REASSIGN ? $heir : null;
        $counts = $this->counts($user);

        DB::transaction(function () use ($user, $mode, $heir): void {
            foreach ($this->panel->getUserContent() as $content) {
                ($content->handle)($user, $mode, $heir);
            }
            $this->tokens->all($user);
            $id = $user->getKey();
            $id = is_scalar($id) ? (string) $id : '';
            AccountSecurity::query()->where('user_id', $id)->delete();
            KeptEdit::query()->where('user_id', $id)->delete();
            app(Preferences::class)->forgetUser($id);
            $user->delete();
        });

        return $counts;
    }
}
