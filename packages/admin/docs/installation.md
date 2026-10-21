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

### 1. The user model

The panel uses [spatie/laravel-permission](https://spatie.be/docs/laravel-permission) for roles: give your user model its trait.

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

### 2. The panel provider

Create `app/Providers/AdminPanelProvider.php` and add it to `bootstrap/providers.php`:

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
            ->path('admin')            // https://example.com/admin
            ->brand('Acme')            // the name in the sidebar and emails (Settings → Branding can change it)
            ->plugin(CmsPlugin::make());   // leave out without the CMS
    }
}
```

An app has one panel. Everything else it can do (another guard, extra middleware, turning off the media library or AI access, your own resources and settings) is in [The panel and plugins](panel.md).

### 3. The install command

```bash
php artisan sunrice:install --roles
```

It publishes `config/sunrice-admin.php` (when you don't have it yet), runs the migrations, publishes the admin's assets (the panel's, and the plugins' through the `laravel-assets` tag), links `public/storage` for the media library, syncs the permissions, creates the [default roles](permissions.md#default-roles) (`--roles`), and offers to create a super admin (`--no-user` skips it; `php artisan sunrice:make-admin` does it later). It's safe to run again.

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

## Upgrading

After `composer update`:

```bash
php artisan migrate               # new tables and columns; also syncs the permissions
php artisan sunrice:assets        # the panel's assets (and laravel-assets, if composer didn't)
php artisan sunrice:roles         # optional: give the default roles new permissions
php artisan view:clear            # compiled views of components that changed
php artisan queue:restart         # workers pick up the new code
php artisan responsecache:clear   # with the CMS's page cache on (SUNRICE_PAGE_CACHE): pages kept with the old templates
```

Then read [Upgrading](../../../docs/upgrading.md) for the changes that need something from you, and the `CHANGELOG.md` for everything else. Run `migrate` after every update: new versions add migrations, and the permission sync that follows it creates the permissions new features need.
