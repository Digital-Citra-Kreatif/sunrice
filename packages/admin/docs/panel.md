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

`brand()` is the panel's own name. Admins can give it another name, a logo, a font, a colour and their own sign-in page in Settings → Branding: see [Branding](branding.md). The name people see (in the sidebar, on the sign-in page, in emails and to AI agents) is `$panel->brandName()`.

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

A complete example (a resource, settings, a React page, a widget, an AI tool, docs and a guide) and how to test it with Testbench are in [Writing a plugin: the Shop example](plugins.md).

When the panel boots it calls `register()` on every plugin, each plugin's requirements before the plugin itself, checks what they declared, then calls `boot()` in the same order.

| Rule | When it's broken |
| --- | --- |
| Plugin ids are unique, lowercase, and not `sunrice` (the panel's own prefix). | `PanelMisconfigured` when the plugin is added. |
| Every plugin in `requires()` is on the panel, with no circular requirements. | `PanelMisconfigured` at boot, naming the missing plugin or the circle. |
| A plugin's permissions start with its id (`shop.…`); the app may not use a plugin's prefix. | `PanelMisconfigured` at boot. |
| A plugin's widget keys start with its id (`shop.…`); the app's may not use a plugin's id; every widget has `data` and says who sees it (`permission()`, `visible()` or `everyone()`). | `PanelMisconfigured` when it's registered. |
| Navigation keys, widget keys, permission names, shared data keys and bundle ids are unique; the panel's own shared data keys (`sunrice`, `auth`, `navigation`, `flash`, `errors`) are reserved. | `PanelMisconfigured`, naming the plugin that registered the duplicate. |
| Submenu items point to an existing parent, one level deep. | `PanelMisconfigured` at boot. |

## Navigation

`NavigationItem::make($key)` takes a label, a `url` relative to the panel path, an `icon` (a lucide name), a `group`, a `parent` (another item's key, for a submenu), a `sort` (lower first, 100 by default) and a `permission`. An item without a permission is shown to everyone who can open the panel. Hiding a parent hides its submenu. Labels and groups are said in the panel's language ([panel languages](languages.md)); `->verbatim()` shows a label that is data (a collection's title) as written.

A page that belongs with the settings (a plugin's own React page, not a `Settings` class) goes in the **Settings menu** instead of the sidebar with `->inSettings($after)`: it's listed among the settings sections, right after the section whose key is `$after` (`'site'`), or at the end without one, for users who pass its `permission()`/`visible()`. The sidebar's **Settings** item shows (and opens the first entry) even when that page is all a user may open, and it's marked current on the page. `$panel->settingsNavigation($user)` gives the menu, `[['key', 'label', 'url'], …]`: pass it to the page and render it with `SettingsLayout` (or `SettingsNav`) from `@imajiner/admin-sdk/ui` ([frontend](frontend.md)), so the page shows the same menu as the settings sections. The CMS's Settings → SEO is one:

```php
NavigationItem::make('cms-seo')->label('SEO')->url('cms/seo')->icon('search')
    ->inSettings('site')->permission('cms.settings.site');
// In its controller: Inertia::render('Cms/Seo/Edit', ['settingsNav' => $panel->settingsNavigation($request->user()), …])
// In its page: <SettingsLayout items={settingsNav} current="cms-seo">…</SettingsLayout>
```

Items made from data (one per record) come from `navigationUsing()`, worked out on each page rather than at boot, so new and renamed records show at once. Keep the closure cheap (it runs on every page; cache what it reads), and give each item its own `permission()` or `visible()`. The CMS lists its collections and taxonomies this way.

```php
$panel->navigationUsing(fn () => Shop::query()->get()->map(
    fn (Shop $shop) => NavigationItem::make("shop-{$shop->handle}")->label($shop->name)
        ->url("shops/{$shop->handle}")->group('Shops')->permission("shop.{$shop->handle}.view")));
```

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
    Widget::cards('shop.help')->title('Getting started')->everyone()
        ->data(fn (): array => ['cards' => [['title' => 'Add a product', 'text' => 'Name, price and photos.', 'url' => 'shop/products/create', 'icon' => 'package']]]),
);
```

Each takes a `title`, a `description` (a line under it), an `icon` (a lucide name, as navigation takes), a `link` (the stat itself, or a list's "See all", with an optional label), `empty` (what a list or cards widget says with nothing in it), `sort` (lower first, 100 by default), `span` (1, 2 or 3 columns on wide screens), and `permission` and/or `visible(fn (Authorizable $user): bool)`, as navigation items do. Every widget must say who sees it: one with neither is refused when it's registered (`PanelMisconfigured`), and `everyone()` shows one to everybody who opens the panel on purpose. A plugin's keys start with its id and a dot.

`data` returns, by kind:

| Kind | Data |
| --- | --- |
| `stat` | `value` (a number or short text), optional `delta` ("+3 this week"), `trend` (`up` or `down`), `description`. |
| `list` | `rows`, each a `title` with optional `meta`, `url`, `badge`, `tone` (`neutral`, `success`, `warning`, `danger`) and `at` (an ISO 8601 time, shown as "5 minutes ago"); optional `count`, shown beside the title. |
| `cards` | `cards`, each a `title` with optional `text`, `url` and `icon`. |

URLs are relative to the panel's path (`shop/orders`), or start with `/` or `http(s)://`; anything else is dropped. Lists and cards show at most 20 items.

The page arrives with the widgets' definitions and paints at once; `data` runs afterwards, in a deferred request (`Inertia::defer`), one request per plugin and one for the panel's and the app's widgets, with placeholders meanwhile. It runs only for users who may see the widget, so it may query what it needs, but keep it to a query or two (no queries per row). A widget whose `data` throws, or returns something of the wrong shape, is reported (`report()`, so it's logged) and shown as unavailable; the rest of the dashboard is unaffected.

`$panel->widgetsFor($user)` gives the widgets a user sees, in order, and `$widget->resolve($user)` runs and checks one's data, for tests. The panel's own widgets are `sunrice.media` (the media library's size, with `sunrice.media.view`) and `sunrice.activity` (the last eight lines of the activity log, with `sunrice.activity.view`). The CMS adds its own (see [Collections](../../cms/docs/collections.md#the-dashboard)).

## Routes and pages

`$panel->routes(fn () => ...)` adds routes for signed-in users who may open the panel (the `sunrice.access` permission), under the panel's path and named `sunrice.admin.…`. Give each one its own permission as well (`->middleware('can:shop.orders.view')`, or a middleware of yours that decides): the panel's tests assert that every `sunrice.admin.*` route is behind sign-in and panel access, and the CMS's that each of its routes names a permission or content check. Plugin pages in React are explained in [Admin pages in React](frontend.md).

### Error pages

Under the panel's path, a 403, 404, 419 (the page expired) or 500 is the panel's own **Error** page, inside the admin layout for someone signed in, and the right status code. That covers `abort(403)`, a refused policy, a record that isn't found and an address the panel doesn't know (the panel's fallback route `sunrice.admin.missing`, after every other route; guests go to the login page). Requests that want JSON still get JSON, a 500 with `APP_DEBUG=true` keeps the framework's page with the stack trace, and pages outside the panel's path are left to the app. A refused page is still a `page refused` security event. The page name `Error` belongs to the panel.

### Command palette searches

The command palette (Cmd/Ctrl+K) lists the navigation on every page. `$panel->commandSearch($id, fn () => url, $minLength = 2)` adds a search it asks on every page too, without loading the plugin's JavaScript: once `$minLength` letters are typed (debounced) it requests `<url>?q=…` and lists the answer's `items`, each with an `id`, a `label` and optional `description`, `group` (the heading), `icon` (a navigation icon name) and `url` (the page it opens). The route guards itself like any other (an error answer lists nothing), and should answer quickly with a handful of items. The CMS searches entries this way (`cms.entries`, `GET admin/cms/entries/search?q=`, titles in every language).

```php
$panel->routes(fn () => Route::get('shop/orders/search', OrderSearch::class)->name('shop.orders.search')->middleware('can:shop.orders.view'));
$panel->commandSearch('shop.orders', fn () => route('sunrice.admin.shop.orders.search'));
```

Search results that need the browser (an action rather than a page) use [`registerCommandSource()`](frontend.md) in the plugin's bundle.

`$panel->installStep($key, fn (InstallOutput $output) => …)` adds a step to `php artisan sunrice:install`, for what a plugin needs the app to have: see [The install step](plugins.md#the-install-step).

## Help pages

The sidebar's **Help** group has two reading pages, each shown when there is something to read:

- **Guide** (`GET admin/guide/{page?}`, route `sunrice.admin.guide.show`, permission `sunrice.guide.view`): how to work in the panel, for editors and site managers, in their language.
- **Developer docs** (`GET admin/docs/{page?}`, route `sunrice.admin.docs.show`, permission `sunrice.docs.view`): how the panel and its plugins work, for developers.

Each is Markdown files from directories the panel and its plugins register, one sidebar section per directory, rendered on the server to HTML that is sanitised before it's sent (GitHub-flavoured Markdown; raw HTML is shown as text, scripts and unsafe links never pass). Pages get an **On this page** list of their `##` and `###` headings and links to the previous and next page.

```php
$panel->docs('Shop', dirname(__DIR__).'/docs');     // docs/orders.md → "shop/orders"
$panel->guide('Shop', dirname(__DIR__).'/guide');   // guide/en/orders.md, guide/id/orders.md → "shop/orders"
```

- A page is named `{slug of the title}/{file name}` and titled by its first `# ` heading. The panel registers its own as "Panel" (its package's `docs` and `guide` folders, so they ship with it), the CMS as "CMS" (`docs`) and "Website" (`guide`). A directory that doesn't exist is skipped; two registrations with one title are a `PanelMisconfigured`.
- **Guides** hold a folder per language (`en`, `id`, …). The reader picks the language with the switch at the top (`?lang=id`), and the choice is kept in the session; before that it's the app's locale or the browser's. A page missing in that language is shown in English, with a note. A leading number orders the files (`01-welcome.md` is the page `welcome`). `{panel}` reads as the panel's branded name (Settings → Branding).
- **Links** between pages are written as relative Markdown links (`[Menus](menus.md#links)`, `../admin/panel.md`) and open the page in the panel; `docs:cms/site` and `guide:website/entries` link the other set, shown as links only to readers who may open it. Links to files that aren't help pages keep only their text.
- **Images** for a guide go in its `images` folder, shared by the languages (`![The menu editor](images/menus.webp)`), and are served by `GET admin/guide/images/{section}/{file}` (`sunrice.admin.guide.image`, `sunrice.guide.view`): only `webp`, `png`, `jpg`, `gif` and `avif` files directly in that folder. Other images are left out.
- **Callouts**: a blockquote starting `[!TIP]`, `[!NOTE]`, `[!IMPORTANT]`, `[!WARNING]`, `[!CAUTION]` or `[!TECHNICAL]` (labelled "For developers") is a labelled box.

Every [default role](permissions.md#default-roles) that opens the panel (`sunrice.access`) also gets `sunrice.guide.view`; give `sunrice.docs.view` to whoever builds the site (the `administrator` role has it). The developer docs are also what AI agents read with `read_docs` ([AI access](ai.md#docs-for-agents)). The URL segments `docs` and `guide`, and the page folder `Help/`, belong to the panel.

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
