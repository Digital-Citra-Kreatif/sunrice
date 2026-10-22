# Installation

## Requirements

- **PHP 8.4** or newer, with the GD or Imagick extension for image sizes (Imagick is used when it's loaded), and `fileinfo`. The `intl` extension is optional: without it, the CMS names languages by their code unless you name them.
- **Laravel 13**.
- A database Laravel supports: **MySQL** or **MariaDB**, **PostgreSQL**, or **SQLite**. (The CMS's site search uses a full-text index on MySQL, MariaDB and PostgreSQL, and a slower scan on SQLite.)
- Nothing to build: the panel and the CMS ship their admin JavaScript and CSS built. Node is only needed to build your own plugin's pages.

## Installing

The panel alone, for any Laravel app:

```bash
composer require sunrice/admin
```

Or the CMS, which brings the panel with it:

```bash
composer require sunrice/cms
```

### The install command

```bash
php artisan sunrice:install --roles
```

It sets everything up, and it's safe to run again:

- **The user model.** The panel uses [spatie/laravel-permission](https://spatie.be/docs/laravel-permission) for roles, so first it offers to add the `HasRoles` trait to your user model (`App\Models\User`), before anything loads it. If it can't (a model it doesn't recognise), it stops and says so: add `use Spatie\Permission\Traits\HasRoles;` and `use HasRoles;` to the class yourself, then run the command again.
- **The panel provider.** When the app has none, it writes `app/Providers/AdminPanelProvider.php` (the panel at `/admin`, named after `APP_NAME`, with every installed plugin, such as the CMS) and adds it to `bootstrap/providers.php`. Edit it afterwards to change the path or the name, or to add your own resources and settings: see [The panel and plugins](panel.md). An app has one panel; an existing provider is left as it is.
- **The rest.** It publishes `config/sunrice-admin.php` (when you don't have it yet), runs the migrations, publishes the admin's assets (the panel's, and the plugins' through the `laravel-assets` tag), links `public/storage` for the media library, syncs the permissions, creates the [default roles](permissions.md#default-roles) (`--roles`), and offers to create a super admin (`--no-user` skips it; `php artisan sunrice:make-admin` does it later).

The provider it writes, for an app named Acme with the CMS:

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use Sunrice\Admin\Panel;
use Sunrice\Admin\PanelProvider;
use Sunrice\Cms\CmsPlugin;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->path('admin')
            ->brand('Acme')
            ->plugin(CmsPlugin::make());
    }
}
```

Sign in at `/admin`.

The CMS's config is published separately when you want to change it: `php artisan vendor:publish --tag=sunrice-cms-config`. Its starter templates for the site: `php artisan vendor:publish --tag=sunrice-cms-templates` (see [the site](../../cms/docs/site.md#starter-templates)).

## On the server

### The scheduler

Run Laravel's scheduler every minute:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

The panel schedules `sunrice:activity-prune` (old activity log lines) and `model:prune` for kept edits, daily; the CMS schedules `sunrice:publish-scheduled` every minute (scheduled entries go live and the caches clear) and `model:prune` for old form submissions and spam, daily.

### A queue worker

Security alert emails are always queued; the CMS queues its comment and form notifications; and image sizes are made on the queue whenever the default queue connection isn't `sync`. With `QUEUE_CONNECTION=database` (or `redis` …), keep a worker running with Supervisor, systemd or your host's process manager:

```bash
php artisan queue:work
```

With `sync`, queued work runs during the request instead. See [Mail](mail.md) for the mailer itself, and send a test email from **Settings → Mail** once it's set up.

### The storage link

The media library stores files on the `public` disk (`sunrice-admin.media.disk`), served through `public/storage`. `sunrice:install` makes the link; on a new server, run `php artisan storage:link`. Until it exists, the Media page warns uploaders. See [Media](media.md#headers-at-the-web-server) for headers to set on that folder at the web server (for Apache, `sunrice:install` writes them as an `.htaccess` there).

### Assets

The panel's built assets are copied to `public/vendor/sunrice-admin` by `php artisan sunrice:assets`, and the CMS's (and other plugins') to `public/vendor/…` by `php artisan vendor:publish --tag=laravel-assets --force`, which Laravel's own `composer.json` already runs after every `composer update`. Add the panel's to the same script, so an update never leaves old assets behind:

```json
"post-update-cmd": [
    "@php artisan vendor:publish --tag=laravel-assets --ansi --force",
    "@php artisan sunrice:assets --ansi"
]
```

Until the panel's are published, admin pages show only a notice saying so. After an update without publishing, the panel keeps running the old ones, and super admins see a warning at the top of each page saying which are older than the installed packages (the panel's and the CMS's, compared with the packages' builds) and what to run. With packages installed from a path (a symlinked `"type": "path"` repository), a change to them doesn't go through `composer update`, so its scripts don't run: run both commands yourself after changing them, or in your deploy script.

### Before going live

- `APP_ENV=production` and `APP_DEBUG=false`: with debug on, error pages show code, settings and secrets to anyone. Super admins see a warning at the top of each panel page while debug is on in production.
- `APP_URL` set to the site's `https://` address: links in emails and the sitemap are built from it ([Links in emails](security.md#links-in-emails)), and super admins see a warning while it's `http://` in production. Turn on Laravel's `TrustHosts` middleware too.
- TLS on the web server, with plain HTTP redirected to HTTPS, and `Strict-Transport-Security` (HSTS, e.g. `max-age=31536000`) once everything is served over HTTPS: at the web server, or for the site's pages in the CMS's `site.headers`.
- `SESSION_SECURE_COOKIE=true`, so the session cookie is never sent over plain HTTP.
- A real mailer (`MAIL_MAILER=smtp`, `ses`, `postmark` …, not `log` or `array`): password resets, two-factor codes and security alerts are emails. Send a test from **Settings → Mail**.
- `expose_php = Off` in `php.ini`, so responses don't announce the PHP version (`X-Powered-By`).
- Headers for the media directory at the web server ([Media](media.md#headers-at-the-web-server)).
- The scheduler and a queue worker (above).
- [Two-factor login](auth.md#two-factor-login) on (Settings → Security), recommended for any panel with administrators.
- A `Content-Security-Policy` for the panel (`SUNRICE_CSP`, see [Security headers](security.md#security-headers)) and, if your templates allow it, for the site (`SUNRICE_SITE_CSP` in the CMS's `site.headers`).

## Upgrading

After `composer update`:

```bash
php artisan migrate               # new tables and columns; also syncs the permissions
php artisan sunrice:assets        # the panel's assets (and laravel-assets, if composer didn't)
php artisan sunrice:roles         # optional: give the default roles new permissions
php artisan view:clear            # compiled views of components that changed
php artisan cache:clear           # with the CMS: also its caches and pages kept with the old templates
php artisan queue:restart         # workers pick up the new code
```

In production, `migrate` needs `--force`; [Upgrading](../../../docs/upgrading.md) has the steps for a production deploy.

Then read [Upgrading](../../../docs/upgrading.md) for the changes that need something from you, and the `CHANGELOG.md` for everything else. Run `migrate` after every update: new versions add migrations, and the permission sync that follows it creates the permissions new features need.
