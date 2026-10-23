# Writing a plugin: the Shop example

A plugin is a Composer package (or a folder of your app) that adds to the panel through the public API: classes and methods marked `@api`. This page builds a small **Shop** plugin end to end, then tests it with Orchestra Testbench. The rules a plugin follows (ids, prefixes, what the panel checks at boot) are in [The panel and plugins](panel.md); each part below links to its own page.

## The layout

```
shop/
├─ composer.json            requires sunrice/admin (and sunrice/cms if it needs it)
├─ src/
│  ├─ ShopPlugin.php        what it adds to the panel
│  ├─ ShopServiceProvider.php
│  ├─ Models/Order.php, Product.php
│  ├─ Resources/ProductResource.php
│  ├─ Settings/ShopSettings.php
│  ├─ Http/OrdersController.php
│  └─ Ai/ListOrders.php
├─ database/migrations/
├─ resources/js/admin.tsx   the React pages (built to dist/)
├─ docs/                    developer docs (Help → Developer docs, read_docs)
├─ guide/en, guide/id       the user guide (Help → Guide)
└─ tests/
```

`composer.json` states the panel version it needs (`"sunrice/admin": "^1.0"`): plugins never check for features at runtime. It also names the plugin class, so `sunrice:install` adds it to the panel provider it writes for a new app:

```json
"extra": {
    "laravel": {
        "providers": ["Acme\\Shop\\ShopServiceProvider"],
        "sunrice-plugins": ["Acme\\Shop\\ShopPlugin"]
    }
}
```

## The plugin

```php
<?php

declare(strict_types=1);

namespace Acme\Shop;

use Acme\Shop\Ai\ListOrders;
use Acme\Shop\Http\OrdersController;
use Acme\Shop\Models\Order;
use Acme\Shop\Resources\ProductResource;
use Acme\Shop\Settings\ShopSettings;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Facades\Route;
use Sunrice\Admin\Assets\PluginBundle;
use Sunrice\Admin\BasePlugin;
use Sunrice\Admin\Dashboard\Widget;
use Sunrice\Admin\Navigation\NavigationItem;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Permissions\Permission;

final class ShopPlugin extends BasePlugin
{
    public function id(): string
    {
        return 'shop';
    }

    public function register(Panel $panel): void
    {
        $panel
            // A resource: list, forms, CSV export; permissions shop.products.view … .export.
            ->resources(ProductResource::class)
            // A settings section: Settings → Shop, permission shop.settings.shop.
            ->settings(ShopSettings::class)
            // A page of its own, with its permission and sidebar item.
            ->permissions(Permission::make('shop.orders.view', 'View orders', 'Shop'))
            ->navigation(NavigationItem::make('shop.orders')->label('Orders')->url('shop/orders')
                ->icon('receipt')->group('Shop')->sort(20)->permission('shop.orders.view'))
            ->routes(fn () => Route::get('shop/orders', OrdersController::class)
                ->name('shop.orders.index')->middleware('can:shop.orders.view'))
            ->bundle(PluginBundle::make('shop')->module(asset('vendor/shop/admin.js'))->pages('Shop/'))
            // A dashboard widget; its data loads after the page.
            ->widgets(Widget::stat('shop.orders-today')->title('Orders today')->icon('receipt')
                ->link('shop/orders')->permission('shop.orders.view')
                ->data(fn (Authorizable $user): array => ['value' => Order::query()->whereDate('created_at', today())->count()]))
            // A tool for AI agents, and how to use it.
            ->tools(ListOrders::class)
            ->aiInstructions('Orders: list them with shop_list_orders.')
            // Who gets what out of the box, and what's worth an alert.
            ->defaultRole('cashier', [Panel::ACCESS, 'shop.orders.view', 'shop.products.view'])
            ->privilegedPermissions('shop.settings.*')
            // Docs and a guide for the Help pages.
            ->docs('Shop', dirname(__DIR__).'/docs')
            ->guide('Shop', dirname(__DIR__).'/guide');
    }
}
```

