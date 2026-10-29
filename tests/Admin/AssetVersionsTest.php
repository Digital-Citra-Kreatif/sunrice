<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Assets\AdminAssets;
use Sunrice\Admin\Assets\PluginBundle;
use Sunrice\Admin\Panel;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\withHeaders;

/*
 * Plugin bundles' URLs and the Inertia version follow the files the
 * browser downloads (the published copies), looked at once per request.
 */

beforeEach(function () {
    $this->public = sys_get_temp_dir().'/sunrice-public-'.uniqid();
    $this->dist = sys_get_temp_dir().'/sunrice-dist-'.uniqid();
    mkdir($this->public);
    mkdir($this->dist);
    app()->usePublicPath($this->public);
});

afterEach(function () {
    foreach ([$this->public.'/vendor/shop', $this->public.'/vendor/sunrice-admin'] as $link) {
        if (is_link($link)) {
            unlink($link);
        }
    }
    (new Filesystem)->deleteDirectory($this->public);
    (new Filesystem)->deleteDirectory($this->dist);
});

/** The shop's bundle, its files named in its published folder. */
function shopBundle(string $dist, string $public): PluginBundle
{
    return PluginBundle::make('shop')->named('Acme Shop')->publishedFrom($dist, $public.'/vendor/shop')
        ->module('admin.js')->style('admin.css')->pages('Shop/');
}

/** A new request, as Octane or a fresh PHP process would start it: the per-request versions are forgotten. */
function nextRequest(): void
{
    app()->forgetScopedInstances();
}

function versionOf(string $content): string
{
    return '?v='.substr(md5($content), 0, 12);
}

it('versions a bundle\'s files by the published copy, not by the build', function () {
    $panel = usePanel(fn (Panel $panel) => $panel->bundle(shopBundle($this->dist, $this->public)));
    $bundle = $panel->getBundles()['shop'];
    // Written after the panel was set up: nothing was looked at then.
    file_put_contents($this->dist.'/admin.js', 'one();');
    file_put_contents($this->dist.'/admin.css', '.one{}');

    // Not published yet: the build's.
    expect($bundle->getModule())->toBe(asset('vendor/shop/admin.js').versionOf('one();'))
        ->and($bundle->getStyles())->toBe([asset('vendor/shop/admin.css').versionOf('.one{}')]);

    artisan('sunrice:assets')->assertSuccessful();
    nextRequest();
    expect($bundle->getModule())->toBe(asset('vendor/shop/admin.js').versionOf('one();'));

    // A git pull of a symlinked package: the build changed, the copy served didn't, nor its URL.
    file_put_contents($this->dist.'/admin.js', 'two(2);');
    nextRequest();
    expect($bundle->getModule())->toBe(asset('vendor/shop/admin.js').versionOf('one();'));

    // Published: the URL changes with the file served.
    artisan('sunrice:assets')->assertSuccessful();
    nextRequest();
    expect($bundle->getModule())->toBe(asset('vendor/shop/admin.js').versionOf('two(2);'))
        ->and($bundle->toArray()['module'])->toBe($bundle->getModule());

    // Republishing the same content keeps the version (no needless reload).
    artisan('sunrice:assets')->assertSuccessful();
    nextRequest();
    expect($bundle->getModule())->toBe(asset('vendor/shop/admin.js').versionOf('two(2);'));
});

it('leaves full URLs as they are', function () {
    $bundle = PluginBundle::make('shop')->module('https://cdn.example.com/shop.js?v=3')->style('/vendor/shop/admin.css')->pages('Shop/')
        ->publishedFrom($this->dist, $this->public.'/vendor/shop');

    expect($bundle->getModule())->toBe('https://cdn.example.com/shop.js?v=3')
        ->and($bundle->getStyles())->toBe(['/vendor/shop/admin.css']);
});

it('changes the Inertia version when only a plugin bundle\'s published file changes', function () {
    file_put_contents($this->dist.'/admin.js', 'one();');
    file_put_contents($this->dist.'/admin.css', '.one{}');
    usePanel(fn (Panel $panel) => $panel->bundle(shopBundle($this->dist, $this->public)));
    artisan('sunrice:assets')->assertSuccessful();
    actingAs(adminUser(Panel::ACCESS));
    nextRequest();
    $before = AdminAssets::inertiaVersion(app(Panel::class));
    $inertia = fn (string $version) => withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Requested-With' => 'XMLHttpRequest'])->get('/admin');

    expect($before)->not->toBeNull()->not->toBe(AdminAssets::version());
    $inertia((string) $before)->assertOk()->assertHeader('X-Inertia', 'true');

    // The build changed but wasn't published: the browser still gets the same files.
    file_put_contents($this->dist.'/admin.js', 'two(2);');
    nextRequest();
    expect(AdminAssets::inertiaVersion(app(Panel::class)))->toBe($before);

    // Published: open tabs reload on their next visit (409, and Inertia loads the page in full).
    artisan('sunrice:assets')->assertSuccessful();
    nextRequest();
    $after = AdminAssets::inertiaVersion(app(Panel::class));
    expect($after)->not->toBe($before);
    $inertia((string) $before)->assertStatus(409)->assertHeader('X-Inertia-Location');
    nextRequest();
    $inertia((string) $after)->assertOk();
});

it('works the versions out without queries', function () {
    file_put_contents($this->dist.'/admin.js', 'one();');
    usePanel(fn (Panel $panel) => $panel->bundle(shopBundle($this->dist, $this->public)));
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    AdminAssets::inertiaVersion(app(Panel::class));
    app(Panel::class)->getBundles()['shop']->toArray();

    expect($queries)->toBe(0);
});

