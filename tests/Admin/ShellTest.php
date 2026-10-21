<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Assets\PluginBundle;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Http\Middleware\Authenticate;
use Sunrice\Admin\Http\Middleware\EnsureCanAccessPanel;
use Sunrice\Admin\Navigation\NavigationItem;
use Sunrice\Admin\Panel;
use Sunrice\Admin\PanelRoutes;
use Sunrice\Admin\Settings\BrandingSettings;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

/** A signed-in user; `$admin` users may open the panel. */
function signIn(bool $admin = true): User
{
    $user = $admin ? adminUser(Panel::ACCESS) : adminUser();
    actingAs($user);

    return $user;
}

it('turns away guests and users who may not open the panel', function () {
    usePanel();

    get('/admin')->assertRedirect('/admin/login');
    getJson('/admin')->assertUnauthorized();

    signIn(admin: false);
    get('/admin')->assertForbidden();
});

it('shows the dashboard with the shell\'s page data', function () {
    usePanel(fn (Panel $panel) => $panel->path('backend')->brand('Acme')->navigation(
        NavigationItem::make('reports')->label('Reports')->url('reports')->group('Tools'),
    )->share('shop', fn () => ['currency' => 'IDR']));
    signIn();

    get('/backend')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard', shouldExist: false)
        ->where('sunrice.brand', 'Acme')
        ->where('sunrice.path', '/backend')
        ->where('sunrice.bundles', [])
        ->where('auth.user.name', 'Rina')
        ->where('navigation.0.group', 'Tools')
        ->where('navigation.0.items.0.key', 'reports')
        ->where('shop.currency', 'IDR'));
});

it('shows the brand\'s first letter where there is no logo', function () {
    usePanel(fn (Panel $panel) => $panel->brand('élan'));
    signIn();

    get('/admin')->assertInertia(fn (Assert $page) => $page
        ->where('sunrice.branding.name', 'élan')
        ->where('sunrice.branding.logo', null)
        ->where('sunrice.branding.lettermark', 'É'));

    // A logo id whose file is gone (or isn't an image) falls back too.
    app(BrandingSettings::class)->fill(['logo' => 12345, 'name' => ' '])->save();
    get('/admin')->assertInertia(fn (Assert $page) => $page
        ->where('sunrice.branding.name', 'élan')
        ->where('sunrice.branding.logo', null)
        ->where('sunrice.branding.lettermark', 'É'));
});

it('puts plugin routes behind sign-in, under the panel path and names', function () {
    usePanel(fn (Panel $panel) => $panel->routes(function () {
        Route::get('shop/orders', fn () => 'orders')->name('shop.orders.index');
    }));

    expect(route('sunrice.admin.shop.orders.index', absolute: false))->toBe('/admin/shop/orders');
    get('/admin/shop/orders')->assertRedirect('/admin/login');

    signIn();
    get('/admin/shop/orders')->assertOk()->assertSee('orders');
});

it('lists plugin bundles for the frontend to load on demand', function () {
    usePanel(fn (Panel $panel) => $panel->bundle(PluginBundle::make('shop')->module('/vendor/shop/admin.js')->style('/vendor/shop/admin.css')->pages('Shop/')));
    signIn();

    get('/admin')->assertInertia(fn (Assert $page) => $page->where('sunrice.bundles', [
        ['id' => 'shop', 'module' => '/vendor/shop/admin.js', 'styles' => ['/vendor/shop/admin.css'], 'pages' => ['Shop/']],
    ]));
});

it('shares command palette searches on every page, with their URLs', function () {
    usePanel(fn (Panel $panel) => $panel
        ->routes(fn () => Route::get('shop/search', fn () => ['items' => []])->name('shop.search'))
        ->commandSearch('shop.orders', fn () => route('sunrice.admin.shop.search'), minLength: 3));
    signIn();

    get('/admin')->assertInertia(fn (Assert $page) => $page->where('sunrice.commandSearches', [
        ['id' => 'shop.orders', 'url' => url('/admin/shop/search'), 'minLength' => 3],
    ]));
    get('/admin/media')->assertInertia(fn (Assert $page) => $page->where('sunrice.commandSearches.0.id', 'shop.orders'));
});

it('refuses two command searches with the same id', function () {
    expect(fn () => Panel::make()->commandSearch('a', fn () => '/a')->commandSearch('a', fn () => '/b'))
        ->toThrow(PanelMisconfigured::class, 'Two command searches have the id "a"');
});

it('guards every panel route but the login pages', function () {
    usePanel(fn (Panel $panel) => $panel->routes(fn () => Route::get('anything', fn () => 'x')->name('anything')));

    $unguarded = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'sunrice.admin.'))
        ->reject(fn ($route) => in_array($route->getName(), PanelRoutes::PUBLIC_ROUTES, true))
        ->reject(fn ($route) => in_array(Authenticate::class, $route->gatherMiddleware(), true)
            && in_array(EnsureCanAccessPanel::class, $route->gatherMiddleware(), true))
        ->map(fn ($route) => $route->getName())
        ->values()->all();

    expect($unguarded)->toBe([]);
});

