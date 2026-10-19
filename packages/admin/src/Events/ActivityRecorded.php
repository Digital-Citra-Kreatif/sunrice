<?php

declare(strict_types=1);

namespace Sunrice\Admin\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Sunrice\Admin\Activity\Activity;

/**
 * A line was added to the activity log (not fired when "updated" lines
 * are merged into an earlier one).
 *
 * @api
 */
final class ActivityRecorded
{
    use Dispatchable;

    public function __construct(public readonly Activity $activity) {}
}
