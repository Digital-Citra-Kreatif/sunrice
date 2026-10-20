# Findings: developer hat

Tester: developer building the Lumina Estates site in `/home/user/realestate` against the CMS's documented
API (`packages/cms/docs`, `packages/admin/docs`). Templates in `resources/views/cms`, helpers in
`app/Support`, tests in `tests/Feature/Site`. Severity reflects the impact on a developer shipping a real
bilingual site.

## Top 10

1. **high** Listing, term and search pages' `$entries`/`$results` aren't hydrated together: ~5 queries per card (journal 61, topic 69, search 107 on real data). Performance.
2. **high** An entries field's chosen entries hydrate one by one: the real homepage took 121 queries until an app helper shared one `HydrationContext`. Performance.
3. **high** The structure export/import drops "SEO from fields" (collections, taxonomies) and forms' "Also email roles": a fresh install from `structure.json` has no meta descriptions or share images from fields. Structure.
4. **high** Auto-rendered forms print the blueprint's English labels and options on Indonesian pages; no translatable labels. i18n.
5. **medium** Kept pages (page cache) outlive a deploy and link the previous build's deleted CSS/JS; docs say no clearing is needed. Caching.
6. **medium** `hide_empty` on a hierarchical terms filter hides parents (Bali), and their value is then silently ignored: the filter shows everything. API.
7. **medium** Select fields give templates the stored value only; option labels aren't translatable and can't be read. API/i18n.
8. **medium** Collection/taxonomy titles are single-language: listing `<title>` and the CMS's BreadcrumbList ("Home", "Properties") stay English on /id. i18n.
9. **medium** `Settings::save()` doesn't fire `SettingsSaved` (docs say it does): a homepage set in code isn't picked up. API/docs.
10. **medium** The docs' breadcrumb example (`mainTranslation()`) links Indonesian visitors to English pages; the right `Site::urlIn()` is undocumented. Docs.

What I couldn't do the supported way: set "SEO from fields" on a fresh install (no action; `Admin\SeoMapping` is @internal: the seeder sets model attributes); translate auto-rendered form labels (left the valuation form auto-rendered to show it); translate the CMS's BreadcrumbList words; avoid the CMS's unused listing/term queries.

## Performance