it('publishes the panel\'s and every plugin bundle\'s assets, and compresses them', function () {
    file_put_contents($this->dist.'/admin.js', str_repeat('shop();', 100));
    mkdir($this->dist.'/chunks');
    file_put_contents($this->dist.'/chunks/Orders-abc.js', str_repeat('orders();', 100));
    // An old file in the published folder goes: it's replaced by the build.
    mkdir($this->public.'/vendor/shop', recursive: true);
    file_put_contents($this->public.'/vendor/shop/gone.js', 'old();');
    usePanel(fn (Panel $panel) => $panel->bundle(shopBundle($this->dist, $this->public))
        ->bundle(PluginBundle::make('crm')->module('/vendor/crm/admin.js')->pages('Crm/')));

    artisan('sunrice:assets')
        ->expectsOutputToContain('Published Sunrice to public/vendor/sunrice-admin.')
        ->expectsOutputToContain('Published Acme Shop to public/vendor/shop.')
        ->assertSuccessful();

    expect(is_file($this->public.'/vendor/sunrice-admin/manifest.json'))->toBeTrue()
        ->and(file_get_contents($this->public.'/vendor/shop/chunks/Orders-abc.js'))->toBe(str_repeat('orders();', 100))
        ->and(is_file($this->public.'/vendor/shop/gone.js'))->toBeFalse()
        ->and(gzdecode((string) file_get_contents($this->public.'/vendor/shop/admin.js.gz')))->toBe(str_repeat('shop();', 100))
        ->and(glob($this->public.'/vendor/sunrice-admin/assets/app-*.js.gz'))->not->toBe([]);
});

it('leaves a published folder that links to the build alone, the panel\'s too', function () {
    file_put_contents($this->dist.'/admin.js', 'shop();');
    mkdir($this->public.'/vendor', recursive: true);
    symlink($this->dist, $this->public.'/vendor/shop');
    // The panel's folder a link too (to a copy of its build, so the package's own is never at stake).
    $panelDist = $this->dist.'-panel';
    (new Filesystem)->copyDirectory(AdminAssets::distPath(), $panelDist);
    symlink($panelDist, $this->public.'/vendor/sunrice-admin');
    usePanel(fn (Panel $panel) => $panel->bundle(shopBundle($this->dist, $this->public)));

    artisan('sunrice:assets')
        ->expectsOutputToContain('Acme Shop: public/vendor/shop is a link to the package\'s build')
        ->expectsOutputToContain('Sunrice: public/vendor/sunrice-admin is a link to the package\'s build')
        ->assertSuccessful();

    // Nothing deleted through the links, and no compressed copies written into the builds.
    expect(file_get_contents($this->dist.'/admin.js'))->toBe('shop();')
        ->and(is_file($this->dist.'/admin.js.gz'))->toBeFalse()
        ->and(is_file($panelDist.'/manifest.json'))->toBeTrue()
        ->and(glob($panelDist.'/assets/*.gz'))->toBe([])
        ->and(is_link($this->public.'/vendor/shop'))->toBeTrue();
    // Served as the build is: the same version, and nothing stale.
    expect(app(Panel::class)->getBundles()['shop']->getModule())->toBe(asset('vendor/shop/admin.js').versionOf('shop();'))
        ->and(app(Panel::class)->getBundles()['shop']->outdated())->toBeFalse();

    unlink($this->public.'/vendor/sunrice-admin');
    (new Filesystem)->deleteDirectory($panelDist);
});

it('refuses to replace a published folder that holds the build', function () {
    mkdir($this->public.'/vendor/shop/dist', recursive: true);
    file_put_contents($this->public.'/vendor/shop/dist/admin.js', 'shop();');
    usePanel(fn (Panel $panel) => $panel->bundle(shopBundle($this->public.'/vendor/shop/dist', $this->public)));

    artisan('sunrice:assets')->expectsOutputToContain('holds the package\'s build')->assertSuccessful();

    expect(file_get_contents($this->public.'/vendor/shop/dist/admin.js'))->toBe('shop();');
});

it('tells super admins, on full page loads, when a bundle\'s published copy is older than its build', function () {
    file_put_contents($this->dist.'/admin.js', 'one();');
    file_put_contents($this->dist.'/admin.css', '.one{}');
    usePanel(fn (Panel $panel) => $panel->bundle(shopBundle($this->dist, $this->public)));
    artisan('sunrice:assets')->assertSuccessful();
    $admin = adminUser(Panel::ACCESS);
    Role::findOrCreate(SyncPermissions::superAdminRole(), 'web');
    $admin->assignRole(SyncPermissions::superAdminRole());
    $notice = 'The admin assets of Acme Shop are older than the installed packages';
    $bundle = app(Panel::class)->getBundles()['shop'];

    actingAs($admin);
    get('/admin')->assertOk()->assertDontSee($notice);

    // Same size, other content, built after the copy was published.
    file_put_contents($this->dist.'/admin.js', 'two();');
    touch($this->dist.'/admin.js', time() + 10);
    expect($bundle->outdated())->toBeTrue();
    get('/admin')->assertOk()->assertSee($notice)->assertSee('<code>php artisan sunrice:assets</code>', false);
    // Not on Inertia visits (the shell's already there), nor for other admins.
    withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) AdminAssets::inertiaVersion(app(Panel::class)), 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/admin')->assertOk()->assertDontSee($notice);
    actingAs(adminUser(Panel::ACCESS));
    get('/admin')->assertOk()->assertDontSee($notice);

    actingAs($admin);
    artisan('sunrice:assets')->assertSuccessful();
    get('/admin')->assertOk()->assertDontSee($notice);
});
