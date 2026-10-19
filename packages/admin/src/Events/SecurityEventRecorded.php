<?php

declare(strict_types=1);

namespace Sunrice\Admin\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Sunrice\Admin\Activity\Activity;

/**
 * A security event was recorded (see SecurityLog): a login, a failed or
 * blocked one, a blocked upload, an export, a change of roles …
 *
 * @api
 */
final class SecurityEventRecorded
{
    use Dispatchable;

    /** @param array<string, mixed> $properties */
    public function __construct(
        public readonly string $action,
        public readonly ?Model $account,
        public readonly ?string $label,
        public readonly array $properties,
        public readonly ?string $ip,
        public readonly ?Activity $activity,
    ) {}
}
