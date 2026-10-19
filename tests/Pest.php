<?php

declare(strict_types=1);

use Sunrice\Admin\Panel;
use Sunrice\Tests\Admin\AdminTestCase;
use Sunrice\Tests\Admin\Fixtures\TestPanelProvider;
use Sunrice\Tests\Cms\CmsTestCase;

/*
 * Each package's tests run against its own Testbench app: admin tests
 * load the panel alone (proving it works without the CMS), CMS tests
 * load both. Architecture tests need no app.
 */
uses(AdminTestCase::class)->in('Admin');
uses(CmsTestCase::class)->in('Cms');

/**
 * Give the test app a panel (registered like an app's panel provider),
 * optionally configured: usePanel(fn (Panel $p) => $p->path('backend')).
 *
 * @param  (Closure(Panel): Panel)|null  $configure
 */
function usePanel(?Closure $configure = null): Panel
{
    TestPanelProvider::$configure = $configure;
    app()->register(TestPanelProvider::class);
    app('router')->getRoutes()->refreshNameLookups();

    return app(Panel::class);
}
