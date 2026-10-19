<?php

declare(strict_types=1);

namespace Sunrice\Admin\Console;

use Illuminate\Console\Command;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;

/**
 * sunrice:sync-permissions — create the permissions the panel and its
 * plugins register, and delete theirs that are gone. Also runs after
 * every `php artisan migrate`.
 *
 * @internal
 */
final class SyncPermissionsCommand extends Command
{
    protected $signature = 'sunrice:sync-permissions';

    protected $description = 'Create the admin panel\'s permissions and remove ones that no longer exist';

    public function handle(SyncPermissions $sync): int
    {
        $result = $sync->handle();
        foreach ($result['created'] as $name) {
            $this->components->twoColumnDetail($name, '<fg=green>created</>');
        }
        foreach ($result['deleted'] as $name) {
            $this->components->twoColumnDetail($name, '<fg=yellow>deleted</>');
        }
        $this->components->info(sprintf('Permissions in sync (%d created, %d deleted).', count($result['created']), count($result['deleted'])));

        return self::SUCCESS;
    }
}
