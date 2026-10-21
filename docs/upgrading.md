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
- **Dashboard widgets must say who sees them**: a `Widget` without `->permission()` or `->visible()` now throws `PanelMisconfigured` when registered. Add `->everyone()` to one meant for everybody who opens the panel. See [Dashboard widgets](../packages/admin/docs/panel.md#dashboard-widgets).
- **One convention for admin addresses and route names**: `admin/<plugin>/<plural>`, what a record owns nested under its handle, and names that follow the address (`sunrice.admin.<plugin>.<area>.<action>`).
  - **Resources** are under the plugin's id (`/admin/shop/products`) and named after it: `sunrice.admin.shop.products.*` instead of `sunrice.admin.resources.products.*` (your app's: `sunrice.admin.products.*`). A relation field's options are at `/admin/shop/products/options/{field}`; bulk actions post to `…/bulk` with `action` in the body instead of `…/bulk/{action}`.
  - **The CMS's collections, taxonomies, forms, menus and globals are addressed by handle** (`/admin/cms/collections/blog/edit`; an old id address redirects). Handles can't be `create`, `export`, `bulk`, `options` or `picker`. Blueprints and fieldsets too (`/admin/cms/blueprints/article/edit`).
  - **Menus and globals have one address each**: `/admin/cms/menus` lists the menus (Content → Menus; the old Structure → Menu settings is gone), a menu's links are at `/admin/cms/menus/{handle}/links` (route `sunrice.admin.cms.menus.links.edit`), its title and handle at `…/{handle}/edit`. The same for `/admin/cms/globals` and `…/{handle}/values` (`sunrice.admin.cms.globals.values.edit`). The global sets' resource key is now `globals`.
  - **Entries, terms and submissions are named after their parent**: `sunrice.admin.cms.collections.entries.{index,create,store,edit,bulk,export}` (`['collection' => $handle, 'entry' => $id]`), `sunrice.admin.cms.taxonomies.terms.{index,create,store,edit}` and `sunrice.admin.cms.forms.submissions.{index,export}`. `cms.entries.index`, `cms.terms.index` and `cms.submissions.index` are the every-collection (taxonomy, form) addresses that go on to the first one (they were `.home`); `cms.entries.edit` and `cms.terms.edit` are the short addresses that redirect (they were `.open`). Links to the old addresses still work.
  - **Moved**: the pickers (`cms/entries/picker`, `cms/terms/picker`, routes `cms.entries.picker`, `cms.terms.picker`), restoring a revision (`POST cms/entries/{entry}/revisions/{revision}/restore`), machine translation and undoing a restore (`POST cms/entries/{entry}/translations/{locale}/translate`, `…/undo-restore`), and the listing page editor (`GET cms/collections/{handle}/listing/edit`, routes `cms.collections.listing.*`). `cms.comments.force` is `cms.comments.force-delete`.
  - **The trash is `?status=trashed` everywhere**: media (was `?trash=1`), terms (`?trashed=1`), comments (`?status=trash`) and resources (a model with `SoftDeletes` gets a trash of its own; the CMS's collections and taxonomies dropped their *Deleted* filter and *Restore* action for it).
  - **Pages**: `Resources/Form` is `Resources/Edit`, `Cms/Entries/Editor` is `Cms/Entries/Edit`, `Cms/Blueprints/Form` is `Cms/Blueprints/Edit` (and `Cms/Fieldsets/Edit`), `Cms/Entries/Listing` is `Cms/Listing/Edit`; `Cms/Menus/Index` and `Cms/Globals/Index` are gone (the resources list them).
- **Permissions split into view, create, update and delete**: `cms.terms.manage` (and `cms.terms.{taxonomy}.manage`) became `cms.terms.view`, `.create`, `.update`, `.delete`; `cms.blueprints.manage` became `cms.blueprints.*` and `cms.fieldsets.*`; `cms.global-sets.*` became `cms.globals.*`. `php artisan migrate` gives whoever had an old one the new ones. `Access::terms($user, $action, $taxonomy)` takes the action. The default `editor` role gets `cms.menus.view`/`.update` and `cms.globals.view`/`.update` only (not creating or deleting menus and sets): run `sunrice:roles` to apply it.
- **Plugin bundles**: `SDK_VERSION` is 3 (the runtime gained `t()`, the panel's words in the user's language). Rebuild your plugin's admin bundle with the matching `@sunrice/admin-sdk`; the panel refuses bundles built for another version. See [Admin pages in React](../packages/admin/docs/frontend.md#compatibility).
- **Role rank is strict** (`PermissionRank`): a user or role someone manages, gives out, creates or edits must be strictly below them (all its permissions are theirs, and at least one of theirs is missing). Managers who could manage people with exactly their own access no longer can; give them a higher role if they need to. See [Users and roles](../packages/admin/docs/users.md#who-may-manage-whom).
- **`SecurityAlerts::PRIVILEGED_PERMISSIONS` is gone.** Mark your own powerful permissions with `$panel->privilegedPermissions()` (see [The panel and plugins](../packages/admin/docs/panel.md#privileged-permissions)); the alerts are now called *Someone / A role was given privileged access*.
- **Exports need their own permission**: a resource whose table is `->exportable()` registers `<prefix>.export` (a policy's `export()` decides when it has one), and the CMS's entry and submission exports need `cms.entries.export` (or `cms.entries.{collection}.export`) and `cms.submissions.export` (or `cms.submissions.{form}.export`). Viewing a list no longer lets anyone download it: run `php artisan migrate` (or `sunrice:sync-permissions`) and give the export permissions to the roles that should keep exporting (`sunrice:roles` gives them to the default `editor`). See [Resources](../packages/admin/docs/resources.md#who-may-do-what).
- **`TermsField::toAdmin()` sends `taxonomy` instead of the `terms` options**; the editor loads terms from `GET cms/term-picker`. Custom field types or bundles that read the `terms` options must ask the picker instead. See [Taxonomies and terms](../packages/cms/docs/taxonomies.md).
- **`docs` and `guide` are reserved resource keys** (and `Help/` a reserved page folder), for the panel's Help pages, as is `resources`. A resource with one of those keys must take another.
- **Run `php artisan view:clear` after updating**: `<x-sunrice-cms::form>` is now a class component, and views compiled with the old anonymous component fail. Forms' inputs are named `data[handle]` (the old names still post). See [Forms](../packages/cms/docs/forms.md).
- **`ForceDeleteEntry` and `DeleteTerm` refuse an entry or term other content points at** unless `force` is passed (bulk deletes skip it). Pass `force: true` where your code deletes on purpose. See [references](../packages/cms/docs/structure.md#references).
- **The site's words are translation keys** (`sunrice-cms::site.*`, in `resources/lang/{en,id}/site.php`): they were JSON strings such as `__('Comments are closed.')` before. Move overrides from `lang/id.json` to `lang/vendor/sunrice-cms/id/site.php`. See [the site's words](../packages/cms/docs/site.md#the-sites-words). The entry filter's (`Filter`, `All`, `Sort by`, `Show results`, `Applied filters`, `Clear all`, `:count result|:count results`, `Min`/`Max`/`From`/`To`), the draft banner's and the comment unsubscribe page's words moved too, to `site.filter.*`, `site.draft.*` and `site.unsubscribed.*`.
- **Shared fields inside translated groups, repeater rows and flexible blocks are shared row by row**: they were translated with their row before. Other languages now show the main language's value for them, read-only, and store only their own text. Check templates that relied on a different value per language, and mark such fields *Per language* in the blueprint. See [Languages](../packages/cms/docs/collections.md#languages).
- **Untranslated pages show the main language** (`sunrice-cms.site.fallback`, `SUNRICE_FALLBACK`, on by default): an entry not live in a secondary language is served with the main language's content at that language's address instead of a 404. Set `SUNRICE_FALLBACK=false` for the old behaviour. See [Untranslated pages](../packages/cms/docs/site.md#untranslated-pages).
- **Hydrated `link` fields have two new keys** (`type` and `title`; links can point at entries too), and **menu links have two new keys** (`active` and `active_trail`). Templates that compare or serialise these arrays whole should expect them. See [Fields](../packages/cms/docs/fields.md) and [Menus](../packages/cms/docs/menus.md).
- **`Globals::get()` reads the current language** when none is given, as `Cms::global()` does: it read the main one before. Pass the main language (`Locales::default()`) where you relied on that.
- **`SiteCache` keeps a content version per area** (`SiteCache::PAGES`, `ROUTES`, `MENUS`, `SITEMAP`): `remember($key, $make, $area = SiteCache::PAGES, $ttl = null)`, `key($key, $area)`, `version($area)` and `changed($what, ...$areas)`. Code that cached with `remember()` keeps working in the `pages` area; code that bumped the version itself calls `changed()` with the areas it affects. See [Caching](../packages/cms/docs/caching.md).
- **Page titles now end with the site name** ("About | Acme"), unless *Add the site name to titles* is turned off in Settings → Site. Templates that added the name themselves should stop, or turn the setting off.
- **The page cache never keeps pages with `?q=` or `?reply_to=`** (`PageCache::UNKEPT_QUERY`), and `PageCacheHasher::QUERY` is `['page']` (was `['page', 'q']`). A template that reads `?q=` for something other than a search shows it uncached; code that read `PageCacheHasher::QUERY` should expect `q` gone. See [Caching](../packages/cms/docs/caching.md#whole-pages).
- **Listing and term pages' `$entries`** is an `Illuminate\Contracts\Pagination\LengthAwarePaginator` queried when first used (`Sunrice\Cms\Site\LazyEntries`), no longer an `Illuminate\Pagination\LengthAwarePaginator`: a component of yours type-hinting the class should take the contract. **On a hierarchical taxonomy's term page it includes the entries of the terms under the term**; a template that wants the term's own only can query them with `Cms::entries()->whereTerm($term->term)`. See [Taxonomies](../packages/cms/docs/taxonomies.md).
- **The comment unsubscribe page asks first**, and its view is `sunrice-cms::comment-unsubscribe` (was `comment-unsubscribed`; it gets `$done` and `$action` too). Move a published copy to `resources/views/cms/comment-unsubscribe.blade.php` (the site's own, used first) and add the confirm form. See [Comments](../packages/cms/docs/comments.md).
- **The `sunrice-forms` and `sunrice-comments` rate limiters are gone**: the form and comment controllers count sends themselves (only what was kept, `Site\SendLimit`) and send a visitor over the limit back to the form with a message (`_limit` in the error bag) instead of a 429 page; JSON requests still get `429`. Code that changed those limiters should set the form's **Sends per visitor a minute**, `sunrice-cms.forms.per_minute` or Settings → Comments instead. See [Forms](../packages/cms/docs/forms.md).

Also worth checking, though not breaking for most apps:

- **The default `administrator` role is no longer given `sunrice.ai.run-commands` and `sunrice.ai.error-log`** by `sunrice:roles`. Roles seeded before keep them: take them away in Roles if administrators' AI agents shouldn't run commands or read the error log. See [Default roles](../packages/admin/docs/permissions.md#default-roles).
- **The login code email's subject no longer has the code** ("Your … login code"); set `SUNRICE_LOGIN_CODE_IN_SUBJECT=true` (`auth.two_factor_code_in_subject`) to keep it there. See [Signing in](../packages/admin/docs/auth.md#two-factor-login).
- The settings keys `security` and `mail` belong to the panel's own sections: give your own settings sections other keys.
- The CMS requires `spatie/laravel-honeypot`, and its whole-page cache (`sunrice-cms.cache.pages`) needs `spatie/laravel-responsecache`; the `X-Sunrice-Cache` header is gone. See [Caching](../packages/cms/docs/caching.md).
- Every default role with `sunrice.access` now also gets `sunrice.guide.view` when `sunrice:roles` runs.
- New migrations, up to the CMS's `000021` (per-entry blueprints; `000019_add_main_version…` adds the outdated-translation column): run `php artisan migrate`.
- The rich text editor moved into the panel (`RichTextEditor` in `@sunrice/admin-sdk/ui`); its editing area's class is `sunrice-prose` (was `cms-prose`): update stylesheets that targeted the old class.
