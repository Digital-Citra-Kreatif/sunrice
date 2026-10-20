# Upgrading

`sunrice/admin` and `sunrice/cms` are released together, with the same version number: update both at once.

## How to upgrade

1. Read this page from the version you have to the one you're going to, and `CHANGELOG.md` for everything else that changed.
2. Update the packages:

   ```bash
   composer update "sunrice/*"
   ```

3. Run the steps every update needs (see [Installation](../packages/admin/docs/installation.md#upgrading)):

   ```bash
   php artisan migrate               # also syncs the permissions
   php artisan sunrice:assets        # the panel's built assets
   php artisan vendor:publish --tag=laravel-assets --force   # the CMS's and other plugins' (composer usually runs it)
   php artisan view:clear
   php artisan queue:restart
   ```

4. Optionally, `php artisan sunrice:roles` gives the [default roles](../packages/admin/docs/permissions.md#default-roles) the permissions new features added.
5. If you build plugin bundles with `@sunrice/admin-sdk`, update it to the version matching the panel and rebuild them.

Until 1.0, APIs may change between versions; every change is recorded in `CHANGELOG.md`, and the ones that need something from you are collected here.

## From the previous development snapshot

What changed in the `Unreleased` version that may need a change in your app, templates or plugins:

- **PHP 8.4 or newer** is required.
- **A plugin's resources** are under the plugin's id: `/admin/shop/products` instead of `/admin/products`, and the CMS's structure pages under `/admin/cms/` (menu settings at `/admin/cms/menu-settings`). Use the route names (`sunrice.admin.resources.products.index`), which didn't change, instead of written-out addresses.
- **Entry and term editor addresses** are under their collection and taxonomy (`/admin/cms/collections/blog/entries/5/edit`). Links to the old ones still work (they redirect), but `route('sunrice.admin.cms.entries.edit', …)` now needs `['collection' => $handle, 'entry' => $id]`, and `…terms.edit` `['taxonomy' => $handle, 'term' => $id]`.
- **Plugin bundles**: `SDK_VERSION` is 2. Rebuild your plugin's admin bundle with the matching `@sunrice/admin-sdk`; the panel refuses bundles built for another version. See [Admin pages in React](../packages/admin/docs/frontend.md#compatibility).
- **Role rank is strict** (`PermissionRank`): a user or role someone manages, gives out, creates or edits must be strictly below them (all its permissions are theirs, and at least one of theirs is missing). Managers who could manage people with exactly their own access no longer can; give them a higher role if they need to. See [Users and roles](../packages/admin/docs/users.md#who-may-manage-whom).
- **`SecurityAlerts::PRIVILEGED_PERMISSIONS` is gone.** Mark your own powerful permissions with `$panel->privilegedPermissions()` (see [The panel and plugins](../packages/admin/docs/panel.md#privileged-permissions)); the alerts are now called *Someone / A role was given privileged access*.
- **Exports need their own permission**: a resource whose table is `->exportable()` registers `<prefix>.export` (a policy's `export()` decides when it has one), and the CMS's entry and submission exports need `cms.entries.export` (or `cms.entries.{collection}.export`) and `cms.submissions.export` (or `cms.submissions.{form}.export`). Viewing a list no longer lets anyone download it: run `php artisan migrate` (or `sunrice:sync-permissions`) and give the export permissions to the roles that should keep exporting (`sunrice:roles` gives them to the default `editor`). See [Resources](../packages/admin/docs/resources.md#who-may-do-what).
- **`TermsField::toAdmin()` sends `taxonomy` instead of the `terms` options**; the editor loads terms from `GET cms/term-picker`. Custom field types or bundles that read the `terms` options must ask the picker instead. See [Taxonomies and terms](../packages/cms/docs/taxonomies.md).
- **`docs` and `guide` are reserved resource keys** (and `Help/` a reserved page folder), for the panel's Help pages, as is `resources`. A resource with one of those keys must take another.
- **Run `php artisan view:clear` after updating**: `<x-sunrice-cms::form>` is now a class component, and views compiled with the old anonymous component fail. Forms' inputs are named `data[handle]` (the old names still post). See [Forms](../packages/cms/docs/forms.md).
- **`ForceDeleteEntry` and `DeleteTerm` refuse an entry or term other content points at** unless `force` is passed (bulk deletes skip it). Pass `force: true` where your code deletes on purpose. See [references](../packages/cms/docs/structure.md#references).
- **The site's words are translation keys** (`sunrice-cms::site.*`, in `resources/lang/{en,id}/site.php`): they were JSON strings such as `__('Comments are closed.')` before. Move overrides from `lang/id.json` to `lang/vendor/sunrice-cms/id/site.php`. See [the site's words](../packages/cms/docs/site.md#the-sites-words).
- **Shared fields inside translated groups, repeater rows and flexible blocks are shared row by row**: they were translated with their row before. Other languages now show the main language's value for them, read-only, and store only their own text. Check templates that relied on a different value per language, and mark such fields *Per language* in the blueprint. See [Languages](../packages/cms/docs/collections.md#languages).
- **Untranslated pages show the main language** (`sunrice-cms.site.fallback`, `SUNRICE_FALLBACK`, on by default): an entry not live in a secondary language is served with the main language's content at that language's address instead of a 404. Set `SUNRICE_FALLBACK=false` for the old behaviour. See [Untranslated pages](../packages/cms/docs/site.md#untranslated-pages).
- **Hydrated `link` fields have two new keys** (`type` and `title`; links can point at entries too), and **menu links have two new keys** (`active` and `active_trail`). Templates that compare or serialise these arrays whole should expect them. See [Fields](../packages/cms/docs/fields.md) and [Menus](../packages/cms/docs/menus.md).
- **`Globals::get()` reads the current language** when none is given, as `Cms::global()` does: it read the main one before. Pass the main language (`Locales::default()`) where you relied on that.
- **`SiteCache` keeps a content version per area** (`SiteCache::PAGES`, `ROUTES`, `MENUS`, `SITEMAP`): `remember($key, $make, $area = SiteCache::PAGES, $ttl = null)`, `key($key, $area)`, `version($area)` and `changed($what, ...$areas)`. Code that cached with `remember()` keeps working in the `pages` area; code that bumped the version itself calls `changed()` with the areas it affects. See [Caching](../packages/cms/docs/caching.md).
- **Page titles now end with the site name** ("About | Acme"), unless *Add the site name to titles* is turned off in Settings → Site. Templates that added the name themselves should stop, or turn the setting off.

Also worth checking, though not breaking for most apps:

- The settings keys `security` and `mail` belong to the panel's own sections: give your own settings sections other keys.
- The CMS requires `spatie/laravel-honeypot`, and its whole-page cache (`sunrice-cms.cache.pages`) needs `spatie/laravel-responsecache`; the `X-Sunrice-Cache` header is gone. See [Caching](../packages/cms/docs/caching.md).
- Every default role with `sunrice.access` now also gets `sunrice.guide.view` when `sunrice:roles` runs.
- New migrations, up to the CMS's `000021` (per-entry blueprints; `000019_add_main_version…` adds the outdated-translation column): run `php artisan migrate`.
- The rich text editor moved into the panel (`RichTextEditor` in `@sunrice/admin-sdk/ui`); its editing area's class is `sunrice-prose` (was `cms-prose`): update stylesheets that targeted the old class.
