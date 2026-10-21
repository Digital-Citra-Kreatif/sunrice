# Sunrice documentation

Developer documentation for `sunrice/admin` (the panel) and `sunrice/cms` (the CMS, a plugin for the panel). Each package ships its own pages in its `docs` folder (`packages/admin/docs`, `packages/cms/docs`); this folder holds the index and what spans both. Inside the panel, the same pages are on **Help → Developer docs**; the user guide for editors is on **Help → Guide** (its files are in `packages/admin/guide` and `packages/cms/guide`).

## Start here

- [Installation](../packages/admin/docs/installation.md): requirements, installing, the panel provider, the scheduler, the queue worker, assets, upgrading.
- [Upgrading](upgrading.md): how to upgrade, and the changes that need something from you.
- [Architecture](architecture.md): how the panel, the CMS and other plugins fit together.

## The panel (`sunrice/admin`)

| Page | |
| --- | --- |
| [The panel and plugins](../packages/admin/docs/panel.md) | The panel provider, plugins, navigation, dashboard widgets, routes, error pages, shared data, Help pages, what users own, privileged permissions. |
| [Writing a plugin: the Shop example](../packages/admin/docs/plugins.md) | A complete plugin, and testing it with Testbench. |
| [Configuration](../packages/admin/docs/configuration.md) | Every key of `config/sunrice-admin.php`. |
| [Commands](../packages/admin/docs/commands.md) | Every panel Artisan command. |
| [Signing in](../packages/admin/docs/auth.md) | Login, two-factor login, brute-force protection, password reset. |
| [Roles and permissions](../packages/admin/docs/permissions.md) | spatie/laravel-permission, the first admin, super admins, permissions from code and data, default roles. |
| [Users and roles](../packages/admin/docs/users.md) | The Users and Roles screens, who may manage whom, deleting a user. |
| [Resources](../packages/admin/docs/resources.md) | Admin screens for Eloquent models: forms, tables, actions, export. |
| [Settings](../packages/admin/docs/settings.md) | Typed settings sections. |
| [Branding](../packages/admin/docs/branding.md) | Settings → Branding: name, logo, font, colour, sign-in page. |
| [Admin pages in React](../packages/admin/docs/frontend.md) | Plugin pages with `@sunrice/admin-sdk`, styles, UI components, the design language. |
| [Preferences](../packages/admin/docs/preferences.md) | Per-user preferences (table columns and page size). |
| [Panel languages](../packages/admin/docs/languages.md) | Each user's panel language, apart from the site's; translations for the panel and plugins. |
| [Edit locks](../packages/admin/docs/locks.md) | One editor at a time, kept edits. |
| [Media](../packages/admin/docs/media.md) | The media library, usage, optimizing images, picking media. |
| [Mail](../packages/admin/docs/mail.md) | What the panel sends, the mailer, the queue worker, Settings → Mail. |
| [Activity log](../packages/admin/docs/activity.md) | Who changed what; recording your own; pruning. |
| [Security](../packages/admin/docs/security.md) | Security events, accounts in the wrong hands, headers, email alerts and your own rules, captcha. |
| [AI access](../packages/admin/docs/ai.md) | Tokens, the MCP endpoint, the panel's tools, tools from plugins, docs for agents. |

## The CMS (`sunrice/cms`)

| Page | |
| --- | --- |
| [Collections and entries](../packages/cms/docs/collections.md) | Collections, editing entries, the entries list, languages, drafts and publishing, revisions, the dashboard. |
| [Fields, blueprints and fieldsets](../packages/cms/docs/fields.md) | Field types, the builder, your own field types. |
| [Taxonomies and terms](../packages/cms/docs/taxonomies.md) | Taxonomies, terms, term pages. |
| [Globals](../packages/cms/docs/globals.md) | Global sets. |
| [Menus](../packages/cms/docs/menus.md) | Menus and their links. |
| [Forms](../packages/cms/docs/forms.md) | Forms, submissions, the form component. |
| [Comments](../packages/cms/docs/comments.md) | Comments, moderation, Settings → Comments. |
| [The site](../packages/cms/docs/site.md) | URLs, templates, SEO, site code, layout helpers, redirects, listing pages, querying entries, components, search, previews. |
| [Helpers and the `Cms` facade](../packages/cms/docs/helpers.md) | `Cms::…`, the `cms_*` functions, `@bodyClass`, the `EntryQuery` reference. |
| [Caching, events and scheduled publishing](../packages/cms/docs/caching.md) | The site's cache, the whole-page cache, Settings → Cache and clearing the caches, content events. |
| [Machine translation](../packages/cms/docs/translation.md) | Translators, `sunrice:translate`. |
| [Structure tooling](../packages/cms/docs/structure.md) | References, renaming fields, export and import, leftovers, demo content. |
| [AI tools for content](../packages/cms/docs/ai.md) | The CMS's tools for AI agents. |
| [Configuration](../packages/cms/docs/configuration.md) | Every key of `config/sunrice-cms.php`. |
| [Commands](../packages/cms/docs/commands.md) | Every CMS Artisan command. |
