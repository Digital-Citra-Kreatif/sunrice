# Changelog

## Unreleased

- Resources: admin screens for Eloquent models described in PHP (forms with sections and six field types, lists with search, filters, sorting, row and bulk actions and CSV export), authorized by the model's policy or the resource's permissions. See `docs/admin/resources.md`.
- Roles and permissions with spatie/laravel-permission: its tables ship with the panel, permissions sync from code (`sunrice:sync-permissions`, also after every migrate), a super admin role that may do everything, and `sunrice:make-admin` for the first admin. See `docs/admin/permissions.md`.
- Signing in: login (with remember me), logout and password reset pages; only accounts with `sunrice.access` get in; three-way brute-force throttling (`config/sunrice-admin.php`) and the `LoginThrottled` event. See `docs/admin/auth.md`.
- The admin shell: Inertia + React 19 layout with sidebar navigation, the dashboard, panel routes behind sign-in and `sunrice.access`, `php artisan sunrice:assets`. Plugin pages in React with `@sunrice/admin-sdk` (shared React/Inertia/UI, bundles loaded per page, runtime version check). See `docs/admin/frontend.md`.
- The Panel and plugin contract: `PanelProvider`, `Panel` (path, guard, brand, middleware), plugins with dependency checks, navigation, permissions and shared page data. See `docs/admin/panel.md`.
- Monorepo skeleton: `sunrice/admin` and `sunrice/cms` packages, tests, architecture boundary tests, CI.