### Listing, term and search pages hand templates entries that aren't hydrated together (N+1 per card)
- Severity: high
- Area: performance
- Where: `$entries` in `cms.{collection}-listing` / `cms.taxonomy-*` views, `$results` in `cms.search`
- Steps: 1. In `cms/journal-listing.blade.php`, loop `$entries` and call `$post->values()` for a card (cover, topics, author). 2. Count queries (tests/Feature/Site/QueryBudgetTest.php).
- Expected: As documented for `Cms::entries()` ("A result's entries share one HydrationContext … `values()` in a loop costs no queries of its own"), the page's own `$entries` should be hydrated as one result.
- Actual: Each card runs ~5 queries (its translations, then linked entries/terms one card at a time). Journal listing with 6 posts: 61 queries; topic page: 69; search with 3 results: 45. Reading the same page through `Cms::entries('journal')->latest()->paginate()` instead: 32 and 34.
- Note: Workaround in every listing/term template: re-query with `Cms::entries()` (the CMS's own paginated query still runs, unused: 4 wasted queries per page). The docs' query budgets (5/6/7) only hold for templates that never call `values()` on cards, i.e. for no real site.

### The entry page's own `$values` loads linked records one field at a time, and linked entries' `values()` re-load everything
- Severity: medium
- Area: performance
- Where: `/properties/villa-samudra` (cms/properties.blade.php)
- Steps: Dump the queries of a property page (agent, neighbourhood, 4 terms fields).
- Expected: "one query per kind" (fields.md, Using fields in code) for the page's own values; linked entries (`$values['agent']`) hydrated with the same context.
- Actual: 2 queries per terms field (terms, translations) and 3 per entries field, instead of one batch; then `$agent->values()` costs 4 more queries (entry, collection, blueprint, translations), `Term::url()` 2 (term again, taxonomy), `$term->parent->title()` 2. Property page: 56 queries warm (45 in Indonesian).
- Note: Acceptable with the page cache on, but nothing in the API lets a template preload "the agent's photo" or term parents (`HydrationContext` isn't documented for templates).

### Query counts per page (warm site cache, test fixture: 3 properties, 2 agents, 7 posts)
- Severity: low (for the record)
- Area: performance
- Where: `tests/Feature/Site/QueryBudgetTest.php`
- Actual: home 43 · page 18 · sub-page 19 · contact 16 · sell 16 · property 56 · property (id) 45 · properties listing (entry-filter) 39 · filtered listing 45 · team 20 · agent 26 · neighbourhood 23 · journal 32 · post 56 · location term 35 · topic term 34 · search 45. Every page pays ~8 for globals + the newsletter form (global values, then each global's blueprint by handle, all fieldsets, the form and its blueprint), which isn't kept in the site cache.
- Note: Counts are constant in the number of cards. On the live MySQL site with the editor's content (16 properties, 6 posts), warm cache, page cache off, after the app-level hydration fixes: home 38 · about 17 · properties 37 · filtered 43 · property 52 · team 18 · agent 25 · neighbourhood 22 · journal 36 · post 46 · location 36 · topic 39 · search 28 · contact 15 (all 20–80 ms). With the page cache on, guests' repeat visits cost 0 queries.

## i18n

### Auto-rendered forms show the blueprint's English labels and options on Indonesian pages
- Severity: high
- Area: i18n
- Where: `/id/sell-with-us` (`<x-sunrice-cms::form handle="valuation" />`)
- Steps: 1. Open the Indonesian sell page.
- Expected: Labels, help texts and select options in Indonesian (the site is bilingual; the CMS's "Send" already is).
- Actual: "Name", "Email", "Phone", "Address", "Property type" with "House/Villa/…" options. Blueprint labels, instructions and select option labels have one language only, so the documented zero-markup form can't be used on a multilingual site.
- Note: The contact, inquiry and newsletter forms use their own markup with labels from `lang/*/site.php` and option labels looked up by value (`cms/partials/form-field.blade.php`). The valuation form is left auto-rendered on purpose, to show the gap.

### Select fields give templates only the stored value; option labels aren't translatable and there's no helper to read them
- Severity: medium
- Area: API / i18n
- Where: property `property_status`, `price_suffix`; agent `languages`
- Expected: A way to print a select's label in the page's language (e.g. `$field->optionLabel($value, $locale)`), as the admin shows labels.
- Actual: Templates get `under_offer`, `per_month`, `english`. I re-declared every option's words in `lang/*/site.php` (`status.*`, `price.*`, `languages.*`), duplicating the blueprint.

### Collection and taxonomy titles have one language: listing pages' `$title`, `<title>` and the breadcrumb structured data stay English on /id
- Severity: medium
- Area: i18n
- Where: `/id/properties`, `/id/journal`, `/id/team`
- Actual: `<title>Properties | Lumina Estates</title>` and BreadcrumbList `"name":"Home"`, `"name":"Properties"` on Indonesian pages. The word "Home" in the CMS's own BreadcrumbList isn't a translation key either.
- Note: Editors can fix `<title>` per language with the listing page's SEO title; the template heading reads `site.collections.{handle}` from lang files (`App\Support\Breadcrumbs::listingTitle()`); the JSON-LD breadcrumb can't be fixed from the template.

### Some of the CMS's visitor-facing words are JSON keys with no Indonesian line
- Severity: low
- Area: i18n
- Where: `sunrice-cms::components.entry-filter` (Filter, All, Sort by, Show results, Clear all, Applied filters, Min/Max, ":count result|:count results"), `sunrice-cms::draft-banner` (Draft, View the draft, Edit …)
- Expected: Like the forms and comments, under `sunrice-cms::site.*` with Indonesian lines ("the CMS's own words already translate").
- Actual: Bare `__('Filter')` etc.; `resources/lang-core/id.json` only has three pagination lines. The default entry-filter markup is English on /id.
- Note: Not visible on this site (the filter uses its own markup); an app can add the lines to `lang/id.json`.

### `og:locale` is "id"/"en" instead of "id_ID"/"en_US"
- Severity: polish
- Area: site
- Where: `<x-sunrice-cms::seo>` on every page
- Actual: `<meta property="og:locale" content="id">`; Open Graph expects `language_TERRITORY`.

## API and docs

### Breadcrumbs example in the docs gives main-language titles and links on translated pages
- Severity: medium
- Area: docs / API
- Where: site.md → Filtering entries → "In hierarchical collections … `$ancestor->mainTranslation()?->url()`"
- Expected: Breadcrumbs in the page's language, or the main page at the language's address for untranslated parents (as menus do).
- Actual: The example links Indonesian visitors to `/about` (English). The right call, `Sunrice\Cms\Site\Site::urlIn($translation, $locale)`, is public and @api but undocumented.
- Note: `App\Support\Breadcrumbs::forEntry()` uses `translation($locale)` when live, else `mainTranslation()` + `Site::urlIn()`.

### `Settings::save()` doesn't announce the change: a homepage set in code isn't picked up
- Severity: medium
- Area: API / docs
- Where: admin docs settings.md ("`$shop->save()` … Saving a section fires `SettingsSaved`")
- Steps: 1. `$s = app(SiteSettings::class); $s->homepage = $id; $s->save();` in a seeder or test. 2. Request `/`.
- Expected: The homepage at `/`.
- Actual: 404 in the same process: `Site` keeps the homepage it read, and only `SaveSettings` dispatches `SettingsSaved`, which makes it forget. The docs say saving fires the event.
- Note: Used `app(SaveSettings::class)->handle($settings, [...])` in `tests/Support/LuminaSite.php`.

### `hide_empty` on a hierarchical terms filter hides parents whose properties are all in children
- Severity: medium
- Area: API
- Where: `<x-sunrice-cms::entry-filter :filters="['location' => ['type' => 'terms', 'taxonomy' => 'locations', 'hide_empty' => true]]">`
- Steps: 1. Properties only in Canggu/Seminyak/Kemang. 2. Open the filter; 3. visit `/properties?location=bali`.
- Expected: Bali offered (choosing it finds everything under it, which the filter does: `includeChildren: true`), counts that include children.
- Actual: Bali, Badung, Jakarta are left out of the options (their `count` is 0), so `?location=bali` is "not among the options" and silently ignored: the page shows every property. Option `depth` is still 2 for Canggu, so the select indents children of missing parents.
- Note: Locations filter without `hide_empty` and without counts.

### Handles can't contain dashes; the BRIEF's handles (and view names) had to change
- Severity: low
- Area: structure / docs
- Where: fieldsets `rich_text`, `image_text`, `featured_properties`, taxonomies `property_types`, `listing_types`; reserved handles `status`, `author`
- Actual: The content model came out as `property_types` (view `cms/taxonomy-property_types.blade.php`, next to dashed URLs `/property-type/{slug}`), `property_status` and `author_agent` (top-level `status`/`author` are reserved). The reserved list is only named by example in fields.md ("`title`, `slug`, `status`…").
- Note: `cms/partials/blocks.blade.php` maps `rich_text` → `cms/blocks/rich-text.blade.php`. The full `Blueprint::RESERVED` list belongs in the docs.

### Docs gaps on what templates receive
- Severity: low
- Area: docs
- Where: site.md → Templates → "Views get"
- Actual: `$richText` and `$draft` are passed to entry views (the starter templates use `$richText`) but missing from the table; fields.md doesn't say a single entries field gives one `EntryTranslation` (or null) and a single terms field one `Term`; site.md doesn't say listing/term `$entries` aren't hydrated together (above).

### Listing and term pages always run their own entries query
- Severity: low
- Area: performance / API
- Where: listing pages using `<x-sunrice-cms::entry-filter>`; term pages that list entries of children too
- Actual: The CMS paginates `$entries` (count + select + 2 eager loads) before the template runs, even when the template uses the filter component or its own query. A term page's `$entries` has only the term itself (no `includeChildren`), so a location page ("Bali") needs its own query anyway.
- Note: A collection/taxonomy setting or a view-declared "I query myself" would avoid the waste.

## Caching

### Kept pages outlive a front-end deploy and link deleted asset files
- Severity: medium
- Area: performance / docs
- Where: `SUNRICE_PAGE_CACHE=true`; caching.md ("a change shows at once (no clearing needed)")
- Steps: 1. Visit a page (kept). 2. `npm run build` (new hashed `app-*.css`, the old file deleted). 3. Visit again as a guest.
- Expected: The deploy docs (installation.md → Upgrading, caching.md) to say kept pages must be cleared after a template or asset deploy, or the content version to include the Vite manifest / view changes.
- Actual: `X-Cache-Status: HIT` with `<link href="/build/assets/app-B8_fWhSV.css">`, a 404: the page renders unstyled for up to `cache.ttl` (an hour). Template changes (new Blade) likewise don't show until the TTL ends. `php artisan view:clear` (listed in the upgrade steps) doesn't help.
- Screenshot: qa/screenshots/developer-01-stale-page-cache.jpg
- Note: Added `php artisan responsecache:clear` to the README's deploy steps.

## Structure tooling

### The structure export drops "SEO from fields" (collections, taxonomies) and forms' "Also email roles"
- Severity: high
- Area: structure
- Where: `php artisan sunrice:export-structure`, `StructureImporter::COLLECTION`, `::TAXONOMY`, `::FORM`
- Steps: 1. Admin sets Properties → SEO from fields (description ← summary, image ← gallery). 2. Export the structure, import it on a fresh install. 3. Open a property.
- Expected: The versioned content model reproduces the site's SEO behaviour.
- Actual: `seo_title_field`, `seo_description_field`, `seo_image_field` aren't in the export (nor read on import), so a fresh install has properties without meta descriptions or share images until someone redoes Structure → Collections by hand. Forms lose `notify_roles` the same way. The docs (structure.md) present the file as the structure.
- Note: App-level workaround through public models: `php artisan lumina:export-structure` (app/Console/Commands/ExportStructure.php) adds a `seo` key per collection/taxonomy and `StructureSeeder` applies it after the import. There's no action for SEO-from-fields (`Admin\SeoMapping` is @internal), so the seeder sets the model attributes.

### `/team` answered 500 while the agents collection had no listing blueprint (template bug, CMS behaved)
- Severity: low
- Area: site / docs
- Where: `/team`, `cms/agents-listing.blade.php`
- Steps: 1. A collection with a listing URL but no listing blueprint. 2. The listing template reads `$values['intro']`.
- Actual: `$values` is `[]` (no blueprint, no keys), so `$values['intro']` threw "Undefined array key" → 500. My template's fault (fixed with `?? null`); the CMS can't prevent it, but site.md could say that `$values` only holds the listing blueprint's handles (none without one), unlike entries whose fields always come with their defaults.

### An entries field's chosen entries aren't hydrated together either: 121 queries on the real homepage
- Severity: high
- Area: performance
- Where: `/` (featured_properties, team, testimonials blocks), `/journal/{slug}` (`related`), `/search` (`$results`); live MySQL with the editor's content
- Steps: Loop an entries field (`$block->properties`, `$values['related']`) and call `values()` on each linked entry for its photo, location, price.
- Expected: fields.md: "Linked entries … loaded once per request" and "one query per kind"; the linked entries' own fields hydrated as one result.
- Actual: Each linked entry hydrates its fields with a fresh context: homepage 121 queries (4 featured properties, 4 agents, 3 testimonials), post 91, search 107 (warm cache).
- Note: `App\Support\Hydrate::together()` gives a list one `HydrationContext`, eager-loads `entry.translations` and preloads the references (`Schema::references()` + `HydrationContext::preload()`, both documented). After: homepage 38, post 46, search 28. This belongs in the CMS (an `Items` of linked entries could share one context, and the search/listing paginators too).


## Environment notes (not CMS issues)

- Bunny Fonts is blocked here; the fonts are self-hosted from the `@fontsource` npm packages through laravel-vite-plugin's font support instead (same result, no third-party request at runtime).
- `php artisan serve` doesn't pass shell environment variables like `DB_CONNECTION` to its workers, so a second preview copy (SQLite, port 8300, scratch DB) runs on `php -S` from `public/`.
- The response cache's default store (`file`) is shared by every copy of the app on the machine and by the tests unless `RESPONSE_CACHE_DRIVER` is set: `phpunit.xml` now sets it to `array` and `SUNRICE_PAGE_CACHE=false`.

## What worked well

- Template resolution (`cms.{collection}`, `-listing`, `taxonomy-{handle}`, per-entry template) is predictable; the starter templates are a good, honest starting point (accessible menu, language switcher, `cms_page()` advice).
- `<x-sunrice-cms::entry-filter>` with a slot: the whole filter UI in my own markup, shareable URLs, no JavaScript, safe against made-up values; sorting by number fields just works.
- `<x-sunrice-cms::form>` with a slot (`old()`, `error()`, `failed()`, per-form error bags), honeypot and CSRF handled; file uploads to a private disk; JSON responses.
- The page cache with per-visitor CSRF tokens works (verified in a browser: a kept page's form submits).
- `:schema` on the seo component made the RealEstateListing/Offer and FAQPage nodes trivial; the CMS's own graph (WebSite with SearchAction, Organization, BreadcrumbList) is solid.
- The main-language fallback on `/id/...`, `$fallback`, canonical to the main page, and language-aware menus.
- Previews and `?draft` with the banner work in custom templates with no extra code.
- Structure import through the real actions made the test fixture easy and trustworthy; `Cms::entries()` results are hydrated efficiently.
