# Upgrading

`sunrice/admin`, `sunrice/cms` and `sunrice/starter-kit` are released together, with the same version number: update them at once. The CMS and the starter kit require the panel (and the starter kit the CMS) at exactly their own version, so Composer can't update one without the others.

## How to upgrade

1. Read this page from the version you have to the one you're going to, and `CHANGELOG.md` for everything else that changed.
2. Update the packages. Within the version your `composer.json` allows (`^0.2` takes 0.2.1, 0.2.2…), update them:

   ```bash
   composer update "sunrice/*" -W
   ```

   **Before 1.0, each minor version is a new major one for Composer**: `^0.1` allows 0.1.x but never 0.2.0, so `composer update` finds nothing new. To move to the next minor version, raise the constraints with `composer require`, every Sunrice package you have at once (and `-W` so their dependencies may move too):

   ```bash
   composer require sunrice/admin:^0.2 sunrice/cms:^0.2 -W
   # with the starter kit installed, add it to the same command:
   composer require sunrice/admin:^0.2 sunrice/cms:^0.2 sunrice/starter-kit:^0.2 -W
   ```

   Plugins of your own or from others (`composer.json`'s `sunrice/admin` or `sunrice/cms` constraint) need a release allowing the new version too; Composer names any that hold the update back.

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

## 0.2.0

From 0.1.x to 0.2.0. **0.2.0 needs a fresh database.** Its migrations are consolidated: each table's create migration makes its 0.2 schema, and the migrations that changed tables or moved data after 0.1.0 are gone. A database made on 0.1 has already run the create migrations, so `php artisan migrate` adds nothing to it and the panel and the site fail on the missing columns. Rebuild it with `php artisan migrate:fresh`, which drops **every table in the database**: your app's own tables (`users` included) as well as Sunrice's. This is an exception for 0.2.0. From 1.0 on, released migrations are never changed.

### Keeping a 0.1 site's content

Sunrice's exports carry the structure and the content, not everything in the database. The safest way to keep a 0.1 site:

1. **Back up the database and the media files** (`storage/app/public`, or your media disk) before anything else. The backup holds everything an export doesn't carry (see below), and it's your way back to 0.1.
2. **On 0.1, publish or discard the changes not published yet.** 0.1's exports write each entry's draft as its content, and importing publishes it, so pending changes would go live. Entries' terms work the same way: an entry gets the terms of its draft's top-level **Terms** fields, for the taxonomies attached to its collection only. Attach the taxonomies those fields use to the collection on 0.1 before exporting (Structure → Taxonomies, **Collections**), or those terms are dropped.
3. **On 0.1, make a starter of the site**. It holds the structure and content, the media files the content uses, and your templates:

   ```bash
   php artisan sunrice:make-starter my-site --public=cms   # writes starters/my-site; --public for each public/ folder your templates use (the starter templates': cms)
   ```

   `php artisan sunrice:export-structure site.json --entries` writes the structure and content without the media files. Imported into the fresh, empty media library, every media field and picker is left empty ("Left empty (not on this site)" in the import's report), so use it only for a site without media. 0.1's exporter has no `--settings` option (0.2's does).
4. **Upgrade the packages** (`composer require sunrice/admin:^0.2 sunrice/cms:^0.2 -W`, adding `sunrice/starter-kit:^0.2` if you use it; the starter kit is needed to install the starter), then rebuild the database and create the roles and an admin:

   ```bash
   mv storage/app/public/media storage/media-0.1   # the 0.1 files (default media disk and folder): the new library doesn't know them, and the starter uploads its own copies
   php artisan migrate:fresh
   php artisan sunrice:roles
   php artisan sunrice:make-admin
   ```

   (`sunrice:install --roles` creates the roles and asks for the admin too.)
5. **Set the languages up as on 0.1** (Settings → Languages, or `SUNRICE_LOCALES`), with the same main language, before bringing the content back. The import stops at texts in a language the site doesn't have.
6. **Bring the content back**: install the starter (`--dry-run` first to see what it does):

   ```bash
   php artisan sunrice:starter --path=starters/my-site
   ```

   For a structure file, run `php artisan sunrice:import-structure site.json` instead. Then run the rest of the steps every update needs, and read the notes below. Delete `storage/media-0.1` and `starters/my-site` once the new site is checked.

**Not carried over**, so it must be set up again or kept from the backup:

- every Settings section (Site, SEO, Languages, Cache, Site code, Comments, Security, Mail, Branding…);
- users, roles, and the permissions given to them (custom roles and their permissions need recreating in Administration → Roles);
- your app's own tables, which `migrate:fresh` drops too;
- revision history (each published entry starts again with one revision);
- form submissions and comments;
- the activity log;
- entries, terms and collections in the trash;
- an entry's author, its review request, and **Accept new comments** turned off;
- media files the content doesn't use, and the media library's folders;
- AI tokens, two-factor setups and people's preferences.

Copy what you need from the backup by hand, or with SQL you write for your site. The 0.1 and 0.2 schemas of these tables differ only in the columns 0.2 added (see `CHANGELOG.md`).

### Changes in 0.2.0

After that, the steps every update needs, and:

- **Published config files get the new keys**: a `config/sunrice-admin.php` or `config/sunrice-cms.php` published on 0.1 is now merged with the package's at every depth, so the keys added under its sections since (`auth.password_uncompromised`, `media.trash_disk`, `security.csp`, `site.csp`, `site.custom_code`, `site.lazy_sessions` …) take their defaults and their env variables (`SUNRICE_CSP_ENABLED`, `SUNRICE_PASSWORD_UNCOMPROMISED`, `SUNRICE_SITE_CSP_ENABLED`, `SUNRICE_SITE_CODE`, `SUNRICE_LAZY_SESSIONS` …). On 0.1 only the top-level keys were, so these were missing from a published file and their env variables did nothing. You needn't publish the files again: keys your file has keep their values, and lists and maps you set (`site.template_prefixes`, `media.image_sizes`, `site.headers` …) stay exactly as they are. Comparing your file with the new one still shows the new keys and what they do. See [Configuration](../packages/admin/docs/configuration.md).
- **The panel sends a strict Content-Security-Policy** (`sunrice-admin.security.csp`, on by default): scripts and `<style>` elements need the panel's origin or the request's nonce, so inline code you added to the panel stops running. That's a published or overridden `sunrice-admin::app` view (`resources/views/vendor/sunrice-admin/app.blade.php`), Blade views of your own panel routes, and plugins of your own that print inline `<script>` or `<style>`: give each tag `nonce="{{ Vite::cspNonce() }}"`, and move inline event handlers (`onclick=""`) and `javascript:` links into a script. A published `app.blade.php` should be compared with the new one: its first inline script gives the `<style>` elements the panel's scripts create the nonce, without which toasts, dialogs and the editors lose their styles. Anything the panel loads from elsewhere (images from a host other than the media disk's, an API your plugin calls, a script from a CDN) needs its origin in `security.csp.sources`. To find what breaks without blocking it, deploy with `SUNRICE_CSP_REPORT_ONLY=true` and watch the browser console; `SUNRICE_CSP_ENABLED=false` turns it off. A policy you set with `SUNRICE_CSP` is still sent as is, instead of the panel's. A `config/sunrice-admin.php` published on 0.1 says above `security.headers.csp` that a Content-Security-Policy is sent "only when set here": that comment is now wrong. A policy is sent by default, and `security.headers.csp` (`SUNRICE_CSP`), when set, replaces it as a whole; correct or remove the comment (the new file's reads "A whole Content-Security-Policy of your own, sent as is instead of the panel's"). See [Content-Security-Policy](../packages/admin/docs/security.md#content-security-policy).
- **The site can send a Content-Security-Policy** (`sunrice-cms.site.csp`, off by default; `SUNRICE_SITE_CSP_ENABLED=true`). A `config/sunrice-cms.php` published on 0.1 has no `csp` key: the env variables work without it, and you can add it to tune `nonce` or `sources`. See [Content-Security-Policy](../packages/cms/docs/site.md#content-security-policy).
- **The media trash is off the public disk**: moving a file to the trash moves its files to `media.trash_disk` (the private `local` disk by default), so they can't be downloaded at their links. A published `config/sunrice-admin.php` needn't have `trash_disk` and `trash_directory` under `media` (the defaults apply without them): add them to change them or to see them there, and keep the trash disk one that isn't served by URL. Files trashed on 0.1 are under the old media folder you moved aside: they aren't carried over. See [Media](../packages/admin/docs/media.md#the-trash).
- **Copy the image preset styles into your site's CSS**: rich text images can now be given a width and an alignment, stored as classes (`sunrice-img`, `sunrice-img-w-50`, `sunrice-img-left`…) that only your site's CSS turns into a layout. A site whose `public/cms/site.css` came from 0.1 (the starter templates, `vendor:publish --tag=sunrice-cms-templates`, or the Blog starter) doesn't have the rules, so the presets do nothing on it: images show full width, wherever they're placed. Add these, from the 0.2 starter templates' `site.css` (`packages/cms/stubs/public/site.css` in `sunrice/cms`), to your own CSS (`.prose` is the starter templates' wrapper of rich text: use your own):

  ```css
  /* Rich text images: the editor's width and alignment presets (no inline styles) */
  .sunrice-img { display: block; max-width: 100%; height: auto; margin-block: 1.5rem; }
  .sunrice-img-w-25 { width: 25%; }
  .sunrice-img-w-50 { width: 50%; }
  .sunrice-img-w-75 { width: 75%; }
  .sunrice-img-w-100, .sunrice-img-full { width: 100%; }
  .sunrice-img-center { margin-inline: auto; }
  .sunrice-img-left { float: left; margin: 0.35rem 1.5rem 1.5rem 0; }
  .sunrice-img-right { float: right; margin: 0.35rem 0 1.5rem 1.5rem; }
  .sunrice-img-left:not([class*="sunrice-img-w-"]), .sunrice-img-right:not([class*="sunrice-img-w-"]) { max-width: 50%; }
  .prose::after { content: ""; display: block; clear: both; }
  .prose :is(h2, h3, h4, hr, pre, blockquote, figure) { clear: both; }
  @media (max-width: 40em) {
      /* With the :not() selectors too, so the 50% limit of a float without a width doesn't outweigh this */
      .sunrice-img-left, .sunrice-img-right, .sunrice-img-left:not([class*="sunrice-img-w-"]), .sunrice-img-right:not([class*="sunrice-img-w-"]) { float: none; width: 100%; max-width: 100%; margin: 1.5rem 0; }
  }
  ```

  A site with its own design styles the same classes its own way. The CMS's plain pages (no templates of your own) have them already.
- **Rich text keeps only the image presets' classes on `<img>`**: the CMS's rich text fields are cleaned when saved, and now drop any other class on an image (0.1 kept every class there); the panel's `RichText` fields, which kept none, now keep the presets'. Inline styles stay dropped, as in 0.1 (`style` was never kept): width and alignment are the presets' classes. Content saved before the upgrade shows as it was until it's next saved, by an editor or by code (`SaveDraft`, an import); then its other image classes are gone. If your templates or CSS style images in rich text by classes of your own (`<img class="rounded">`), style them another way (`.prose img`), or use the editor's presets.
- **Show code fields as code**: 0.2 adds a Code field type. Templates get `$code` (the page's code fields, handle => language), and the 0.2 starter templates' `cms/partials/fields.blade.php` show those fields with `<x-sunrice-cms::code-block>`; a `fields` partial copied from 0.1 prints them as an escaped paragraph. Copy the `@elseif (isset($code[$handle]))` branch from the new one if your blueprints use code fields.
- **Settings → SEO moved to `/admin/settings/seo`**: links and bookmarks to `/admin/cms/seo` redirect (301), but code naming its routes must use `sunrice.admin.settings.seo.edit` and `.update` instead of `sunrice.admin.cms.seo.edit` and `.update`, and a page that marks it in the Settings menu uses the key `seo` (was `cms-seo`). Its **Structured data** section moved there from Settings → Site; the values are still kept in `SiteSettings`, so templates and code reading them change nothing. Code that saved them through Settings → Site's form (`PUT /admin/settings/site`) saves them with `SaveSeoSettings` (`structured`) or `SaveSettings` on `SiteSettings` instead; AI agents find them in the settings section `seo`.
- **Strong passwords are on, for existing installs too**: new passwords set in the panel need 12 characters or more with upper and lower case letters, a number and a symbol, and are checked against known data leaks (a request to `api.pwnedpasswords.com`; allow it out of your firewall, or set `SUNRICE_PASSWORD_UNCOMPROMISED=false`). People whose password doesn't meet the rules are asked to choose a stronger one the next time they sign in. Scripts or tests that create users through the panel's forms or `sunrice:make-admin --password` with simple passwords need stronger ones. To keep the old behaviour (your app's `Password::defaults()`), turn off **Require strong passwords** in Settings → Security, or set `sunrice-admin.auth.strong_passwords` to `false`. See [Strong passwords](../packages/admin/docs/auth.md#strong-passwords).
- **Entries' terms come from their collection's taxonomies**: the editor's **Taxonomies** card chooses an entry's terms for the taxonomies attached to its collection, and **Terms** fields no longer give entries their terms. An entry's terms are those of the attached taxonomies only, everywhere (`$entry->terms`, `whereTerm()`, term pages, `<x-sunrice-cms::terms>` counts, the sitemap): detaching a taxonomy hides its terms from the collection's entries until it's attached again. A 0.1 starter or structure file gives each entry the terms of its top-level terms fields, for the taxonomies attached to its collection (see [Keeping a 0.1 site's content](#keeping-a-01-sites-content)). Then:
  - **Review** Structure → Taxonomies: each taxonomy's **Collections**, and term pages in each collection (on by default).
  - **Delete the terms fields** that only gave entries their terms (the Terms field type stays for custom links, like a "Featured topic"): their values stay stored, unused. Templates that read the entry's terms from such a field (`$values['topics']`) read `$entry->terms` or `$entry->termsOf('topics')` instead.
  - Code: `AttachCollections::handle()` no longer takes `$removeFields` and returns `attached` and `detached`; `AttachCollections::blueprintsWithField()` is gone. Give `SaveDraft`, `CreateEntry` or `SaveEntryTerms` an entry's terms as `terms` (taxonomy handle => term ids or slugs) instead of writing them into a terms field's data.
  - Structure files and starters: write an entry's terms as its `terms` (`{"topics": ["news"]}`); files without it still import, the values of their entries' top-level terms fields standing in, and the terms of taxonomies not attached to the collection on the site you import into are dropped.
  - Code: `DefinitionValidator::usedFieldsets()` is removed; use `fieldsetUses($fields)`, which also says how each fieldset is used.
- **Editors no longer change menus' titles or handles**: a menu's **Settings** take the new `cms.menus.configure` besides `cms.menus.update`, which now edits its links only. The `administrator` role gets it from `sunrice:roles`; the `editor` role doesn't. If someone with `cms.menus.update` alone should still rename menus, tick **Change settings** under Menus in their role (the roles you recreate after the upgrade included). Code or scripts that save a menu's Settings as such a user (`PUT /admin/cms/menus/{handle}`, `save_record` on `menus`) get a 403 until then; saving links is unchanged.
- **Editors no longer change global sets' titles, handles or blueprints**: a set's **Settings** take the new `cms.globals.configure` besides `cms.globals.update`, which now edits its values only. The `administrator` role gets it from `sunrice:roles`; the `editor` role doesn't. If someone with `cms.globals.update` alone should still rename sets or change their blueprints, tick **Change settings** under Globals in their role (the roles you recreate after the upgrade included). Code or scripts that save a set's Settings as such a user (`PUT /admin/cms/globals/{handle}`, `save_record` on `globals`) get a 403 until then; saving values is unchanged.
- **Templates may also be in `resources/views/pages/`**: `sunrice-cms.site.template_prefixes` now defaults to `['cms.', 'pages.']` (was `['cms.']`). A published `config/sunrice-cms.php` keeps what it says. List the folders editors may use; `null` allows any of your views but those under `layouts`, `partials`, `components`, `errors`, `vendor`, `emails`, `mail` and `auth`, `_` files and packages' views (`SaveEntrySettings::prefixes()` returns `null` then).
- **A published `config/sunrice-cms.php` forces untranslated pages on**: 0.1.0's file has `'fallback' => env('SUNRICE_FALLBACK', true)`, which now forces Settings → Site's **Show untranslated pages in the main language** on (the toggle shows disabled). Change it to `env('SUNRICE_FALLBACK')` to let the setting decide. See [Untranslated pages](../packages/cms/docs/site.md#untranslated-pages).

- **`sunrice:translate` also translates listing and index pages and site texts**: without `--only`, it now runs `pages` and `texts` too (`TranslateCommand::DEFAULT_PARTS`), besides entries, terms, globals and menus. Unlike entries, which are translated into their drafts, these have no drafts: a collection's listing page, a taxonomy's index pages and **Content → Translations**' texts are saved live, and the site shows the translations at once. A scheduled `sunrice:translate` run without `--only` does this from the update on: give it `--only=entries,terms,globals,menus` to keep 0.1's parts. See [Machine translation](../packages/cms/docs/translation.md#what-is-translated).
- **Structure exports keep drafts as drafts**: an export (Structure → Export, `sunrice:export-structure`, `sunrice:make-starter`) now writes each entry as the site shows it, its live title, slug and fields, with changes not published yet as its `draft`; importing publishes the live version and saves the `draft` as the draft, so it stays unpublished. 0.1's exports wrote the draft as the content, so importing published changes nobody had published; such files still import as before. See [Exporting and importing structure](../packages/cms/docs/structure.md#exporting-and-importing-structure).
- **`MakeStarter::LANG` is removed** (it was `@api`): `sunrice:make-starter` now copies all of the app's `lang/` but other packages' published copies, and `MakeStarter::LANG_VENDOR` lists the packages under `lang/vendor/` it keeps (`['sunrice-cms']`). Code reading `MakeStarter::LANG` reads `LANG_VENDOR` instead. See [Starter sites](../packages/starter-kit/docs/starters.md).

- **`$term->title()` is in the page's language**: without a language, `Term::title()` and `Term::translation()` gave the main language's title and translation; they now give the page's (`Locales::current()`, as `$term->url()` always did), falling back to the main language's when the term isn't translated. Templates showing terms on translated pages now show translated names with no change. Code that wants the main language's whatever the page (a label in the panel, an export, a log line) passes it: `$term->title(Locales::default())`; code run outside a page reads the app's locale, so name the language there too.

- **Site visitors get a session only when a page needs one** (`sunrice-cms.site.lazy_sessions`, on by default): a visitor without a session cookie reading a page without a form gets no session and no `laravel_session` or `XSRF-TOKEN` cookie. If your templates or front-end scripts rely on every visitor having them (`session()->getId()`, JavaScript reading `XSRF-TOKEN` on a page with no form), set `SUNRICE_LAZY_SESSIONS=false`, or print `csrf_token()` on those pages. A middleware list of your own in `sunrice-cms.site.middleware` keeps working: its `StartSession` is replaced on site routes. See [Sessions](../packages/cms/docs/site.md#sessions).

## 0.1.0

From a development snapshot (`dev-main`) to 0.1.0. Require the release instead of `dev-main` (`composer require sunrice/admin:^0.1 sunrice/cms:^0.1`, and `sunrice/starter-kit:^0.1` if you use it), then:

- **The migrations are consolidated**: each table has one create migration with its final schema, and the migrations that added to it, changed it or filled in data are gone (the list is in `CHANGELOG.md`). A database made from the earlier migrations must be rebuilt: `php artisan migrate:fresh` (it deletes all data: export what you want to keep first, or start again from a starter site). Until 1.0 this can happen again; from 1.0 on, released migrations are never changed.
- **Settings → Cache → Any other parameter** now defaults to **Skip the cache**. A site that saved its Cache settings before keeps **Ignore it** (stored with the rest; it can't be told apart from a deliberate choice, so it isn't changed for you). Settings → Cache warns while it's set to **Ignore it** and whole pages are kept: unless you chose it on purpose (no template reads a parameter that isn't listed), set it to **Skip the cache**.
- `php artisan cache:clear` now also clears the CMS's caches and the whole-page cache's own store (see above): a deploy script that clears the cache before it migrates still works, and the CMS logs a warning instead of failing.
