<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai;

/**
 * The AI access token of this request, if any: whether the request may
 * only read. Set by AuthenticateAiToken.
 *
 * @internal
 */
final class TokenScope
{
    public ?AiToken $token = null;

    public function readOnly(): bool
    {
        return $this->token !== null && $this->token->read_only;
    }

    /**
     * Whether a read-only token refuses this ability: anything but viewing.
     *
     * @param  list<string>  $registered  the panel's permission names
     */
    public function refuses(string $ability, array $registered): bool
    {
        if (! $this->readOnly()) {
            return false;
        }
        if (in_array($ability, ['create', 'update', 'delete', 'restore', 'forceDelete', 'updateAny', 'deleteAny'], true)) {
            return true;
        }

        return in_array($ability, $registered, true) && ! str_ends_with($ability, '.view') && ! in_array($ability, ['sunrice.access', 'sunrice.ai.connect'], true);
    }
}
