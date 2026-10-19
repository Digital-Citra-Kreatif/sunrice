# The panel and plugins

## Setting up the panel

Create a panel provider in your app and register it in `bootstrap/providers.php`:

```php
use Sunrice\Admin\Panel;
use Sunrice\Admin\PanelProvider;
use Sunrice\Cms\CmsPlugin;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->path('admin')          // https://example.com/admin
            ->guard('web')           // the auth guard admins sign in with
            ->brand('Acme')
            ->plugin(CmsPlugin::make());
    }
}
```

An app has one panel. Once it boots, the panel is locked: changing it later throws `PanelMisconfigured`.

## Writing a plugin

A plugin implements `Sunrice\Admin\Contracts\Plugin`, or extends `Sunrice\Admin\BasePlugin`:

```php
use Sunrice\Admin\BasePlugin;
use Sunrice\Admin\Navigation\NavigationItem;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Permissions\Permission;

final class ShopPlugin extends BasePlugin
{
    public function id(): string
    {
        return 'shop';
    }

    public function requires(): array
    {
        return ['cms']; // products are CMS entries
    }

    public function register(Panel $panel): void
    {
        $panel
            ->permissions(Permission::make('shop.orders.view', 'View orders', 'Shop'))
            ->navigation(
                NavigationItem::make('shop.orders')->label('Orders')->url('shop/orders')
                    ->icon('receipt')->group('Shop')->permission('shop.orders.view'),
            )
            ->share('shop', fn () => ['currency' => 'IDR']);
    }
}
```

When the panel boots it calls `register()` on every plugin, each plugin's requirements before the plugin itself, checks what they declared, then calls `boot()` in the same order.

| Rule | When it's broken |
| --- | --- |
| Plugin ids are unique, lowercase, and not `sunrice` (the panel's own prefix). | `PanelMisconfigured` when the plugin is added. |
| Every plugin in `requires()` is on the panel, with no circular requirements. | `PanelMisconfigured` at boot, naming the missing plugin or the circle. |
| A plugin's permissions start with its id (`shop.…`); the app may not use a plugin's prefix. | `PanelMisconfigured` at boot. |
| Navigation keys, permission names, shared data keys and bundle ids are unique; the panel's own shared data keys (`sunrice`, `auth`, `navigation`, `flash`, `errors`) are reserved. | `PanelMisconfigured`, naming the plugin that registered the duplicate. |
| Submenu items point to an existing parent, one level deep. | `PanelMisconfigured` at boot. |

## Navigation

`NavigationItem::make($key)` takes a label, a `url` relative to the panel path, an `icon` (a lucide name), a `group`, a `parent` (another item's key, for a submenu), a `sort` (lower first, 100 by default) and a `permission`. An item without a permission is shown to everyone who can open the panel. Hiding a parent hides its submenu.

## Dashboard cards

The dashboard shows cards from the panel and its plugins: figures
(`stats`) or a list of links (`list`). Their content is worked out for the
user looking, after the page shows (an Inertia deferred prop), so slow
queries don't hold up the panel. Each card says who sees it, or the panel
refuses it:

```php
use Sunrice\Admin\Dashboard\DashboardCard;

$panel->dashboardCards(
    DashboardCard::make('shop.today')->title('Today')->permission('shop.orders.view')->span(2)
        ->stats(fn (User $user) => [
            ['label' => 'Orders', 'value' => Order::today()->count(), 'url' => '/admin/shop/orders'],
            ['label' => 'Revenue', 'value' => Money::format(Order::today()->sum('total'))],
        ]),
    DashboardCard::make('shop.recent')->title('Recent orders')->visible(fn (User $user) => $user->isStaff())
        ->list(fn (User $user) => Order::latest()->limit(8)->get()->map(fn (Order $order) => [
            'title' => $order->number, 'meta' => $order->created_at->diffForHumans(), 'url' => "/admin/shop/orders/{$order->id}",
        ])->all())
        ->empty('No orders yet.')->link('All orders', 'shop/orders'),
);
```

- `permission()`, `visible()` or `everyone()` (everyone who can open the panel).
- `sort()` (lower first, 100 by default) and `span()` (1 to 3 columns).
- Items' addresses are paths or web addresses (anything else is dropped);
  `link()`'s is relative to the panel's path, like navigation items'.

The panel has **Recent activity** (`sunrice.activity.view`); the CMS adds
content per collection, recent edits, new submissions and comments.

## Help

**Help** (in the menu under your name) shows guide pages for the people who use the panel: the panel's
own (*Using the panel*), the CMS's (*Content*), and any a plugin adds, as
Markdown files in a folder:

```php
use Sunrice\Admin\Help\HelpSection;

$panel->help(HelpSection::make('shop', 'Shop', __DIR__.'/../guide', sort: 50));
```

Files are shown in name order (`01-orders.md` is the page `orders`), each
titled by its first `# ` line. HTML in them is left out, and links to
`javascript:` addresses too.

## Routes and pages

`$panel->routes(fn () => ...)` adds routes for signed-in users who may open the panel (the `sunrice.access` permission), under the panel's path and named `sunrice.admin.…`. Plugin pages in React are explained in [Admin pages in React](frontend.md).

## Shared page data

`$panel->share($key, fn () => ...)` adds data every admin page receives. The closure runs per request, only when a page is rendered, so it can be expensive without slowing anything else.
