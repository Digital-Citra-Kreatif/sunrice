# Sunrice

Sunrice is an admin panel framework for Laravel, and a CMS built on it as a plugin. The panel gives any Laravel app a calm, fast admin: sign-in with two-factor login, users and roles, screens for your models described in PHP, React pages of your own, settings, a shared media library, an activity log with security alerts, AI access over MCP, and an in-panel user guide. The CMS adds content: collections and entries with drafts, scheduling and revisions, blueprints, taxonomies, globals, menus, forms and comments, in several languages, and serves them as your website.

| Package | What it is |
| --- | --- |
| [`sunrice/admin`](packages/admin) | The admin panel. Use it on its own in any Laravel app. |
| [`sunrice/cms`](packages/cms) | The CMS, a plugin for the panel: content and the site side. |

Both are released together with the same version number. Other plugins (commerce, the visual editor) live in their own repositories. Sunrice is in development, before 1.0: see the [changelog](CHANGELOG.md) and [upgrading](docs/upgrading.md).

## Requirements

PHP 8.4 or newer (with GD or Imagick), Laravel 13, and MySQL, MariaDB, PostgreSQL or SQLite. No Node in your app: the admin ships built.

## Quick start

```bash
composer require sunrice/cms          # or sunrice/admin for the panel alone
```

Give your user model `Spatie\Permission\Traits\HasRoles`, then add a panel provider to `bootstrap/providers.php`:

```php
use Sunrice\Admin\Panel;
use Sunrice\Admin\PanelProvider;
use Sunrice\Cms\CmsPlugin;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->path('admin')->brand('Acme')->plugin(CmsPlugin::make());
    }
}
```

```bash
php artisan sunrice:install --roles   # config, migrations, assets, storage link, permissions, roles, a super admin
```

Sign in at `/admin`. Run the scheduler and a queue worker on the server: see [Installation](packages/admin/docs/installation.md).

## Documentation

- [The docs index](docs/README.md): every page, for the panel ([`packages/admin/docs`](packages/admin/docs)) and the CMS ([`packages/cms/docs`](packages/cms/docs)).
- [Architecture](docs/architecture.md): how the packages fit together.
- The user guide for editors, in English and Indonesian, is inside the panel under **Help → Guide** (`packages/*/guide`).

## Developing this repository

```bash
composer install
npm ci && npm run build      # the SDK and the admin bundles (packages/*/dist)
composer serve               # the workbench app at http://127.0.0.1:8000
```

Sign in to the workbench at `/_workbench/login/admin@example.com`; it has demo content in English and Indonesian. Another host or port works as it is (the workbench builds its URLs from the address it is served on, not `APP_URL`): after one `composer serve`, run `vendor/bin/testbench serve --port=8001`.

```bash
composer test        # Pest: tests/Admin, tests/Cms, tests/Arch
composer lint        # Pint (composer format fixes)
composer analyse     # Larastan, level max
npm run typecheck && npm run test:frontend   # the admin's TypeScript and its node tests
```

The tests use in-memory SQLite; CI runs them on MySQL 8 and PostgreSQL 16 too. To do the same, point them at an empty database: `DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=sunrice DB_USERNAME=... DB_PASSWORD=... composer test` (or `pgsql`). A test that uses site search calls `commitForFullText()` (in `tests/Pest.php`) before searching: MySQL's FULLTEXT index sees committed rows only.

After UI work, sweep every admin page in a real browser (desktop and phone, light and dark): `tests/Browser/smoke.mjs` fails on JavaScript errors, console errors, failed requests and pages wider than the screen, and can save a screenshot of each page. It isn't part of `composer test` and Playwright isn't a dependency: install `playwright-core` anywhere and point the script at it and at a Chromium.

```bash
mkdir -p /tmp/pw && (cd /tmp/pw && npm init -y && npm i playwright-core)
composer serve   # in another terminal
PLAYWRIGHT_DIR=/tmp/pw CHROMIUM_PATH=/path/to/chrome SMOKE_SCREENSHOTS=/tmp/shots node tests/Browser/smoke.mjs
```

The script's header lists its options (`SUNRICE_URL`, `SMOKE_VIEWPORTS`, `SMOKE_SCHEMES`, `SMOKE_ONLY`, ...). Upload an image to the media library first, so the media details sheet has something to open.

Read [AGENTS.md](AGENTS.md) before contributing: the boundaries between the packages, the approved dependencies and the conventions.
