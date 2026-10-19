<?php

declare(strict_types=1);

namespace Sunrice\Admin\Console;

use Illuminate\Console\Command;
use Sunrice\Admin\Actions\Permissions\SeedRoles;

/**
 * `php artisan sunrice:roles`: creates the default roles (administrator
 * and the plugins' own, like editor), or gives them new permissions.
 *
 * @internal
 */
final class SeedRolesCommand extends Command
{
    protected $signature = 'sunrice:roles';

    protected $description = 'Create the default roles, or give them the permissions they\'re missing';

    public function handle(SeedRoles $seed): int
    {
        foreach ($seed->handle() as $role => $given) {
            $this->components->twoColumnDetail($role, $given === [] ? 'up to date' : count($given).' permissions given');
        }

        return self::SUCCESS;
    }
}
