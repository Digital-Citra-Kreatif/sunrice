# Working on Sunrice

This repository holds two packages, published separately:

- `packages/admin` → `sunrice/admin`, namespace `Sunrice\Admin\`: the admin panel framework. It must work in a Laravel app **without** the CMS.
- `packages/cms` → `sunrice/cms`, namespace `Sunrice\Cms\`: the CMS, a plugin for the panel.

Other packages (sunrice-commerce, its payment and shipping gateways, sunrice-visual-editor) live in their own repositories and are plugins too. Read `docs/architecture.md` before changing how packages fit together.

## Rules

- One change per branch and pull request, with a title that says what it does.
- Every change ships Pest tests (Orchestra Testbench). Before opening a PR run `composer test`, `composer lint` and `composer analyse`; for frontend changes also `npm run typecheck && npm run test:frontend && npm run build`.
- **Boundaries** (enforced by `tests/Arch`):
  - The panel never uses `Sunrice\Cms` or any other plugin.
  - The CMS and other plugins use only panel classes and methods marked `@api`. If a plugin needs something that isn't `@api`, make it a deliberate public API (documented and tested) instead of reaching in.
  - No feature detection (`method_exists`, reflection) between packages: a plugin states the versions it needs in `composer.json`.
- **Security by default**: every route, page, resource and action declares the permission or policy that guards it, and an unguarded one is a failing test. Validate all input, escape all output, never trust plugin or user HTML without sanitising it.
- **Performance**: no queries in loops (eager load), no work at boot that can wait until it's needed, lazy Inertia props for anything expensive, plugin JS loaded only on the pages that use it.
- Larastan runs at `level: max`. Don't add ignores to `phpstan.neon` without a comment saying why.
- Until 1.0 there is no backwards compatibility to keep: improve APIs freely, but record every change in `CHANGELOG.md`. After 1.0, never modify a released migration and keep developer-facing APIs compatible.
- Update the docs when adding or changing developer-facing API: each package's own pages live in its `docs/` (`packages/admin/docs`, `packages/cms/docs`, shipped with the package and shown on Help → Developer docs), cross-cutting ones (architecture, upgrading, the index) in the root `docs/`. Relative links between them are checked by `tests/Arch/DocsLinksTest.php`.
- Do not commit `node_modules/`, `vendor/`, `composer.lock` or `.env`. Do commit compiled admin assets in each package's `dist/`.

## Approved dependencies

- Composer, `sunrice/admin`: `illuminate/*`, `spatie/laravel-package-tools`, `inertiajs/inertia-laravel`, `spatie/laravel-permission`, `intervention/image` (v3+), `spatie/simple-excel`, `symfony/html-sanitizer`, `laravel/mcp`, `league/commonmark` (Markdown for the Help pages; ships with every Laravel install).
- Composer, `sunrice/cms`: the above plus `spatie/laravel-honeypot`, `spatie/laravel-sitemap`, `spatie/laravel-responsecache` (optional, suggested).
- Dev: `orchestra/testbench`, `pestphp/pest`, `pestphp/pest-plugin-laravel`, `laravel/pint`, `larastan/larastan`.
- npm (admin): React 19, Inertia, TypeScript, Vite, Tailwind v4, shadcn/ui components with their Radix dependencies, `lucide-react`, `@tanstack/react-table`, `@dnd-kit/*`, TipTap, `sonner`, `date-fns`.
- Anything else: explain why in the PR instead of adding it.

## Out of scope

Multiple panels in one app, multi-tenancy, a headless/REST API, frontend search beyond the CMS's site search, nested page URLs, manual image cropping and focal points, and automatically generated menus.

## Conventions

- Every PHP file starts `declare(strict_types=1);`.
- Write operations live in single-purpose action classes (`src/Actions/<Area>/`) with a public `handle()`. Controllers stay thin.
- Config: `config/sunrice-admin.php` (key `sunrice-admin`) and `config/sunrice-cms.php` (key `sunrice-cms`). Views: `sunrice-admin::` and `sunrice-cms::`. Tables are prefixed `sunrice_`.
- Permission names `sunrice.<area>.<action>` (panel) and `cms.<area>.<action>` (CMS); plugins use their own prefix. Admin route names `sunrice.admin.<area>.<action>`.
- Tests live in `tests/Admin` (panel alone), `tests/Cms` (panel + CMS), `tests/Arch` and `tests/Frontend` (node:test).
- `packages/admin-sdk` is the npm package `@sunrice/admin-sdk` for plugin bundles; keep `SHARED_MODULES` in step with the runtime (`tests/Frontend` checks).

## Commands

```bash
composer test      # Pest
composer lint      # Pint (test mode)
composer format    # Pint (fix)
composer analyse   # Larastan
composer serve     # the workbench app; sign in at /_workbench/login/admin@example.com
npm run build      # the SDK and the panel's frontend (packages/admin/dist)
npm run test:frontend
```
