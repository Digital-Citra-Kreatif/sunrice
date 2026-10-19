<?php

declare(strict_types=1);

namespace Sunrice\Tests\Admin;

use Orchestra\Testbench\TestCase;
use Sunrice\Admin\AdminServiceProvider;

/** A Laravel app with the admin panel and nothing else. */
abstract class AdminTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [AdminServiceProvider::class];
    }
}
