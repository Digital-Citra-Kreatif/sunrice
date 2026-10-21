<?php

declare(strict_types=1);

namespace Sunrice\Tests\StarterKit;

use Orchestra\Testbench\TestCase;
use Spatie\Honeypot\HoneypotServiceProvider;
use Spatie\ResponseCache\ResponseCacheServiceProvider;
use Sunrice\Admin\AdminServiceProvider;
use Sunrice\Cms\CmsServiceProvider;
use Sunrice\StarterKit\StarterKitServiceProvider;
use Workbench\App\Models\User;

use function Orchestra\Testbench\default_migration_path;
use function Orchestra\Testbench\load_migration_paths;

/** A Laravel app with the admin panel, the CMS and the starter kit. */
abstract class StarterKitTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [AdminServiceProvider::class, CmsServiceProvider::class, HoneypotServiceProvider::class, ResponseCacheServiceProvider::class, StarterKitServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('auth.providers.users.model', User::class);
        load_migration_paths($app, default_migration_path());
    }
}
