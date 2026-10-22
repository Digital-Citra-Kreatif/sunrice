# Admin pages in React

The panel's frontend is React 19 with Inertia. A plugin (or your app) adds its own admin pages: a Laravel route renders an Inertia page, and a small JavaScript bundle provides the React component.

## 1. The route

```php
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

public function register(Panel $panel): void
{
    $panel->routes(function () {
        Route::get('shop/orders', fn () => Inertia::render('Shop/Orders/Index', [
            'orders' => Order::query()->latest()->paginate(),
        ]))->name('shop.orders.index')->middleware('can:shop.orders.view');
    });
}
```

Routes added with `$panel->routes()` live under the panel's path (`/admin/shop/orders`), are named `sunrice.admin.…`, and are only reachable for signed-in users who may open the panel. Add the permission each route needs, as above.

## 2. The bundle

Build the page with `@imajiner/admin-sdk`. Its Vite plugin makes your bundle use the panel's React, Inertia and UI components instead of its own copies (a second React would break hooks, and the bundle stays a few kilobytes):

```bash
npm install --save-dev @imajiner/admin-sdk vite @vitejs/plugin-react react @types/react
```

```ts
// vite.config.ts
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';
import { sunrice } from '@imajiner/admin-sdk/vite';

export default defineConfig({
    plugins: [react(), sunrice({ entry: 'resources/js/admin.tsx' })], // → dist/admin.js
});
```

```tsx
// resources/js/admin.tsx
import { usePage } from '@inertiajs/react';
import { definePlugin, adminUrl } from '@imajiner/admin-sdk';
import { Button, Card, CardContent, useBreadcrumbs } from '@imajiner/admin-sdk/ui';

function OrdersIndex({ orders }: { orders: { data: { id: number; number: string }[] } }) {
    useBreadcrumbs([{ label: 'Orders' }]);

    return (
        <Card>
            <CardContent>{orders.data.map((order) => <div key={order.id}>{order.number}</div>)}</CardContent>
        </Card>
    );
}

definePlugin({ pages: { 'Shop/Orders/Index': OrdersIndex } });
```

Pages get the admin layout (sidebar and header). Set `Component.bare = true` for a page without it.

The header's breadcrumbs are what the page declares with `useBreadcrumbs()`; a page that declares none gets the navigation item whose address is the longest prefix of the page's (with its parent, for a submenu link), so `/admin/shop/orders/12` under an *Orders* item shows *Orders*.

Messages flashed as `success` or `error` (`back()->with('success', 'Saved.')`) become toasts. Each one shown carries an `id` in the page's `flash` data (`{ id, success, error }`, `id` null without a message), so the same text twice shows twice. A success message can carry a button, flashed as `success_action` with a `label`, a `url`, an optional `method` (`post`, the default, or `get`) and `data`: `->with('success', 'Moved to the trash.')->with('success_action', ['label' => 'Undo', 'url' => route('…restore', $entry)])`. The toast then stays eight seconds, and the button visits the URL (the route guards itself as usual).

## 3. Registering the bundle

Publish the built file to `public/` (for example with `$this->publishes([...], 'shop-assets')`) and tell the panel which pages it provides:

```php
use Sunrice\Admin\Assets\PluginBundle;

$panel->bundle(
    PluginBundle::make('shop')
        ->module(asset('vendor/shop/admin.js'))
        ->style(asset('vendor/shop/admin.css'))
        ->pages('Shop/')
        ->named('Acme Shop')
        ->publishedFrom(__DIR__.'/../dist', public_path('vendor/shop')),
);
```

