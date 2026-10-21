# Lumina Estates website

The website of Lumina Estates, a premium residential agency in Jakarta and Bali, built on Laravel 13 and
[Sunrice](../../README.md) (`sunrice/admin` + `sunrice/cms`). English is the main language, Indonesian (`/id/…`)
the second.

> **In this repository** this app is an example and a field test of Sunrice: it requires the packages from `../../packages/*` (a Composer path repository). The QA campaign that built it (admin, developer, editor, frontend and security testing) is in [`qa/REPORT.md`](qa/REPORT.md), with the per-role findings, scripts and screenshots in `qa/`. Photos aren't included: the environment that built it couldn't reach Unsplash; `qa/unsplash-wanted.md` lists them. `database/content.json` holds the content (`php artisan sunrice:import-structure database/content.json`).

## What lives where

| In the CMS (editors, `/admin`) | In code (developers, this repository) |
| --- | --- |
| Every word visitors read on pages: pages and their sections, properties, agents, neighbourhood guides, journal posts, testimonials | The templates (`resources/views/cms`), how each kind of content is laid out |
| Prices, statuses, photos (media library, with alt texts), floor plans | The design system (`resources/css/app.css`), the script (`resources/js/app.js`), the logo |
| Menus (`main`, `footer`, `legal`), contact details (global `company`), social links (`social`), footer texts (`footer`) | Interface words: labels, buttons, headings of fixed sections, in `lang/en/site.php` and `lang/id/site.php` |
| Forms (`contact`, `inquiry`, `valuation`, `newsletter`): fields, recipients, thank-you texts | Which form appears where (contact page, property pages, sell page, footer) |
| SEO titles, descriptions and share images; redirects; site code (analytics) | Structured data for properties, agents and FAQs (`app/Support/Schema.php`) |
| The content model, edited in Structure → … and exported to `database/structure.json` | `database/structure.json` itself (versioned), imported on fresh installs |

Nothing in a template is copy: when a template needs a word, it's a translation key; when it needs a fact
(a phone number, a price), it reads it from the CMS.

## The content model

`database/structure.json` is the content model: fieldsets, blueprints, collections (with their routes and
listing pages), taxonomies, global sets, forms, menus and redirects. Handles use underscores (the CMS doesn't
allow dashes).

| Collection | URL | Template | Listing |
| --- | --- | --- | --- |
| `pages` (hierarchical) | `/{slug}`, the homepage at `/` (Settings → Site) | `cms/pages.blade.php`; Contact uses `cms.contact`, Sell with us `cms.sell` (Page settings → Template) | — |
| `properties` | `/properties/{slug}` | `cms/properties.blade.php` | `/properties`, `cms/properties-listing.blade.php` (filters) |
| `agents` (ordered) | `/team/{slug}` | `cms/agents.blade.php` | `/team`, `cms/agents-listing.blade.php` |
| `neighbourhoods` | `/neighbourhoods/{slug}` | `cms/neighbourhoods.blade.php` | `/neighbourhoods` |
| `journal` (dated, comments) | `/journal/{slug}` | `cms/journal.blade.php` | `/journal`, 6 a page |
| `testimonials` (ordered) | none: shown by the Testimonials block | — | — |

Term pages: `/property-type/{slug}` (`cms/taxonomy-property_types`), `/location/{slug}`
(`cms/taxonomy-locations`, includes the places under it), `/journal/topic/{slug}` (`cms/taxonomy-topics`).
Search is `/search` (`cms/search`); the sitemap `/sitemap.xml`; errors `resources/views/errors/`.

How the CMS picks a template: the entry's own (Page settings), the collection's, `cms.{collection}`,
`cms.entry`. Listing pages: `cms.{collection}-listing`; term pages: `cms.taxonomy-{taxonomy}`
(see `../../packages/cms/docs/site.md`).

### Changing the structure

1. Change it in the admin (Structure → Blueprints, Collections …) on a development copy.
2. Rename fields with `php artisan sunrice:rename-field …` (never in the builder: the values would stay behind).
3. `php artisan lumina:export-structure` (the CMS's `sunrice:export-structure` plus the "SEO from fields"
   settings it leaves out), commit `database/structure.json` with the templates that use it.
4. On the server: `php artisan sunrice:import-structure database/structure.json --dry-run`, then without
   `--dry-run`. The import adds and updates, never deletes content; it refuses to drop fields unless `--force`.

## Templates

```
resources/views/cms/
  layout.blade.php            head (SEO, site code, fonts, assets), header, footer
  pages.blade.php             pages: the "sections" blocks, sub-pages; contact.blade.php and sell.blade.php extend it
  blocks/{type}.blade.php     one view per block type of the page blueprint's "sections"
  properties*.blade.php, agents*.blade.php, neighbourhoods*.blade.php, journal*.blade.php
  taxonomy-*.blade.php        term pages (on layouts/term.blade.php)
  search.blade.php
  partials/                   header, footer, cards, image, price, status badge, breadcrumbs, pagination, forms…
```

Conventions:

