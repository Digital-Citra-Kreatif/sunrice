<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
uses(CmsTestCase::class, RefreshDatabase::class)->afterEach(fn () => emptyTablesAfterFullTextCommit())->in('Cms');

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

/**
 * Site search on MySQL and MariaDB uses a FULLTEXT index, and InnoDB adds
 * rows to it when they are committed: a test that searches calls this
 * first, to commit what it wrote so far (and, from here on, as it goes),
 * instead of rolling back the transaction the suite wraps it in; the
 * tables are emptied after the test. On the other databases it does
 * nothing.
 */
function commitForFullText(): void
{
    if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) && DB::transactionLevel() > 0) {
        DB::commit();
        fullTextCommitted(true);
    }
}

/** @internal whether this test committed for FULLTEXT search */
function fullTextCommitted(?bool $committed = null): bool
{
    static $state = false;
    if ($committed !== null) {
        $state = $committed;
    }

    return $state;
}

/** @internal */
function emptyTablesAfterFullTextCommit(): void
{
    if (! fullTextCommitted()) {
        return;
    }
    fullTextCommitted(false);
    Schema::withoutForeignKeyConstraints(function (): void {
        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            if ($table !== 'migrations') {
                DB::table($table)->truncate();
            }
        }
    });
}