`publishedFrom()` (optional) says where the package's built files are and where they're published: when a published file is missing or differs from the build (an update that didn't publish them, as when a package symlinked from a path changes without `composer update`), super admins see a warning at the top of the panel naming the bundle by `named()` ("Acme Shop"; its id without it), with the commands to run. The warning is part of the page (the sidebar and header start below it), and it's plain Blade, since the panel's React code may be the stale one. Publish under the `laravel-assets` tag too (`$this->publishes([...], ['shop-assets', 'laravel-assets'])`), which Laravel apps publish after every `composer update`.

### Styles

The panel's stylesheet already holds Tailwind's base styles and theme. Build only the utilities your pages use, **with a prefix of your own**, the theme's values written in and the panel's colours read from its CSS variables. Your pages then follow the panel's colours, dark mode and branding, without a second copy of Tailwind:

```css
/* resources/css/admin.css, imported by the bundle's entry */
@layer theme, base, components, utilities;
@import 'tailwindcss/theme.css' layer(theme) theme(reference inline) prefix(shop);
@import 'tailwindcss/utilities.css' layer(utilities) source('../js');

@custom-variant dark (&:is(.dark *));

@theme inline reference {
    --color-primary: var(--primary);
    --color-muted-foreground: var(--muted-foreground);
    /* … the panel's other colours: see packages/cms/resources/css/admin.css */
}
```

```tsx
<div className="shop:flex shop:gap-4 shop:md:grid-cols-2">…</div>
```

The prefix matters: your stylesheet loads after the panel's, so an unprefixed utility it happens to contain (Tailwind makes `.hidden` from any word "hidden" in your code) would override the panel's own responsive classes, hiding its sidebar for one. Prefixed utilities never meet the panel's.

The CMS's own bundle (`packages/cms`) is a complete example.

The panel loads the bundle the first time a page under `Shop/` opens, not on every admin page. Page prefixes are folders ending in `/`; two bundles can't claim the same pages, and the panel's own folders (`Auth/`, `Users/`, `Settings/`…) are reserved.

Your bundle can split: a dynamic `import()` (or `React.lazy`) becomes a chunk next to it, loaded when needed. Publish the whole build folder.

## UI components

`@imajiner/admin-sdk/ui` gives the panel's own components: `PageHeader` (the title block at the top of every page: `title`, `description`, `back={{ href, label }}`, children beside the title, and its actions in the panel's one pattern: `primary`, the page's one prominent button ("New entry"), and `menu`, the secondary actions and links in a "⋮" menu after it, `[{ label, href | onSelect, icon?, external?, download?, group?, destructive? }]` (Export CSV, settings, the blueprint…; items with a `group` sit together under it); `actions` puts other buttons before them; table controls such as Columns, filters and the trash belong in the table's toolbar, not the header), buttons, cards, dialogs, dropdown menus, inputs, selects, switches, tables, tabs, tooltips, toasts, `useBreadcrumbs`, `useUnsavedChanges(dirty, onSave)` (asks before leaving a form with unsaved edits, in the panel's own *Leave without saving?* dialog for links and the browser's for closing the tab, and saves on Ctrl/Cmd+S, as the panel's own forms do), `useEditLock`, `KeptEditsNotice` and `ViewingNotice` ([edit locks](locks.md)), `useTablePreferences(key, initial)` (a table's saved columns and page size, per user: see [preferences](preferences.md)), `DropdownMenuCheckboxItem` (for a Columns menu), `TrashTitle` and `TrashBanner` (a list showing its trash: the name with a red Trash badge, and a banner saying its `items` are deleted and show nowhere until restored, with a button back), `TrashHint` (an empty list's "1 is in the trash." with a button to it), `MediaPickerInput` (the media library picker of the panel's `MediaPicker` field: one id or an ordered list, limited to kinds of file), and `RichTextEditor` (the editor of the panel's `RichText` field: `id`, `value`, `onChange(html | null)`, `invalid`, `labelledBy`, `images`, `disabled`; TipTap loads in its own chunk when the first editor shows, so neither the panel's main script nor a plugin bundle carries it; sanitise the HTML on the server, as `RichText::sanitize()` does).

## Design language

The panel is calm and warm: neutral surfaces with a hint of warmth, one accent used sparingly, soft radii, two-layer shadows, and motion that is quick, eased out and purposeful. Plugin pages get the same look by reading the panel's tokens, which are CSS variables on `:root` (and `.dark`):