- Layouts and partials read the page shown from `cms_page()`, never from `$entry` (see the CMS's docs).
- Images go through `cms/partials/image.blade.php`: `srcset` from the media library's sizes, alt text from
  the library, a quiet placeholder when the field is empty.
- Rich text is printed raw (`{!! !!}`, it's cleaned when saved); everything else escaped.
- Prices: `cms/partials/price.blade.php` and `App\Support\Money` (no intl needed: separators and currency are
  translation lines).
- Breadcrumbs: `App\Support\Breadcrumbs` (in the page's language, untranslated parents included).
- `App\Providers\AppServiceProvider` registers one CMS hook: `Cms::bodyClassUsing()` adds `has-hero` to pages
  whose first section is a hero, so the header sits transparent over the photo.

### Adding a block type

1. In the admin: create a fieldset (Structure → Fieldsets), say `video` with a `url` link and a `caption`
   text, and add it to the `page` blueprint's `sections` field under *Blocks*.
2. Create `resources/views/cms/blocks/video.blade.php` (underscores in the handle become dashes in the file
   name). It gets `$block` (`$block->url`, `$block->caption`), `$first` (whether it opens the page: then it
   holds the `<h1>`) and `$blockId` (an anchor: the block's key, else `section-{n}`).
3. Interface words go in `lang/en/site.php` and `lang/id/site.php` (`blocks.*`).
4. Export the structure (`database/structure.json`) and add a case to `tests/Feature/Site/PagesTest.php`.

Until the view exists the block renders nothing on the live site, and a note in previews.

## Front end

```bash
npm install
npm run dev     # Vite dev server with hot reload
npm run build   # public/build (not committed: built on deploy)
```

Tailwind CSS v4 (tokens in `@theme`, components in `@layer components` of `resources/css/app.css`), fonts
self-hosted at build time by laravel-vite-plugin from the `@fontsource` packages' latin woff2 files, one file per face
(`@fonts` in the layout, which preloads the regular weights).
The script (`resources/js/app.js`) only enhances: the gallery viewer, the mobile menu, the header over a hero,
reveal on scroll (off with reduced motion), filter selects submitting themselves. Every page works without it.

The logo is generated (wordmark outlined from the site's fonts): `node qa/scripts/make-logo.mjs` (needs
`opentype.js`) writes `public/images/lumina-logo.svg`, the inline `cms/partials/logo.blade.php` and
`public/favicon.svg`; `node qa/scripts/make-icons.mjs` the PNG and ICO icons.

## Languages

English is the main language (no prefix), Indonesian under `/id`. Content is translated in the admin; an
untranslated page shows the English content at its `/id/…` address with a note (the CMS's fallback). The
site's own words are in `lang/{en,id}/site.php` (keep both in step: a test checks they have the same keys);
the CMS's words (forms' errors, comments, pagination) come translated with the CMS.

## Caching

- The CMS's site cache is on (`SUNRICE_CACHE`, default store).
- Whole pages: `SUNRICE_PAGE_CACHE=true` (spatie/laravel-responsecache). Forms and comments keep working
  (each visitor gets their own CSRF token). Query parameters that change a page must be listed in
  `config/sunrice-cms.php` → `cache.query` (the property filters and `listings` are).
- Content changes show at once (the caches are versioned by content); nothing to clear after editing. A
  deploy that rebuilds the assets must clear kept pages (`php artisan responsecache:clear`): they still link
  the previous build's hashed CSS and JS files, which `npm run build` deleted.

## Tests

```bash
composer test          # Pest: tests/Feature/Site/*
```

The site tests import `database/structure.json` into an in-memory SQLite database, create a little content
with the CMS's actions, render each kind of page and check the SEO tags, forms, filters, structured data and
a query budget per page type.

## Install and deploy

Fresh install:

```bash
composer install
cp .env.example .env && php artisan key:generate     # set APP_URL, DB_*, MAIL_*, SUNRICE_LOCALES=en,id
php artisan sunrice:install --roles                  # migrations, admin assets, storage link, roles, a super admin
php artisan db:seed                                  # the content model from database/structure.json
npm ci && npm run build
```

Then, in the admin: Settings → Languages (`en`, `id`), Settings → Site (homepage, site name), the pages'
templates for Contact (`cms.contact`) and Sell with us (`cms.sell`).

Every deploy:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force                          # also syncs the permissions new features need
php artisan sunrice:import-structure database/structure.json   # when the structure changed (--dry-run first)
php artisan sunrice:assets                           # the panel's assets (and laravel-assets)
npm ci && npm run build
php artisan config:cache && php artisan route:cache
php artisan view:clear
php artisan responsecache:clear                      # kept pages name the old build's asset files
php artisan queue:restart
```

On the server, keep running:

- a queue worker (`php artisan queue:work`, Supervisor or systemd): form and comment notifications, image sizes;
- the scheduler (`* * * * * php artisan schedule:run`): scheduled publishing, pruning old submissions and spam.

The server's `.env` sets `APP_ENV=production` and `APP_DEBUG=false` (with debug on, error pages show stack
traces and file paths; with it off, visitors get `resources/views/errors/`), and `APP_URL` to the real
address: emails' links are built from it, and `bootstrap/app.php` trusts only its host (`trustHosts()`). Set
`expose_php = Off` in `php.ini` so responses don't announce the PHP version (`X-Powered-By`).

Web server: serve `public/`; for `storage/media` add `X-Content-Type-Options: nosniff` (see the panel's
media docs). `/robots.txt` is a route (`routes/web.php`) that names the sitemap at the address the site is
served on, and keeps search engines out of a hidden site.
