<?php

declare(strict_types=1);

namespace Sunrice\Tests\Admin;

use Orchestra\Testbench\TestCase;
use Sunrice\Admin\AdminServiceProvider;
use Workbench\App\Models\User;

use function Orchestra\Testbench\default_migration_path;
use function Orchestra\Testbench\load_migration_paths;

/** A Laravel app with the admin panel and nothing else. */
abstract class AdminTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [AdminServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('auth.providers.users.model', User::class);
        // Laravel's users, cache and jobs tables join the migrations
        // RefreshDatabase runs once per test file, instead of being
        // migrated and rolled back around every test: on MySQL that
        // rollback commits the test's transaction and forced a full
        // migrate:fresh before the next test.
        load_migration_paths($app, default_migration_path());
    }
}
