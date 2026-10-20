# Commands

The CMS's Artisan commands. The panel's own (installing, roles and permissions, accounts, media, assets, the activity log) are in its docs; see [panel commands](#panel-commands) below.

| Command | What it does |
| --- | --- |
| `sunrice:publish-scheduled` | Announces entries whose scheduled publish time came: clears the site's caches and fires `EntryPublished` (`scheduled`). The scheduler runs it every minute; see [caching](caching.md). |
| `sunrice:rename-field {definition} {old} {new} [--dry-run]` | Renames a blueprint's or fieldset's field (`fieldset:cta`, dot paths like `hero.title`) and moves the values stored under it. See [renaming a field](structure.md#renaming-a-field). |
| `sunrice:export-structure {file} [--entries]` | Writes fieldsets, blueprints, collections (with listing pages), taxonomies, global sets, forms, menus and redirects as JSON; `--entries` adds terms, entries and global values. |
| `sunrice:import-structure {file} [--dry-run] [--force]` | Creates or updates, by handle, what such a file describes; `--force` lets fieldsets and blueprints lose fields. Fails (and saves nothing) on an invalid item, or when something was refused. See [exporting and importing](structure.md#exporting-and-importing-structure). |
| `sunrice:orphans [--purge] [--force]` | Lists deleted collections and taxonomies with what they keep, media whose file is missing, and fields pointing at missing entries or terms; `--purge` deletes the deleted collections and taxonomies for good (asking first unless `--force`). See [leftovers](structure.md#leftovers). |
| `sunrice:demo-content [--fresh]` | Fills the site with a small demo; `--fresh` deletes the demo's structure first. See [demo content](structure.md#demo-content). |

The CMS also schedules Laravel's `model:prune` daily for old form submissions and comments ([forms](forms.md), [comments](comments.md)).

## Panel commands

| Command | Docs |
| --- | --- |
| `sunrice:install`, `sunrice:roles`, `sunrice:sync-permissions`, `sunrice:make-admin` | [Permissions](../admin/permissions.md) |
| `sunrice:two-factor` | [Signing in](../admin/auth.md) |
| `sunrice:unlock` | [Security](../admin/security.md) |
| `sunrice:media-sizes`, `sunrice:optimize-images` | [Media](../admin/media.md) |
| `sunrice:assets` | [Frontend](../admin/frontend.md) |
| `sunrice:activity-prune` | [Activity](../admin/activity.md) |
