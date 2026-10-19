<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Security\SecurityLog;

/**
 * Creates an AI access token for a user: the record and the plain token,
 * to show once. A security event.
 *
 * @api
 */
final class CreateAiToken
{
    public function __construct(private SecurityLog $security) {}

    /** @return array{0: AiToken, 1: string} */
    public function handle(Model $user, string $name, bool $readOnly = false, ?Carbon $expiresAt = null): array
    {
        $key = $user->getKey();
        [$token, $plain] = AiToken::issue(is_scalar($key) ? (string) $key : '', $name, $readOnly, $expiresAt);
        $this->security->record('token created', $user, null, ['note' => "\"{$token->name}\"".($readOnly ? ', read-only' : '').($expiresAt === null ? '' : ', expires '.$expiresAt->toDateString())]);

        return [$token, $plain];
    }
}
