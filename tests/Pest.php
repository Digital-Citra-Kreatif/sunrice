<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Sunrice\Admin\Panel;
use Sunrice\Tests\Admin\AdminTestCase;
use Sunrice\Tests\Admin\Fixtures\TestPanelProvider;
use Sunrice\Tests\Cms\CmsTestCase;
use Workbench\App\Models\User;

/*
 * Each package's tests run against its own Testbench app: admin tests
 * load the panel alone (proving it works without the CMS), CMS tests
 * load both. Architecture tests need no app.
 */
uses(AdminTestCase::class, RefreshDatabase::class)->in('Admin');
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

/**
 * A saved user, optionally with permissions (created as needed) given
 * directly: adminUser('sunrice.access').
 */
function adminUser(string ...$permissions): User
{
    $user = User::query()->create(['name' => 'Rina', 'email' => 'rina'.uniqid().'@example.com', 'password' => 'secret-password']);
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $user->givePermissionTo($permissions);

    return $user;
}
