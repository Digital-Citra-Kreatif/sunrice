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

`$panel->routes(fn () => ...)` adds routes for signed-in users who may open the panel (the `sunrice.access` permission), under the panel's path and named `sunrice.admin.…`. Give each one its own permission as well (`->middleware('can:shop.orders.view')`, or a middleware of yours that decides): the panel's tests assert that every `sunrice.admin.*` route is behind sign-in and panel access, and the CMS's that each of its routes names a permission or content check. Plugin pages in React are explained in [Admin pages in React](frontend.md).

### Error pages

Under the panel's path, a 403, 404, 419 (the page expired) or 500 is the panel's own **Error** page, inside the admin layout for someone signed in, and the right status code. That covers `abort(403)`, a refused policy, a record that isn't found and an address the panel doesn't know (the panel's fallback route `sunrice.admin.missing`, after every other route; guests go to the login page). Requests that want JSON still get JSON, a 500 with `APP_DEBUG=true` keeps the framework's page with the stack trace, and pages outside the panel's path are left to the app. A refused page is still a `page refused` security event. The page name `Error` belongs to the panel.

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

Some permissions are worth an [email alert](security.md#email-alerts) when someone gets them, directly or through a role. The panel's own are `sunrice.users.*`, `sunrice.roles.*`, `sunrice.settings.*`, `sunrice.ai.connect` and `sunrice.activity.*`; a plugin adds names or patterns of its own (starting with its id):

```php
$panel->privilegedPermissions('shop.settings.*', 'shop.payouts.manage');
```
