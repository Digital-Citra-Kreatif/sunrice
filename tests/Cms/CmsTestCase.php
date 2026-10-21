<?php

declare(strict_types=1);

namespace Sunrice\Tests\Cms;

use Orchestra\Testbench\TestCase;
use Spatie\Honeypot\HoneypotServiceProvider;
use Spatie\ResponseCache\ResponseCacheServiceProvider;
use Sunrice\Admin\AdminServiceProvider;
use Sunrice\Cms\CmsServiceProvider;
use Workbench\App\Models\User;

use function Orchestra\Testbench\default_migration_path;
use function Orchestra\Testbench\load_migration_paths;

/** A Laravel app with the admin panel and the CMS. */
abstract class CmsTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [AdminServiceProvider::class, CmsServiceProvider::class, HoneypotServiceProvider::class, ResponseCacheServiceProvider::class];
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
        // Kept pages live as long as the test.
        $app['config']->set('responsecache.cache.store', 'array');
    }
}
