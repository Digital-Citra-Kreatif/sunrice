# @imajiner/admin-sdk

Build admin pages for [Sunrice](https://github.com/Digital-Citra-Kreatif/sunrice) panel plugins in React. The Vite plugin makes your bundle use the panel's own React, Inertia and UI components instead of its own copies, so hooks work and the bundle stays a few kilobytes.

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
import { definePlugin } from '@imajiner/admin-sdk';
import { Card, CardContent, useBreadcrumbs } from '@imajiner/admin-sdk/ui';

function Reports() {
    useBreadcrumbs([{ label: 'Reports' }]);

    return <Card><CardContent>Hello from a plugin</CardContent></Card>;
}

definePlugin({ pages: { 'Shop/Reports': Reports } });
```

| Import | What it gives |
| --- | --- |
| `@imajiner/admin-sdk` | `definePlugin()` to register pages (or loaders for code-split pages), `t()` for the panel's translations, `adminUrl()`. |
| `@imajiner/admin-sdk/ui` | The panel's UI components and hooks (buttons, cards, forms, tables, dialogs, `useBreadcrumbs`…). |
| `@imajiner/admin-sdk/vite` | `sunrice({ entry, outDir?, fileName? })`, the Vite plugin. |

Use the SDK version that matches your `sunrice/admin` version (`0.7.x` with the panel's `0.7.x`): they're released together.

The panel's docs explain the PHP side (registering the bundle, routes and permissions) and the full UI: [Admin pages in React](https://github.com/Digital-Citra-Kreatif/sunrice/blob/main/packages/admin/docs/frontend.md).

## License

MIT
