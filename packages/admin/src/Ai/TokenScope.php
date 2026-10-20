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
     * Whether a read-only token refuses this ability: everything but
     * viewing. Only "view", "viewAny", a permission ending in ".view",
     * opening the panel and connecting agents pass; an ability the panel
     * doesn't know (a plugin policy's "publish" or "refund") is refused
     * rather than let through.
     */
    public function refuses(string $ability): bool
    {
        if (! $this->readOnly()) {
            return false;
        }

        return ! in_array($ability, ['view', 'viewAny', 'sunrice.access', 'sunrice.ai.connect'], true) && ! str_ends_with($ability, '.view');
    }
}
