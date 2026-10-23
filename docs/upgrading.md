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
   php artisan cache:clear           # also the site's caches and kept pages, wherever they're kept
   php artisan queue:restart
   ```

   **In production**, deploy the update as you deploy your own code, and run the same steps with `--force` on `migrate` (without it, Laravel stops to ask in production):

   ```bash
   php artisan down                  # optional: visitors get the maintenance page meanwhile
   composer install --no-dev --optimize-autoloader   # with the composer.lock you updated and tested
   php artisan migrate --force
   php artisan sunrice:assets
   php artisan vendor:publish --tag=laravel-assets --force
   php artisan optimize:clear        # config, routes, views, events and the cache (the site's caches and kept pages too)
   php artisan optimize              # if you cache config and routes
   php artisan queue:restart         # workers pick up the new code
   php artisan up
   ```

   `cache:clear` (which `optimize:clear` runs) empties the app's default cache store; the CMS then clears its own caches and the whole-page cache's own store too, so pages kept with the old templates aren't served. If your app keeps things in the default store that must survive a deploy, run `php artisan view:clear` and `php artisan responsecache:clear` instead, and **Clear all site caches** on Settings → Cache ([Caching](../packages/cms/docs/caching.md#clearing-the-caches)). With **Preload after clearing the cache** on, the clear queues a preload; queue workers take no jobs while the app is down, so it runs once it's up, with the new code.

4. Optionally, `php artisan sunrice:roles` gives the [default roles](../packages/admin/docs/permissions.md#default-roles) the permissions new features added.
5. If you build plugin bundles with `@imajiner/admin-sdk`, update it to the version matching the panel and rebuild them.

Until 1.0, APIs may change between versions; every change is recorded in `CHANGELOG.md`, and from the first release on, the ones that need something from you are collected here, by version.

## Unreleased

- **Strong passwords are on, for existing installs too**: new passwords set in the panel need 12 characters or more with upper and lower case letters, a number and a symbol, and are checked against known data leaks (a request to `api.pwnedpasswords.com`; allow it out of your firewall, or set `SUNRICE_PASSWORD_UNCOMPROMISED=false`). People whose password doesn't meet the rules are asked to choose a stronger one the next time they sign in. Scripts or tests that create users through the panel's forms or `sunrice:make-admin --password` with simple passwords need stronger ones. To keep the old behaviour (your app's `Password::defaults()`), turn off **Require strong passwords** in Settings → Security, or set `sunrice-admin.auth.strong_passwords` to `false`. See [Strong passwords](../packages/admin/docs/auth.md#strong-passwords).
- **Entries' terms come from their collection's taxonomies** (`php artisan migrate` runs `2026_10_23_000001` and `2026_10_23_000002`): the editor's **Taxonomies** card chooses an entry's terms for the taxonomies attached to its collection, and **Terms** fields no longer give entries their terms. Your site shows the same terms after the upgrade (the live terms stay); an entry whose draft had other terms in its top-level terms fields keeps them as unpublished changes. Then:
  - **Attach** each taxonomy your entries are tagged with to their collection (Structure → Taxonomies → **Collections**, or the collection's **Taxonomies**) if it isn't yet: a terms field in a collection's blueprint for a taxonomy that isn't attached no longer counts, and its entries list no longer filters by it. Tick **One term only** where an entry should have one.
  - **Delete the terms fields** that only gave entries their terms (the Terms field type stays for custom links, like a "Featured topic"): their values stay stored, unused. Templates that read the entry's terms from such a field (`$values['topics']`) read `$entry->terms` or `$entry->termsOf('topics')` instead.
  - Code: `AttachCollections::handle()` no longer takes `$removeFields` and returns `attached` and `detached`; `AttachCollections::blueprintsWithField()` is gone. Give `SaveDraft`, `CreateEntry` or `SaveEntryTerms` an entry's terms as `terms` (taxonomy handle => term ids or slugs) instead of writing them into a terms field's data.
  - Structure files and starters: write an entry's terms as its `terms` (`{"topics": ["news"]}`); files without it still import, the values of their entries' top-level terms fields standing in.

- **Site visitors get a session only when a page needs one** (`sunrice-cms.site.lazy_sessions`, on by default): a visitor without a session cookie reading a page without a form gets no session and no `laravel_session` or `XSRF-TOKEN` cookie. If your templates or front-end scripts rely on every visitor having them (`session()->getId()`, JavaScript reading `XSRF-TOKEN` on a page with no form), set `SUNRICE_LAZY_SESSIONS=false`, or print `csrf_token()` on those pages. A middleware list of your own in `sunrice-cms.site.middleware` keeps working: its `StartSession` is replaced on site routes. See [Sessions](../packages/cms/docs/site.md#sessions).

## 0.1.0

From a development snapshot (`dev-main`) to 0.1.0. Require the release instead of `dev-main` (`composer require sunrice/admin:^0.1 sunrice/cms:^0.1`, and `sunrice/starter-kit:^0.1` if you use it), then:

- **The migrations are consolidated**: each table has one create migration with its final schema, and the migrations that added to it, changed it or filled in data are gone (the list is in `CHANGELOG.md`). A database made from the earlier migrations must be rebuilt: `php artisan migrate:fresh` (it deletes all data: export what you want to keep first, or start again from a starter site). Until 1.0 this can happen again; from 1.0 on, released migrations are never changed.
- **Settings → Cache → Any other parameter** now defaults to **Skip the cache**. A site that saved its Cache settings before keeps **Ignore it** (stored with the rest; it can't be told apart from a deliberate choice, so it isn't changed for you). Settings → Cache warns while it's set to **Ignore it** and whole pages are kept: unless you chose it on purpose (no template reads a parameter that isn't listed), set it to **Skip the cache**.
- `php artisan cache:clear` now also clears the CMS's caches and the whole-page cache's own store (see above): a deploy script that clears the cache before it migrates still works, and the CMS logs a warning instead of failing.
