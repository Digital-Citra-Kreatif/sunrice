# sunrice/admin

An admin panel framework for Laravel. Describe screens in PHP, add React pages where you need them, and get sign-in, users and roles, settings, media, an activity log, security alerts and AI access out of the box. It works in any Laravel app; [`sunrice/cms`](https://github.com/Digital-Citra-Kreatif/sunrice/tree/main/packages/cms) is a plugin for it.

Developed in [Digital-Citra-Kreatif/sunrice](https://github.com/Digital-Citra-Kreatif/sunrice); this package's own repository is a read-only copy. In development, before 1.0.

## Features

- **Signing in**: login, two-factor login by emailed code, password reset, three-way brute-force protection; only accounts with `sunrice.access` get in.
- **Users and roles** on spatie/laravel-permission, with permissions declared in code, default roles, and nobody granting more than they have.
- **Resources**: list and form screens for Eloquent models (search, filters, sorting, row and bulk actions, CSV export, relation and rich text fields), guarded by policies or permissions.
- **Your own pages** in React 19 with Inertia, built with `@sunrice/admin-sdk` on the panel's components and design language.
- **Settings** sections with typed properties and encrypted secrets; **Branding** (name, logo, font, colour, sign-in page) and **Mail** (with a test email) built in.
- **Dashboard widgets**, a **command palette**, per-user table preferences, **edit locks**.
- **Media library**: folders, uploads checked for scripts, image sizes, usage tracking, optimizing images.
- **Activity log** and **security**: security events, email alerts, account locks, security headers, captcha.
- **AI access**: tokens and an MCP endpoint where agents work with the user's permissions; tools for plugins.
- **Help**: the developer docs and a bilingual user guide inside the panel.
- **Plugins**: a small, checked contract for packages that add to the panel.

## Installing

PHP 8.4+, Laravel 13.

```bash
composer require sunrice/admin
php artisan sunrice:install --roles
```

Your user model needs `Spatie\Permission\Traits\HasRoles`, and your app a panel provider (`Sunrice\Admin\PanelProvider`). The full steps: [Installation](https://github.com/Digital-Citra-Kreatif/sunrice/blob/main/packages/admin/docs/installation.md).

## Assets

The panel's JavaScript and CSS are built in `dist/`. Publish them after installing and after every update:

```bash
php artisan sunrice:assets
```

## Documentation

- [Installation](https://github.com/Digital-Citra-Kreatif/sunrice/blob/main/packages/admin/docs/installation.md), [Configuration](https://github.com/Digital-Citra-Kreatif/sunrice/blob/main/packages/admin/docs/configuration.md), [Commands](https://github.com/Digital-Citra-Kreatif/sunrice/blob/main/packages/admin/docs/commands.md)
- [The panel and plugins](https://github.com/Digital-Citra-Kreatif/sunrice/blob/main/packages/admin/docs/panel.md), [Writing a plugin](https://github.com/Digital-Citra-Kreatif/sunrice/blob/main/packages/admin/docs/plugins.md), [Resources](https://github.com/Digital-Citra-Kreatif/sunrice/blob/main/packages/admin/docs/resources.md), [Admin pages in React](https://github.com/Digital-Citra-Kreatif/sunrice/blob/main/packages/admin/docs/frontend.md)
- [All the docs](https://github.com/Digital-Citra-Kreatif/sunrice/blob/main/docs/README.md) and [Upgrading](https://github.com/Digital-Citra-Kreatif/sunrice/blob/main/docs/upgrading.md)