| Token | Use |
| --- | --- |
| `--background`, `--foreground`, `--card`, `--popover`, `--muted`, `--accent`, `--border`, `--input`, `--ring` … | The shadcn colour set; `--color-*` in your prefixed theme (see above). |
| `--accent-tint` (`bg-accent-tint`) | A faint wash of the primary colour: selected rows, the current nav item. |
| `--surface-glass` (`bg-surface-glass`) | Translucent chrome over `backdrop-blur`: the header, the sidebar, toasts. |
| `--ring-glow` (`ring-ring-glow`) | The soft 4 px focus glow: `focus-visible:ring-4 focus-visible:ring-ring-glow`, as inputs do. |
| `--radius` (0.625rem) | `rounded-lg`; `rounded-xl` for dialogs and toasts. |
| `--shadow-card` (`shadow-card`) | A hairline and a soft spread: cards, tiles on hover, the content panel. |
| `--shadow-pop` (`shadow-pop`) | Deeper: dialogs, menus, popovers, tooltips, toasts. |
| `--ease-out-quart` (`ease-out-quart`) | `cubic-bezier(0.22, 1, 0.36, 1)`: nearly everything that moves (150–300 ms). |
| `--ease-spring` (`ease-spring`) | A `linear()` spring with a touch of overshoot: dialogs entering, the switch thumb, the checkbox tick. |
| `--sunrice-font` | The typeface (Instrument Sans unless Settings → Branding or `sunrice-admin.font` sets another; see [Branding](branding.md)). |
| `--primary`, `--primary-foreground`, `--sidebar-primary` | The brand colour when one is set in Settings → Branding, with black or white text on it. |

Add the ones you use to your `@theme inline reference` block, as the CMS does (`--shadow-card: var(--shadow-card)`, `--ease-spring: var(--ease-spring)`, `--color-ring-glow: var(--ring-glow)`), and they become `shop:shadow-card`, `shop:ease-spring` and `shop:ring-ring-glow`.

The panel's stylesheet also ships a few plain classes, usable unprefixed from any bundle:

- `sunrice-page-title`: the display style of page titles (`PageHeader` uses it).
- `sunrice-page-enter`: a page's entrance, a short fade and rise; the layout puts it on every page.
- `sunrice-row-in`: rows and tiles arriving one after another. Give each `style={{ '--i': index }}`; the delay is 28 ms a row, capped at twelve.
- `sunrice-shimmer`: the gradient sweep of `Skeleton`.
- `sunrice-card-content`: a collapsible card body (`data-state="open|closed"`).
- `sunrice-atmosphere`, `sunrice-orb`, `sunrice-orbit`: the sign-in page's decoration.

Rows answer the pointer with `hover:bg-muted/40`; buttons press with `active:scale-[0.98]`. Page to page, the panel runs a view transition (`::view-transition-old/new(root)`) when the browser supports it. Under `prefers-reduced-motion: reduce` nothing travels: pages, rows and overlays appear in place, placeholders stop sweeping, and colour and opacity transitions survive, so keep your own travel animations on those classes or behind the same media query.

It also has these interaction components, the ones the panel's own pages use:

- **`DeveloperHelp`**: the **For developers** disclosure of a field's `developerHelp()`: `text` and `code` (shown as code, with a copy button), for help on a page of your own.
- **`SettingsLayout`** / **`SettingsNav`**: the Settings menu beside a page put among the settings sections (`NavigationItem::inSettings()`): `items` from `Panel::settingsNavigation($user)`, `current` the page's navigation key. `SettingsLayout` wraps the page in the same two columns as the settings sections (the menu as a scrolling row on phones).
- **`CollapsibleCard`**: a card whose header folds its content away, animated. `storageKey` remembers the choice in the browser, `hasErrors` opens the card so a validation error is never hidden, `headerAction` sits in the header outside the toggle. Folded fields stay in the form (still submitted) but are inert and hidden from assistive technology.

  ```tsx
  <CollapsibleCard title="Shipping" storageKey="shop:order:shipping" hasErrors={'address' in errors}>…</CollapsibleCard>
  ```

