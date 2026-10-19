# Changelog

## Unreleased

- The admin shell: Inertia + React 19 layout with sidebar navigation, the dashboard, panel routes behind sign-in and `sunrice.access`, `php artisan sunrice:assets`. Plugin pages in React with `@sunrice/admin-sdk` (shared React/Inertia/UI, bundles loaded per page, runtime version check). See `docs/admin/frontend.md`.
- The Panel and plugin contract: `PanelProvider`, `Panel` (path, guard, brand, middleware), plugins with dependency checks, navigation, permissions and shared page data. See `docs/admin/panel.md`.
- Monorepo skeleton: `sunrice/admin` and `sunrice/cms` packages, tests, architecture boundary tests, CI.
