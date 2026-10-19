<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Assets\PluginBundle;
use Sunrice\Admin\Http\Middleware\Authenticate;
use Sunrice\Admin\Http\Middleware\EnsureCanAccessPanel;
use Sunrice\Admin\Navigation\NavigationItem;
use Sunrice\Admin\Panel;
use Sunrice\Admin\PanelRoutes;
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
