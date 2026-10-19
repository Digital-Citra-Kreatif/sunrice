<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Tables;

use RuntimeException;

/**
 * Thrown by an action's handler to stop with a message for the user:
 * `throw new ActionFailed('Only drafts can be published.')`.
 *
 * @api
 */
final class ActionFailed extends RuntimeException {}
