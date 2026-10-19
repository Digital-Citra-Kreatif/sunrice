<?php

declare(strict_types=1);

namespace Sunrice\Admin\Console;

use Illuminate\Console\Command;
use Sunrice\Admin\Activity\Activity;

/**
 * sunrice:activity-prune — delete activity log lines older than
 * `sunrice-admin.activity.keep_days` (or --days). Scheduled daily.
 *
 * @internal
 */
final class PruneActivityCommand extends Command
{
    protected $signature = 'sunrice:activity-prune {--days= : Keep this many days instead of the config\'s}';

    protected $description = 'Delete old activity log lines';

    public function handle(): int
    {
        $days = $this->option('days') ?? config('sunrice-admin.activity.keep_days');
        if (! is_numeric($days)) {
            $this->components->info('The activity log is kept forever (activity.keep_days is null).');

            return self::SUCCESS;
        }
        $deleted = Activity::prune((int) $days);
        $this->components->info("Deleted {$deleted} activity log ".($deleted === 1 ? 'line' : 'lines').' older than '.(int) $days.' days.');

        return self::SUCCESS;
    }
}
