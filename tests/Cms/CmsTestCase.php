<?php

declare(strict_types=1);

namespace Sunrice\Tests\Cms;

use Orchestra\Testbench\TestCase;
use Sunrice\Admin\AdminServiceProvider;
use Sunrice\Cms\CmsServiceProvider;

/** A Laravel app with the admin panel and the CMS. */
abstract class CmsTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [AdminServiceProvider::class, CmsServiceProvider::class];
    }
}
