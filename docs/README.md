# Sunrice documentation

Developer documentation for `sunrice/admin` (the panel) and `sunrice/cms` (the CMS, a plugin for the panel). Inside the panel, the same pages are on **Help → Developer docs**; the user guide for editors is on **Help → Guide** (its files are in `packages/admin/guide` and `packages/cms/guide`).

## Start here

- [Installation](admin/installation.md): requirements, installing, the panel provider, the scheduler, the queue worker, assets, upgrading.
- [Upgrading](upgrading.md): how to upgrade, and the changes that need something from you.
- [Architecture](architecture.md): how the panel, the CMS and other plugins fit together.

## The panel (`sunrice/admin`)

| Page | |
| --- | --- |
| [The panel and plugins](admin/panel.md) | The panel provider, plugins, navigation, dashboard widgets, routes, error pages, shared data, Help pages, what users own, privileged permissions. |
| [Writing a plugin: the Shop example](admin/plugins.md) | A complete plugin, and testing it with Testbench. |
| [Configuration](admin/configuration.md) | Every key of `config/sunrice-admin.php`. |
| [Commands](admin/commands.md) | Every panel Artisan command. |
| [Signing in](admin/auth.md) | Login, two-factor login, brute-force protection, password reset. |
| [Roles and permissions](admin/permissions.md) | spatie/laravel-permission, the first admin, super admins, permissions from code and data, default roles. |
| [Users and roles](admin/users.md) | The Users and Roles screens, who may manage whom, deleting a user. |
| [Resources](admin/resources.md) | Admin screens for Eloquent models: forms, tables, actions, export. |
| [Settings](admin/settings.md) | Typed settings sections. |
| [Branding](admin/branding.md) | Settings → Branding: name, logo, font, colour, sign-in page. |
| [Admin pages in React](admin/frontend.md) | Plugin pages with `@sunrice/admin-sdk`, styles, UI components, the design language. |
| [Preferences](admin/preferences.md) | Per-user preferences (table columns and page size). |
| [Edit locks](admin/locks.md) | One editor at a time, kept edits. |
| [Media](admin/media.md) | The media library, usage, optimizing images, picking media. |
| [Mail](admin/mail.md) | What the panel sends, the mailer, the queue worker, Settings → Mail. |
| [Activity log](admin/activity.md) | Who changed what; recording your own; pruning. |
| [Security](admin/security.md) | Security events, accounts in the wrong hands, headers, email alerts and your own rules, captcha. |
| [AI access](admin/ai.md) | Tokens, the MCP endpoint, the panel's tools, tools from plugins, docs for agents. |

## The CMS (`sunrice/cms`)

| Page | |
| --- | --- |
| [Collections and entries](cms/collections.md) | Collections, editing entries, the entries list, languages, drafts and publishing, revisions, the dashboard. |
| [Fields, blueprints and fieldsets](cms/fields.md) | Field types, the builder, your own field types. |
| [Taxonomies and terms](cms/taxonomies.md) | Taxonomies, terms, term pages. |
| [Globals](cms/globals.md) | Global sets. |
| [Menus](cms/menus.md) | Menus and their links. |
| [Forms](cms/forms.md) | Forms, submissions, the form component. |
| [Comments](cms/comments.md) | Comments, moderation, Settings → Comments. |
| [The site](cms/site.md) | URLs, templates, SEO, site code, layout helpers, redirects, listing pages, querying entries, components, search, previews. |
| [Helpers and the `Cms` facade](cms/helpers.md) | `Cms::…`, the `cms_*` functions, `@bodyClass`, the `EntryQuery` reference. |
| [Caching, events and scheduled publishing](cms/caching.md) | The site's cache, the whole-page cache, content events. |
| [Machine translation](cms/translation.md) | Translators, `sunrice:translate`. |
| [Structure tooling](cms/structure.md) | References, renaming fields, export and import, leftovers, demo content. |
| [AI tools for content](cms/ai.md) | The CMS's tools for AI agents. |
| [Configuration](cms/configuration.md) | Every key of `config/sunrice-cms.php`. |
| [Commands](cms/commands.md) | Every CMS Artisan command. |