The app adds it in its panel provider: `$panel->plugin(ShopPlugin::make())`. A plugin that needs another one says so in `requires()` (`return ['cms'];`), and the panel registers that one first.

The service provider does what any Laravel package does: migrations, and the built assets to publish (tag them `laravel-assets` too, so `composer update` publishes them):

```php
final class ShopServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__).'/database/migrations');
        $this->publishes([dirname(__DIR__).'/dist' => public_path('vendor/shop')], ['shop-assets', 'laravel-assets']);
    }
}
```

## The resource

```php
final class ProductResource extends Resource
{
    protected static string $model = Product::class;

    public static function navigationGroup(): ?string { return 'Shop'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required(),
            NumberInput::make('price')->min(0)->step(0.01)->required(),
            MediaPicker::make('image_id')->images(),
            RichText::make('description'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([Column::make('name')->searchable()->sortable(), Column::make('price')->sortable()])
            ->exportable();   // adds shop.products.export
    }
}
```

Its routes are `/admin/shop/products` (a plugin's resources live under its id), its permissions `shop.products.view`, `.create`, `.update`, `.delete` and `.export`, unless the model has a policy. See [Resources](resources.md).

## The settings

```php
final class ShopSettings extends Settings
{
    public string $currency = 'IDR';

    #[Encrypted]
    public string $paymentKey = '';

    public static function key(): string { return 'shop'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('currency')->options(['IDR' => 'Rupiah', 'USD' => 'US dollar'])->required(),
            Secret::make('paymentKey'),
        ]);
    }
}
```

Read them anywhere with `app(ShopSettings::class)->currency`. See [Settings](settings.md).

## The page

The controller renders an Inertia page; the bundle provides it, built with `@imajiner/admin-sdk` so it uses the panel's React and components (see [Admin pages in React](frontend.md)):

```php
final class OrdersController
{
    public function __invoke(): \Inertia\Response
    {
        return Inertia::render('Shop/Orders/Index', [
            'orders' => Order::query()->latest()->paginate(25),
        ]);
    }
}
```

```tsx
// resources/js/admin.tsx
import { definePlugin } from '@imajiner/admin-sdk';
import { Card, CardContent, PageHeader, useBreadcrumbs } from '@imajiner/admin-sdk/ui';

function OrdersIndex({ orders }: { orders: { data: { id: number; number: string; total: string }[] } }) {
    useBreadcrumbs([{ label: 'Orders' }]);

    return (
        <div className="shop:flex shop:flex-col shop:gap-6">
            <PageHeader title="Orders" description="The latest orders, newest first." />
            <Card>
                <CardContent>
                    {orders.data.map((order) => <div key={order.id}>{order.number} · {order.total}</div>)}
                </CardContent>
            </Card>
        </div>
    );
}

definePlugin({ pages: { 'Shop/Orders/Index': OrdersIndex } });
```

With more than a page or two, give each page its own chunk, loaded the first time it opens, with `lazyPages` (see [Loading pages on demand](frontend.md#loading-pages-on-demand)):

```tsx
definePlugin({
    lazyPages: {
        'Shop/Orders/Index': () => import('./pages/Orders/Index'),
        'Shop/Orders/Show': () => import('./pages/Orders/Show'),
    },
});
```

## The AI tool

```php
#[IsReadOnly]
#[Description('List the shop\'s latest orders.')]
final class ListOrders extends Tool
{
    protected string $name = 'shop_list_orders';

    public function handle(Request $request): Response
    {
        if (! $this->can('shop.orders.view')) {
            return $this->denied('view orders');
        }

        return $this->json(Order::query()->latest()->limit(20)->get(['id', 'number', 'total']));
    }
}
```

Agents work as the token's user, with that user's permissions. See [AI access](ai.md#tools-from-plugins-and-apps).

## Testing a plugin with Testbench

Test the plugin the way the panel tests itself: [Orchestra Testbench](https://packages.tools/testbench) gives a Laravel app, Pest the tests.

```bash
composer require --dev orchestra/testbench pestphp/pest pestphp/pest-plugin-laravel
```

A test case loads the panel's provider, yours, and a panel provider for the test app that adds your plugin. The app's user model needs `HasRoles`:

```php
// tests/TestCase.php
namespace Acme\Shop\Tests;

use Acme\Shop\ShopPlugin;
use Acme\Shop\ShopServiceProvider;
use Orchestra\Testbench\TestCase as Testbench;
use Sunrice\Admin\AdminServiceProvider;
use Sunrice\Admin\Panel;
use Sunrice\Admin\PanelProvider;

use function Orchestra\Testbench\default_migration_path;
use function Orchestra\Testbench\load_migration_paths;

final class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->plugin(ShopPlugin::make());
    }
}

abstract class TestCase extends Testbench
{
    protected function getPackageProviders($app): array
    {
        return [AdminServiceProvider::class, ShopServiceProvider::class, TestPanelProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('auth.providers.users.model', Fixtures\User::class);   // a User with HasRoles
        load_migration_paths($app, default_migration_path());   // Laravel's users table; the panel's migrations load themselves
    }
}
```

```php
// tests/Pest.php
uses(Acme\Shop\Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class)->in(__DIR__);
```

`RefreshDatabase` then migrates once and wraps each test in a transaction, so the suite stays quick on MySQL and Postgres too. Avoid Testbench's `WithLaravelMigrations` here: it migrates and rolls back Laravel's tables around every test, which makes every test migrate the whole database again. Since ids aren't reset between tests, assert against the saved records' ids rather than `1`.

Then test what the plugin registered, that its routes are guarded, and its pages and tools:

```php
use Acme\Shop\Tests\Fixtures\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Sunrice\Admin\Actions\Ai\CreateAiToken;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Panel;

function shopUser(string ...$permissions): User
{
    app(SyncPermissions::class)->handle();   // creates the registered permissions

    return User::query()->create(['name' => 'Rina', 'email' => 'rina@example.com', 'password' => 'secret'])
        ->givePermissionTo($permissions);
}

it('registers its permissions', function () {
    expect(app(Panel::class)->getPermissions())->toHaveKeys(['shop.orders.view', 'shop.products.export', 'shop.settings.shop']);
});

it('guards the orders page', function () {
    $this->actingAs(shopUser(Panel::ACCESS))->get('/admin/shop/orders')->assertForbidden();

    $this->actingAs(shopUser(Panel::ACCESS, 'shop.orders.view'))->get('/admin/shop/orders')
        ->assertInertia(fn (Assert $page) => $page->component('Shop/Orders/Index', false));   // false: the page is in your bundle, not a file to look for
});

it('lists orders for AI agents', function () {
    $user = shopUser(Panel::ACCESS, 'sunrice.ai.connect', 'shop.orders.view');
    [, $token] = app(CreateAiToken::class)->handle($user, 'Test agent');

    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'shop_list_orders', 'arguments' => []]],
        ['Authorization' => "Bearer {$token}"])->assertOk()->assertJsonStructure(['result' => ['content']]);
});
```

- `$panel->widgetsFor($user)` and `$widget->resolve($user)` test a widget's visibility and data without the dashboard.
- Every admin route of yours should be behind its permission: a test that walks your routes and checks each names a `can:` middleware (as the CMS's `tests/Cms/AdminRoutesGuardTest.php` does) keeps it so.
- Settings: `$this->put('/admin/settings/shop', [...])` saves through the panel's validation, and `app(ShopSettings::class)` reads the result.
- For a plugin that requires the CMS, add `Sunrice\Cms\CmsServiceProvider` (and `Spatie\Honeypot\HoneypotServiceProvider`) to the providers, and the CMS's plugin to the test panel before yours.
