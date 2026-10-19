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

## Routes and pages

`$panel->routes(fn () => ...)` adds routes for signed-in users who may open the panel (the `sunrice.access` permission), under the panel's path and named `sunrice.admin.…`. Plugin pages in React are explained in [Admin pages in React](frontend.md).

## Shared page data

`$panel->share($key, fn () => ...)` adds data every admin page receives. The closure runs per request, only when a page is rendered, so it can be expensive without slowing anything else.
