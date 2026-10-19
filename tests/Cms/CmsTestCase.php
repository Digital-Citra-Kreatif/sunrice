<?php

declare(strict_types=1);

namespace Sunrice\Tests\Cms;

use Orchestra\Testbench\Concerns\WithLaravelMigrations;
use Orchestra\Testbench\TestCase;
use Spatie\Honeypot\HoneypotServiceProvider;
use Spatie\ResponseCache\ResponseCacheServiceProvider;
use Sunrice\Admin\AdminServiceProvider;
use Sunrice\Cms\CmsServiceProvider;
use Workbench\App\Models\User;

/** A Laravel app with the admin panel and the CMS. */
abstract class CmsTestCase extends TestCase
{
    // Laravel's users table; the packages' own migrations load themselves.
    use WithLaravelMigrations;

    protected function getPackageProviders($app): array
    {
        return [AdminServiceProvider::class, CmsServiceProvider::class, HoneypotServiceProvider::class, ResponseCacheServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('auth.providers.users.model', User::class);
        // Kept pages live as long as the test.
        $app['config']->set('responsecache.cache.store', 'array');
    }
}
