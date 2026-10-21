<?php

declare(strict_types=1);

namespace Sunrice\Admin\Console;

use Illuminate\Console\Command;
use Sunrice\Admin\Actions\Permissions\SeedRoles;

/**
 * `php artisan sunrice:roles`: creates the default roles (administrator
 * and the plugins' own, like editor), or gives them new permissions, and
 * lists what they have beyond their defaults (never taking it away).
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
        // Only said: what a role has beyond its default may be on purpose.
        $extras = $seed->extras();
        if ($extras !== []) {
            $this->newLine();
            $this->line('These roles have permissions their default no longer gives (kept: take them away in Administration → Roles if they\'re left over from an older default):');
            foreach ($extras as $role => $permissions) {
                $this->components->twoColumnDetail($role, implode(', ', $permissions));
            }
        }

        return self::SUCCESS;
    }
}
