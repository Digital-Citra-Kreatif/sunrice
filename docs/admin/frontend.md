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

Build the page with `@sunrice/admin-sdk`. Its Vite plugin makes your bundle use the panel's React, Inertia and UI components instead of its own copies (a second React would break hooks, and the bundle stays a few kilobytes):

```bash
npm install --save-dev @sunrice/admin-sdk vite @vitejs/plugin-react react @types/react
```

```ts
// vite.config.ts
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';
import { sunrice } from '@sunrice/admin-sdk/vite';

export default defineConfig({
    plugins: [react(), sunrice({ entry: 'resources/js/admin.tsx' })], // → dist/admin.js
});
```

```tsx
// resources/js/admin.tsx
import { usePage } from '@inertiajs/react';
import { definePlugin, adminUrl } from '@sunrice/admin-sdk';
import { Button, Card, CardContent, useBreadcrumbs } from '@sunrice/admin-sdk/ui';

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

## 3. Registering the bundle

Publish the built file to `public/` (for example with `$this->publishes([...], 'shop-assets')`) and tell the panel which pages it provides:

```php
use Sunrice\Admin\Assets\PluginBundle;

$panel->bundle(
    PluginBundle::make('shop')
        ->module(asset('vendor/shop/admin.js'))
        ->style(asset('vendor/shop/admin.css'))
        ->pages('Shop/'),
);
```

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

## UI components

`@sunrice/admin-sdk/ui` gives the panel's own components: buttons, cards, dialogs, dropdown menus, inputs, selects, switches, tables, tabs, tooltips, toasts, `useBreadcrumbs`, and `MediaPickerInput` (the media library picker of the panel's `MediaPicker` field: one id or an ordered list, limited to kinds of file).

## Compatibility

`definePlugin()` checks the panel's runtime version and refuses to load a bundle built for another major version, with an error that says to rebuild it. The modules shared with plugins are listed in `@sunrice/admin-sdk/shared`.

## The panel's own assets

The panel ships its built JavaScript and CSS in `vendor/sunrice/admin/dist`. Publish them after installing or updating:

```bash
php artisan sunrice:assets
```

Until they are published, admin pages show a notice saying so.
