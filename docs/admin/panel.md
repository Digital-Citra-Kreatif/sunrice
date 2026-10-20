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

`brand()` names the panel in the sidebar, on the sign-in page and in emails. Admins can give it another name, a logo, a font, a colour and their own sign-in page in Settings → Branding: see [Branding](branding.md).

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
| A plugin's widget keys start with its id (`shop.…`); the app's may not use a plugin's id; every widget has `data`. | `PanelMisconfigured` when it's registered. |
| Navigation keys, widget keys, permission names, shared data keys and bundle ids are unique; the panel's own shared data keys (`sunrice`, `auth`, `navigation`, `flash`, `errors`) are reserved. | `PanelMisconfigured`, naming the plugin that registered the duplicate. |
| Submenu items point to an existing parent, one level deep. | `PanelMisconfigured` at boot. |

## Navigation

`NavigationItem::make($key)` takes a label, a `url` relative to the panel path, an `icon` (a lucide name), a `group`, a `parent` (another item's key, for a submenu), a `sort` (lower first, 100 by default) and a `permission`. An item without a permission is shown to everyone who can open the panel. Hiding a parent hides its submenu.

## Dashboard widgets

The dashboard shows the widgets the panel and its plugins register, the ones the user may see, in a grid of three columns (one on phones). The panel draws them from data alone, so a plugin adds one without JavaScript (plugin bundles load only on their own pages). There are three kinds, made with `Sunrice\Admin\Dashboard\Widget`:

```php
use Illuminate\Contracts\Auth\Access\Authorizable;
use Sunrice\Admin\Dashboard\Widget;

$panel->widgets(
    Widget::stat('shop.revenue')->title('Revenue today')->icon('receipt')->link('shop/orders')
        ->permission('shop.orders.view')->sort(15)
        ->data(fn (Authorizable $user): array => [
            'value' => Order::query()->whereDate('created_at', today())->sum('total'),
            'delta' => '+12% on yesterday', 'trend' => 'up',
        ]),
    Widget::list('shop.orders')->title('Latest orders')->span(2)->link('shop/orders', 'All orders')->empty('No orders yet.')
        ->permission('shop.orders.view')
        ->data(fn (Authorizable $user): array => ['rows' => Order::query()->latest()->limit(8)->get()->map(fn (Order $order) => [
            'title' => $order->number, 'meta' => $order->customer_name, 'url' => "shop/orders/{$order->id}",
            'badge' => $order->status_label, 'tone' => $order->paid ? 'success' : 'warning',
            'at' => $order->created_at?->toIso8601String(),
        ])->all()]),
    Widget::cards('shop.help')->title('Getting started')
        ->data(fn (): array => ['cards' => [['title' => 'Add a product', 'text' => 'Name, price and photos.', 'url' => 'shop/products/create', 'icon' => 'package']]]),
);
```

Each takes a `title`, a `description` (a line under it), an `icon` (a lucide name, as navigation takes), a `link` (the stat itself, or a list's "See all", with an optional label), `empty` (what a list or cards widget says with nothing in it), `sort` (lower first, 100 by default), `span` (1, 2 or 3 columns on wide screens), and `permission` and/or `visible(fn (Authorizable $user): bool)`, as navigation items do. A plugin's keys start with its id and a dot.

`data` returns, by kind:

| Kind | Data |
| --- | --- |
| `stat` | `value` (a number or short text), optional `delta` ("+3 this week"), `trend` (`up` or `down`), `description`. |
| `list` | `rows`, each a `title` with optional `meta`, `url`, `badge`, `tone` (`neutral`, `success`, `warning`, `danger`) and `at` (an ISO 8601 time, shown as "5 minutes ago"); optional `count`, shown beside the title. |
| `cards` | `cards`, each a `title` with optional `text`, `url` and `icon`. |

URLs are relative to the panel's path (`shop/orders`), or start with `/` or `http(s)://`; anything else is dropped. Lists and cards show at most 20 items.

The page arrives with the widgets' definitions and paints at once; `data` runs afterwards, in a deferred request (`Inertia::defer`), one request per plugin and one for the panel's and the app's widgets, with placeholders meanwhile. It runs only for users who may see the widget, so it may query what it needs, but keep it to a query or two (no queries per row). A widget whose `data` throws, or returns something of the wrong shape, is reported (`report()`, so it's logged) and shown as unavailable; the rest of the dashboard is unaffected.

`$panel->widgetsFor($user)` gives the widgets a user sees, in order, and `$widget->resolve($user)` runs and checks one's data, for tests. The panel's own widgets are `sunrice.media` (the media library's size, with `sunrice.media.view`) and `sunrice.activity` (the last eight lines of the activity log, with `sunrice.activity.view`). The CMS adds its own (see [Collections](../cms/collections.md#the-dashboard)).

## Routes and pages

`$panel->routes(fn () => ...)` adds routes for signed-in users who may open the panel (the `sunrice.access` permission), under the panel's path and named `sunrice.admin.…`. Give each one its own permission as well (`->middleware('can:shop.orders.view')`, or a middleware of yours that decides): the panel's tests assert that every `sunrice.admin.*` route is behind sign-in and panel access, and the CMS's that each of its routes names a permission or content check. Plugin pages in React are explained in [Admin pages in React](frontend.md).

## Shared page data

`$panel->share($key, fn () => ...)` adds data every admin page receives. The closure runs per request, only when a page is rendered, so it can be expensive without slowing anything else.

## What users own

When a user is [deleted](users.md#deleting-a-user), whoever deletes them chooses what happens to what they own. Register each kind with a key (a plugin's start with its id), a label for the dialog, how to count a user's, and what to do with it: `$mode` is `reassign` (with `$heir`), `keep` or `delete`.

```php
use Illuminate\Database\Eloquent\Model;

$panel->userContent('shop.orders', 'orders',
    fn (Model $user): int => Order::query()->where('user_id', $user->getKey())->count(),
    function (Model $user, string $mode, ?Model $heir): void {
        Order::query()->where('user_id', $user->getKey())->update(['user_id' => $heir?->getKey()]);
    });
```

It runs inside the delete's transaction, before the user row goes. `delete` means whatever fits the kind: trash it, delete it, or keep it when it can't go.

## Privileged permissions

Some permissions are worth an [email alert](security.md#email-alerts) when someone gets them, directly or through a role. The panel's own are `sunrice.users.*`, `sunrice.roles.*`, `sunrice.settings.*`, `sunrice.ai.*` (connecting agents, letting them read the error log and run commands) and `sunrice.activity.*`; a plugin adds names or patterns of its own (starting with its id):

```php
$panel->privilegedPermissions('shop.settings.*', 'shop.payouts.manage');
```