it('tells you when the assets are not published, and publishes them', function () {
    $public = sys_get_temp_dir().'/sunrice-public-'.uniqid();
    mkdir($public);
    app()->usePublicPath($public);
    usePanel();
    signIn();

    get('/admin')->assertOk()->assertSee('php artisan sunrice:assets');

    artisan('sunrice:assets')->assertSuccessful();
    expect(file_exists($public.'/vendor/sunrice-admin/manifest.json'))->toBeTrue();
    get('/admin')->assertOk()
        ->assertDontSee('php artisan sunrice:assets')
        ->assertSee('/vendor/sunrice-admin/assets/app-', false);

    (new Filesystem)->deleteDirectory($public);
});

it('warns super admins when the published assets are older than the installed packages', function () {
    $public = sys_get_temp_dir().'/sunrice-public-'.uniqid();
    $dist = sys_get_temp_dir().'/sunrice-dist-'.uniqid();
    mkdir($public);
    mkdir($dist);
    file_put_contents($dist.'/admin.js', 'new();');
    mkdir($public.'/vendor/shop', recursive: true);
    file_put_contents($public.'/vendor/shop/admin.js', 'new();');
    app()->usePublicPath($public);
    usePanel(fn (Panel $panel) => $panel->bundle(PluginBundle::make('shop')->module('/vendor/shop/admin.js')->pages('Shop/')->named('Acme <Shop>')->publishedFrom($dist, $public.'/vendor/shop')));
    artisan('sunrice:assets')->assertSuccessful();
    $admin = adminUser(Panel::ACCESS);
    Role::findOrCreate(SyncPermissions::superAdminRole(), 'web');
    $admin->assignRole(SyncPermissions::superAdminRole());
    $warning = 'are older than the installed packages';

    actingAs($admin);
    get('/admin')->assertOk()->assertDontSee($warning);

    // An update without publishing: the panel's manifest and the plugin's build changed.
    file_put_contents($public.'/vendor/sunrice-admin/manifest.json', '{"old": true}');
    file_put_contents($dist.'/admin.js', 'newer();');
    // Named by the bundle's display name, with the commands to run as code; in the page's flow, above the shell (NEW2-front-3).
    get('/admin')->assertOk()
        ->assertSee('The admin assets of Sunrice and Acme &lt;Shop&gt; are older than the installed packages, so the panel may run old code.', false)
        ->assertSee('On the server, run <code>php artisan sunrice:assets</code> and <code>php artisan vendor:publish --tag=laravel-assets --force</code>.', false)
        ->assertSee('id="sunrice-stale-assets"', false)
        ->assertSee('--sunrice-banner', false);
    expect(PluginBundle::make('shop')->displayName())->toBe('shop');
    // Only to super admins, and only on full page loads.
    actingAs(adminUser(Panel::ACCESS));
    get('/admin')->assertOk()->assertDontSee($warning);

    actingAs($admin);
    artisan('sunrice:assets')->assertSuccessful();
    copy($dist.'/admin.js', $public.'/vendor/shop/admin.js');
    get('/admin')->assertOk()->assertDontSee($warning);

    (new Filesystem)->deleteDirectory($public);
    (new Filesystem)->deleteDirectory($dist);
});

it('loads the panel font, or the one configured instead', function () {
    usePanel();

    get('/admin/login')->assertOk()
        ->assertSee('<link rel="preconnect" href="https://fonts.bunny.net" crossorigin>', false)
        ->assertSee('https://fonts.bunny.net/css?family=instrument-sans:400,500,600&amp;display=swap', false)
        ->assertDontSee('--sunrice-font');

    config(['sunrice-admin.font' => ['stylesheet' => null, 'family' => 'Instrument "Sans"']]);
    get('/admin/login')->assertOk()
        ->assertDontSee('fonts.bunny.net')
        ->assertSee('html:root{--sunrice-font:"Instrument Sans";}', false);
});

it('gives each flash message an id, so the same text twice shows twice', function () {
    usePanel();
    signIn();

    get('/admin')->assertInertia(fn (Assert $page) => $page->where('flash', ['id' => null, 'success' => null, 'error' => null, 'action' => null]));

    $ids = [];
    foreach ([1, 2] as $_) {
        session()->flash('success', 'Saved.');
        get('/admin')->assertInertia(function (Assert $page) use (&$ids) {
            $page->where('flash.success', 'Saved.')->where('flash.error', null);
            $ids[] = $page->toArray()['props']['flash']['id'];
        });
    }
    expect($ids[0])->toBeString()->not->toBe($ids[1]);
});

it('gives a success toast the button flashed with it, as an Undo', function () {
    usePanel();
    signIn();

    session()->flash('success', 'Moved to the trash.');
    session()->flash('success_action', ['label' => 'Undo', 'url' => url('/admin/things/1/restore'), 'data' => ['ids' => [1]]]);
    get('/admin')->assertInertia(fn (Assert $page) => $page->where('flash.action', [
        'label' => 'Undo', 'url' => url('/admin/things/1/restore'), 'method' => 'post', 'data' => ['ids' => [1]],
    ]));

    // Without a success message, or malformed, there's no button.
    session()->flush();
    session()->flash('error', 'No.');
    session()->flash('success_action', ['label' => 'Undo', 'url' => '/x']);
    get('/admin')->assertInertia(fn (Assert $page) => $page->where('flash.action', null));
    session()->flush();
    session()->flash('success', 'Done.');
    session()->flash('success_action', ['label' => 'Undo']);
    get('/admin')->assertInertia(fn (Assert $page) => $page->where('flash.action', null));
});
