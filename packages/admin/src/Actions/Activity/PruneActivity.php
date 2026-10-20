<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Activity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Security\SecurityLog;

/**
 * The activity log's Prune: deletes lines older than `$days` days, as
 * sunrice:activity-prune does, and records it as a "log pruned" security
 * event (which sends an alert: deleting the log can cover someone's
 * tracks). How many lines went.
 *
 * @internal
 */
final class PruneActivity
{
    public function __construct(private SecurityLog $security) {}

    public function handle(int $days, ?Model $by): int
    {
        $days = max(1, $days);
        $deleted = Activity::prune($days);
        $this->security->record('log pruned', $by, null, [
            'note' => $deleted.' '.Str::plural('line', $deleted).' older than '.$days.' '.Str::plural('day', $days),
            'days' => $days,
            'deleted' => $deleted,
        ]);

        return $deleted;
    }
}
