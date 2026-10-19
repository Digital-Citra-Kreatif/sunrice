<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Security\SecurityLog;

/**
 * Revokes AI access tokens: one, or all of a user's. AI agents using them
 * stop working at once. A security event.
 *
 * @api
 */
final class RevokeAiTokens
{
    public function __construct(private SecurityLog $security) {}

    public function one(AiToken $token, Model $owner): void
    {
        $token->delete();
        $this->security->record('token revoked', $owner, null, ['note' => "\"{$token->name}\""]);
    }

    /** All of a user's tokens; how many. */
    public function all(Model $user): int
    {
        $key = $user->getKey();
        $count = AiToken::query()->where('user_id', is_scalar($key) ? (string) $key : '')->delete();
        $count = is_int($count) ? $count : 0;
        if ($count > 0) {
            $this->security->record('tokens revoked', $user, null, ['note' => $count.' AI access '.Str::plural('token', $count)]);
        }

        return $count;
    }
}
