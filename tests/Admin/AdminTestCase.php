<?php

declare(strict_types=1);

namespace Sunrice\Tests\Admin;

use Orchestra\Testbench\Concerns\WithLaravelMigrations;
use Orchestra\Testbench\TestCase;
use Sunrice\Admin\AdminServiceProvider;
use Workbench\App\Models\User;

/** A Laravel app with the admin panel and nothing else. */
abstract class AdminTestCase extends TestCase
{
    // Laravel's users table; the panel's own migrations load themselves.
    use WithLaravelMigrations;

    protected function getPackageProviders($app): array
    {
        return [AdminServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('auth.providers.users.model', User::class);
    }
}
