# Architecture

Sunrice is two packages: **`sunrice/admin`**, an admin panel framework for
any Laravel app, and **`sunrice/cms`**, a CMS that is a plugin for that
panel. Other packages (commerce, payment gateways, the visual editor) are
plugins in the same way.

```
your app
└─ AdminPanelProvider: Panel::make()->path('admin')->plugin(...)
   ├─ sunrice/admin   panel, auth, users and roles, resources, pages,
   │                  settings, media, activity and security, AI access
   ├─ sunrice/cms     plugin: content and the site side
   └─ other plugins   (commerce → requires the CMS, visual editor …)
```

## Rules between packages

- **The panel knows no plugin.** Nothing in `Sunrice\Admin` refers to the
  CMS. A Laravel app can use the panel alone.
- **Plugins use the public API only.** Panel classes and methods meant for
  plugins are marked `@api`; everything else may change without notice.
  The CMS gets no special access: anything it needs, another plugin could
  use too.
- **No feature detection.** Plugins require the versions they need in
  `composer.json` instead of checking `method_exists`.

These are checked by the architecture tests in `tests/Arch`.

## The panel and plugins

The app configures one panel in a provider (details in `packages/admin/docs/panel.md`). Every package that adds to the
admin is a plugin:

```php
interface Plugin
{
    public function id(): string;                 // 'cms', 'commerce'
    /** @return array<int, string> */
    public function requires(): array;            // plugin ids it needs, e.g. ['cms']
    public function register(Panel $panel): void; // declare things
    public function boot(Panel $panel): void;     // runtime wiring
}
```

A plugin registers resources, React pages, navigation, permissions and
policies, settings sections, dashboard widgets, field types, AI tools,
shared page data, activity log subjects and health checks. The panel
validates them when it boots: a missing required plugin or a duplicate key
is an error, not a silent no-op.

## Frontend

The panel ships the admin shell (React, Inertia and the UI kit). Plugins
build their pages with `@sunrice/admin-sdk`, which makes their bundle use
the panel's single copy of React, Inertia and the components. A plugin's
bundle is loaded only when one of its pages is opened.

## Repository and releases

The packages live in this repository and are released together with the
same version number. CI publishes each package folder to its own read-only
repository for Packagist.

The starter kit (`packages/starter-kit`, `sunrice/starter-kit`) is a
third package beside the CMS, not a plugin the site needs: it installs
starter sites (structure, content, media, templates and assets) and makes
them from a site, using only the panel's and the CMS's `@api` classes
(`tests/Arch/PublicApiTest.php`). Neither the panel nor the CMS knows it.