- **`SortableList`** and **`SortableHandle`**: a vertical list reordered by dragging a handle or with the keyboard (focus the handle, space, arrows, space). The dragged item follows the pointer as a lifted ghost; with reduced motion nothing travels. `onReorder` gets the reordered list, and `moveItem(items, from, to)` is the pure helper behind it. Give `getLabel` the item's name so screen readers hear "Home moved above About" while it moves (without it, items are named by position).

  ```tsx
  <SortableList items={rows} getId={(row) => row.id} getLabel={(row) => row.title} onReorder={setRows}>
      {(row, { handle }) => <div className="shop:flex shop:gap-2"><SortableHandle {...handle} />{row.title}</div>}
  </SortableList>
  ```

- **`DatePicker`**: a calendar in a popover, with a time input when `withTime`. Its value is what a native date input gives (`"2026-10-19"`, or `"2026-10-19T14:30"`), so it drops in where a date input was. It opens with focus on the selected day (else today, marked with a dot). The calendar is a keyboard grid: arrows move by a day or a week, PageUp/PageDown by a month (a year with Shift), Home/End to the ends of the week, Enter or Space picks the day.

- **`Command`**, `CommandInput`, `CommandList`, `CommandGroup`, `CommandItem`, `CommandEmpty`, `CommandSeparator`: a filterable list with a search box, for comboboxes and pickers. Items match on their `value` (their text when none is given) and `keywords` with the command palette's ranking, best first; `<Command shouldFilter={false}>` shows them as given (when a server already searched). The input is a combobox: Up/Down (wrapping round; `loop={false}` stops at the ends), Home/End move the active item, Enter or a click runs its `onSelect(value)`, hovering makes it active; `disabled` items are skipped. `CommandInput` takes `value`/`onValueChange`, `CommandGroup` a `heading`, and `CommandEmpty` shows when nothing matches.

- **`registerCommandSource(source, { minLength })`**: adds results to the command palette (Cmd/Ctrl+K, which lists the navigation out of the box). The source gets the query and an `AbortSignal` and returns items (`id`, `label`, `group`, optional `description`, `icon` (a navigation icon name), `url` (a full href from `adminUrl()`) or `onSelect`). It's asked after `minLength` letters (2 by default), debounced; one that throws or is aborted contributes nothing. It returns a function that removes the source. A bundle runs when one of its pages first opens, so a source registered at its top level is there from then on, but not on pages opened before. To search from every page (the Dashboard included), declare the search on the server instead with [`$panel->commandSearch()`](panel.md#command-palette-searches); the CMS does that for entries.

  ```ts
  registerCommandSource(async (query, signal) => {
      const response = await fetch(`${adminUrl('shop/orders/search')}?q=${encodeURIComponent(query)}`, { signal, headers: { Accept: 'application/json' } });
      return (await response.json()).map((order) => ({ id: String(order.id), label: order.number, group: 'Orders', url: adminUrl(`shop/orders/${order.id}`) }));
  });
  ```

Checking pages in a browser: the repository's `tests/Browser/smoke.mjs` visits every page of the workbench at phone and desktop sizes, light and dark, and fails on console errors, failed requests and horizontal overflow (see the README's "Developing this repository"). Plugin authors can copy it and swap in their own page list.

## Compatibility

`definePlugin()` checks the panel's runtime version and refuses to load a bundle built for another major version, with an error that says to rebuild it. The modules shared with plugins are listed in `@imajiner/admin-sdk/shared`.

## The panel's own assets

The panel ships its built JavaScript and CSS in `vendor/sunrice/admin/dist`. Publish them after installing or updating:

```bash
php artisan sunrice:assets
```

Until they are published, admin pages show a notice saying so. When the published copy differs from the package's build (an update that didn't publish them), super admins see a warning at the top of every page they load naming what's stale (`Sunrice`, and the plugins whose bundles say where they're published from), until `sunrice:assets` and `vendor:publish --tag=laravel-assets --force` have run.
