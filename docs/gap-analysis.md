# Gap analysis: `sunrice-cms` → `sunrice`

A comparison of the monolithic `sunrice-cms` package (the "old" CMS) with
this repository's rewrite into `sunrice/admin` (the panel) and
`sunrice/cms` (the CMS plugin). It covers features, developer API, design,
ease of use, robustness, security, performance, documentation and the
admin's look and feel, and ends with a prioritised roadmap.

Everything here was checked against the source of both repositories on
19 October 2026 (`sunrice-cms` at `198db4d`, `sunrice` at `f899d04`).
File paths are relative to each repository; "OLD" is `sunrice-cms`,
"NEW" is this repository.

Status legend: **present** (same or better), **partial** (exists, but
something is missing: noted), **missing**, **NEW only**.

## 1. Summary

| Area | Parity | Biggest gaps |
| --- | --- | --- |
| Content modeling (fields, blueprints, collections) | ~75% | Internal entry links in the `link` field, per-entry blueprint override, rename-field migration, structure import/export, translation overlay for repeaters |
| Entries, terms, globals | ~80% | Drag-and-drop ordering, saved table columns and filters, "outdated translation" badge, references between entries and terms, version checks on globals and terms |
| Site side (routing, components, SEO, cache) | ~70% | Route parameters from related entries, fallback to the main language, draft viewing for editors, `entry-filter` component, SEO field mapping, starter templates |
| Admin panel (auth, users, settings, media) | ~75% | Branding (logo, colour, font, login page), per-user security card, user deletion with content reassignment, test email, in-admin docs and user guide, dashboard widgets |
| AI / MCP | ~55% | 16 tools gone (templates, commands, error log, docs, SEO audit, revisions, reorder, assets, listings, blueprints, menus), stdio transport, token command |
| Translation and multilingual | ~35% | Machine translation (Gemini, OpenRouter), `sunrice:translate`, switching the main language, locale names, Indonesian core translations |
| Documentation | ~55% | No end-user guide (EN + ID), no in-admin docs, no upgrading, installation, mail, commands, helpers or config reference pages, thin READMEs |
| Admin UI and interaction | ~60% | Dashboard is a placeholder, no dark-mode toggle, no web font loaded, no drag-and-drop, no page transitions, no collapsible sections, no command palette |

What the rewrite does better than the old package: a real panel/plugin
contract with ownership checks and architecture tests, per-section typed
and encrypted settings, read-only and expiring AI tokens, generic resource
tools for agents, stricter blueprint definition validation, per-language
publish state with atomic version checks on entries, media usage tracking
for any plugin, structured data (JSON-LD), listing and term pages with
their own fields, a page cache that strips cookies, half the eager
JavaScript, and a frontend test suite for the SDK contract.

## 2. Feature parity

### 2.1 Fields, blueprints and fieldsets

| OLD capability | NEW status | Where |
| --- | --- | --- |
| Field types text, textarea, number, select, date, toggle, rich text, asset, entries, terms, link, repeater, flexible, group, fieldset include, file (forms) | present (asset renamed `media`); NEW adds `placeholder`, `rows`, `step`, `default`, typed media, single-value entries and terms, `max` on flexible | `packages/cms/src/Fields/Types/*` |
| `link` field: internal entry links (`type: entry`, resolved URL and title) | **missing**: NEW stores `{url,label,new_tab}` only | `Fields/Types/Link.php` |
| Text field without a length cap | changed: NEW caps text at 255 by default; old data longer than that fails validation on the next publish | `Fields/Types/Text.php`, `Actions/Entries/PublishEntry.php` |
| Flexible field accepting every fieldset (empty = all) | changed: `blocks` must be listed explicitly | `Fields/Types/Flexible.php` |
| `TranslationOverlay`: translations store only translated text, row by row; rows added in the main language appear in every language | **missing**: translations are full copies; a translatable repeater is a separate list per language, and non-translatable children inside a translatable group are not shared | OLD `src/Fields/TranslationOverlay.php`; NEW `Models/EntryTranslation::values()` |
| Rename a field handle with data migration (`RenameFieldHandle`, `sunrice:rename-field`) | **missing** | OLD `src/Actions/Structure/RenameFieldHandle.php` |
| Structure import/export (`sunrice:import-structure`, `StructureImporter`, `Sunrice::importStructure()`) | **missing** | OLD `src/Structure/*` |
| Purge soft-deleted structure (`sunrice:orphans --purge`, `PurgeDeleted`) and re-use a deleted handle | **missing**: a deleted collection's handle stays taken forever | NEW `Actions/Collections/DeleteCollection.php` |
| Per-entry blueprint override (`ChangeBlueprint`) | **missing** | OLD `src/Actions/Entries/ChangeBlueprint.php` |
| `DeleteBlueprint` refuses when taxonomies, global sets, listing pages or forms use it | **partial**: only collections are checked | NEW `Actions/Blueprints/DeleteBlueprint.php` |
| Blueprint definition validation | NEW only: per-type settings schema, allow-listed validation rules, reserved handles, cycle detection, depth limit | `Fields/DefinitionValidator.php` |
| Custom field types in PHP (`CustomField`) | present; a `FieldType` with no base type has no editor input and the docs don't say what the editor shows | `docs/cms/fields.md` |
| Plugin-registered JavaScript field components (`registerField` in `plugin-api.ts`) | **missing** (out of scope by `AGENTS.md`, but the old docs promised it) | OLD `resources/js/fields/registry.ts` |
| Conditional visibility, field width, `unique` rules | missing on both sides | |

### 2.2 Collections

| OLD capability | NEW status |
| --- | --- |
| Route with parameters from related entries (`/projects/{project}/{slug}`) | **missing**: `Site/RoutePattern.php` allows fixed segments plus one `{slug}` |
| Nested URLs for hierarchical collections | missing by design (`AGENTS.md`: URLs stay flat) |
| Archive page with its own blueprint, per-locale title, intro and SEO | **partial**: listing pages exist with a blueprint, but no title, intro or SEO fields of their own; `Seo::forListing()` passes an empty SEO array |
| Default sort (`sort` + `sort_direction`: manual, published, created, updated, title) | **partial**: `dated`, `ordered` or title ascending only |
| Per-locale collection titles, icon, table columns, SEO field mapping, `translatable` flag, single-term taxonomies | **missing** |
| Schema.org type per collection | NEW only |
| Manual order by drag-and-drop | **partial**: Move up and Move down, renumbering the whole collection on each move (`Actions/Entries/MoveEntry.php`) |

### 2.3 Entries

| OLD capability | NEW status |
| --- | --- |
| Draft, publish now or scheduled, unpublish, trash, restore, delete for good, duplicate, revisions and restore | present; NEW adds undo of a restore (`PutBackDraft`), bulk unpublish, delete-for-good only from the trash |
| "Outdated translation" badge when the main language changed after a translation | **missing** |
| Version check against overwriting (entries, terms, globals) | **partial**: entries only; `SaveGlobals`, `SaveTerm`, `SaveTermContent` and `SaveListing` have no check, and `PublishEntry` does not bump the version |
| Edit locks on entries, terms and globals | **partial**: terms are not lockable (`CmsPlugin.php` registers entries and globals) |
| References between entries and terms (`sunrice_references`, "used by" on entries and terms) | **partial**: media usage only (`MediaUsages`); `Fields/Reference` has `ENTRY` and `TERM` kinds but nothing persists them |
| Terms synced from nested fields | **partial**: `SyncEntryTerms` reads top-level `terms` fields only |
| Entry list: filters (status, author, terms, fields), sortable columns, optional field columns, saved columns and page size per user (`TablePreferencesController`), "Go to" related links | **mostly missing**: title search and a trash toggle |
| CSV export with the list's filters | **partial**: exports the whole collection in the main language |
| Comments card inside the entry editor | **missing** (comments are moderated on their own page) |

### 2.4 Taxonomies, terms and globals

| OLD capability | NEW status |
| --- | --- |
| Nested terms, trash and restore, translations | present |
| Term routes per collection (`/blog/category/{slug}`), `per_page`, per-locale taxonomy titles | **partial**: one route per taxonomy, page size hard-coded to 10 |
| Term fields, term pages, term templates | NEW only |
| Drag reorder and bulk actions on terms | **partial**: Move up and Move down |
| Global set groups (`global` / `template_part`) | **missing** |
| Globals cached between requests | **partial**: loaded once per request, not cached |

### 2.5 The site side

| OLD capability | NEW status |
| --- | --- |
| Locale middleware on every site request (`SetFrontendLocale`), 301 from a main-locale prefix (`/en/about` → `/about`) | **partial**: the locale is set only when a CMS page renders; `/en/about` is a 404; 404s, redirects and app routes keep the app locale |
| Whole-entity fallback: an untranslated page shows the main language at the prefixed URL, with a canonical to the main language | **missing**: an untranslated page is a 404 in that locale; menus and `hreflang` omit it |
| Draft viewing for signed-in editors (`sunrice.view-drafts`, draft banner, `no-store`) | **missing**: only 30-minute signed previews; the preview response has no `Cache-Control: no-store` |
| Redirect when a slug changes, redirect chains flattened, manual redirects | present and better (manual 301/302, `SafeUrl`-checked); NEW drops the query string |
| Template hooks (`resolveTemplateUsing`, `bodyClassUsing`, `cachePageUsing`, `bypassPageCacheUsing`, `pageCacheLifetimeUsing`) | **missing** except `PageCache::skipWhen()` |
| `@bodyClass` Blade directive and `sunrice_*` helper functions | **missing** (use `Cms::bodyClass()`, `Cms::entries()`) |
| Blade components `entries`, `terms`, `search`, `seo`, `code`, `form`, `comments` | present with fewer attributes: `entries` lacks `page-name`, `with`, terms by slug with children and `where` operators; `terms` lacks `parent`, `order-by`, `limit`, `isCurrent()`; `seo` lacks `description`, `default-title`, `noindex`; `search` has a fixed `q` parameter |
| `entry-filter` component with `ActiveFilter`, `FilterControl` and `SortChoice` (URL-driven filtering on listing pages) | **missing** |
| `form` component with a slot for custom markup, `error()`, `old()`, `success()` | changed: the form renders every field itself; no slot; `old()` is global, so two forms sharing a handle refill each other |
| SEO field mapping (which content fields feed title, description and image per collection and taxonomy) | **missing**: `Support/Seo.php` reads only the `seo` card and site defaults |
| SEO tags `og:locale:alternate`, `article:published_time`, `article:modified_time`, `twitter:title/description/image` | **missing** |
| Structured data (JSON-LD) | NEW only (`Support/StructuredData.php`) |
| Sitemap: lastmod on terms, exclusion of pages with a canonical elsewhere, streaming, sitemap index above 50,000 URLs | **partial**: hand-built XML, 50,000 cap, everything loaded in one query |
| Search: main-language results for untranslated entries | **partial**; NEW ranks title matches first |
| Form submissions storing IP, user agent and referrer | **partial**: locale only |
| Comment settings `notify_roles` | **missing** (every moderator is mailed) |
| Content cache for queries, menus, globals, routes and the sitemap (`ContentCache`) | **partial**: `SiteCache` covers paths, patterns, listings, menus and the sitemap; `EntryQuery` and globals are not cached |
| Page-cache lifetime per page, flash bypass | **partial**: one `cache.ttl`, `skipWhen()` |
| Test email from Settings → Email (`TestMail`) | **missing** |
| Starter templates (`--tag=sunrice-templates`: layout, header, menu, language switcher, card, pagination, entry filter, error pages, CSS) | **missing** |
| Draft banner, unsubscribe page, error pages, Indonesian frontend strings (`sunrice::frontend.*`) | **missing** |

### 2.6 The admin panel

| OLD capability | NEW status |
| --- | --- |
| Login, remember me, three-way throttle, emailed two-factor code, password reset | present; NEW adds a throttle on the code routes, refuses login without `sunrice.access`, `RedirectIfAuthenticated` |
| Custom login template (`--tag=sunrice-login`) and branded login page (layout, eyebrow, headline, text, footer, image) | **missing** |
| Branding: name, tagline, logo, 11 fonts, brand colour with computed contrast, dark-mode override, editable in Settings (`Support/Branding.php`) | **missing**: `Panel::brand(string)` only |
| Users: Security card per user (lock reason and time, sessions, tokens, last login and IP, recent security events) | **missing**: row actions only; the lock action never passes a reason |
| Users: delete with content handling (reassign, keep or delete entries and assets) | **missing**: a bare `delete()` leaves `author_id` and `uploaded_by` dangling |
| Roles: refuse to delete a role still assigned | **missing** |
| Roles: managers may only create roles strictly below their own | **partial**: `PermissionRank::covers()` is a subset check, so a manager can clone their own access |
| Permissions scoped by id (renaming never breaks them) | changed: scoped by handle; renaming a collection orphans its permissions |
| `export` permission on resources | **missing** (export needs `view` plus `exportable()`) |
| Settings page: timezone, locales (main, available, names), SEO defaults, branding, security alert recipients, mail info and test mail, comments `notify_roles`, package tabs | **partial**: typed sections for `security` (2FA only), `site`, `code`, `comments`; locales and alert recipients are config only |
| Activity log observer on every model, with JSON sub-key diffs | changed: explicit `ActivityLog::record()` calls (plugins must log themselves) |
| Activity log: action filter, per-page preferences, in-admin prune with `sunrice.activity.prune` | **partial**: no action filter, 50 per page, prune from the CLI only |
| Security alerts: "many pages refused", owner notice "was this you?", log pruned, mass unpublish | **missing**; privileged permissions narrowed to users and roles |
| Security alert mail queued | changed: sent synchronously inside the request, once per recipient |
| Admin-layout 403, 404 and 500 pages | **missing** |
| Media: edit page per asset, title and alt on upload, `thumbnail()`, orphan detection, storage-link warning, folder permissions | **partial**: a details sheet, usage JSON endpoint, bulk move and restore (NEW), queued sizes, `max_pixels` |
| Resources: `belongs_to` and `belongs_to_many` searchable relation fields, rich text, repeater and asset fields, `Filter::dateRange`, `Action::url()`, `authorize()` closures | **missing**; NEW adds `Password`, `Secret`, `MediaPicker`, `CheckboxList`, `can()`, disabled fields, policy `*Any` |
| Dashboard: collection counts, recent edits, recent submissions, package cards (`addDashboardCard`) | **missing**: a welcome sentence; no widget API on `Panel` or `Plugin` |
| In-admin developer docs and a bilingual user guide (`DocsController`, `GuideController`, `MarkdownPages`, `guide/en`, `guide/id`) | **missing** |
| Indonesian core translations (`CoreTranslations`, `resources/lang-core/id`) and `frontend.php` strings | **missing** |
| Table column and page-size preferences per user | **missing** |
| Shared Inertia props: permissions list, locales, branding, flash id, settings tabs | **partial**: brand name, path, bundles, user, navigation, flash |
| Commands `sunrice:demo-content`, `import-structure`, `rename-field`, `switch-main-language`, `translate`, `orphans`, `mcp-token` | **missing**; NEW adds `sunrice:make-admin`, renames `publish-assets` → `assets`, `seed-roles` → `roles`, `regenerate-images` → `media-sizes` |
| Installer adds `HasRoles` to the user model | **partial**: warns instead |
| Config: `admin.domain`, `locales.names`, `translation.*`, `branding.*`, `mcp.*`, `security.alerts.forbidden` | **missing** |

### 2.7 AI / MCP

OLD had 38 tools; NEW has 22 (10 panel, 12 CMS). Generic resource tools
(`list_records`, `get_record`, `save_record`, `delete_record`,
`run_action`, `get_settings`, `update_settings`) replace about ten
structure tools, which is a better design. These OLD tools have no
equivalent:

| OLD tool | What it did | Note |
| --- | --- | --- |
| `read_docs` | Read the developer docs | No docs in the admin either |
| `get_template_guide`, `read_template`, `write_template` | Read and write Blade templates with a syntax check and a backup, behind super admin and `SUNRICE_MCP_TEMPLATES` | Guarded, high-value for agents building a site |
| `run_command` | Allow-listed artisan commands (`sunrice.mcp.commands`) | |
| `read_error_log` | Last 2 MB of the newest logs, secrets masked, behind `sunrice.error-log.view` | |
| `render_page` | Render a public URL and return status and HTML | |
| `seo_audit` | Site-wide audit of titles, descriptions, duplicates, alt text, missing translations | |
| `entry_revisions` | List, show and restore revisions | `RestoreRevision` exists, no tool |
| `reorder` | Manual order of entries, terms and menu items | |
| `save_asset`, `manage_asset` | Upload from URL or base64, alt text, folders, move, trash | NEW only lists media |
| `get_listing`, `save_listing` | Listing page content per language | |
| `get_blueprint`, `save_blueprint` | Blueprint fields with a field-type catalogue | `get_panel_info` has no field catalogue |
| `get_menu`, `save_menu` | Menu items (stored and rendered) | Menus are resources, items are not reachable |
| `delete_submissions` | Delete submissions with their files | |
| `translate_entry` (machine translation) | NEW `cms_translate_entry` only copies the main language | |
| `manage_entry` delete, duplicate, set_homepage | **partial** | |
| `update_site_settings` locales, timezone, branding | **partial** | |

Also missing: the stdio transport (`Mcp::local`, `sunrice.mcp.local_user`)
and `sunrice:mcp-token`. NEW only: read-only tokens, expiry in the UI,
revoke-all per user, per-token rate limit, `#[IsReadOnly]` gating,
scheduled publishing through `cms_publish_entry`.

### 2.8 Translation and multilingual

| OLD capability | NEW status |
| --- | --- |
| Machine translation providers (Gemini, OpenRouter) with batching, `TranslatorFactory`, config `sunrice.translation` | **missing** |
| `ContentTranslator` for entries, terms, globals and menus; `sunrice:translate` with `--to`, `--from`, `--only`, `--collection`, `--dry-run` | **missing** |
| `LangFileTranslator` for the app's own language files | **missing** |
| Switching the main language (`SwitchMainLanguage`, 414 lines, converts every stored structure; `--dry-run`) | **missing**: locales are env only, "the first is the main one" |
| Locales in Settings with display names (`Locales::names()`) | **missing**: `Locales::all/default/current/has` |
| Draft/Ready flag per translation, Outdated badge | changed: per-translation publish state; no Outdated badge |
| Whole-entity fallback | **missing** (see 2.5) |

### 2.9 Developer API: facade, helpers, queries

`Cms` facade (`Content.php`): `entries()`, `menu()`, `global()`,
`page()`, `bodyClass()`, `localeUrls()`, `site()`. Missing from OLD's
`Sunrice` facade: every registration hook (`resolveTemplateUsing`,
`bodyClassUsing`, `cachePageUsing`, `registerField`, `registerResource`,
`addNavigationItem`, `addSettingsTab`, `addDashboardCard`,
`registerMcpTool`, `importStructure`, `usersQueryUsing`), partly replaced
by the plugin contract (`fieldTypes()`, resources, navigation, settings,
tools) and partly gone (dashboard cards, template hooks, users query).

`EntryQuery` missing: `locale()` after construction, `preview()`,
`search()`, `whereTerm()` by slug with children, `whereFieldTerm()`,
`in` and `not in`, cast-aware number and date comparison and sorting
(`sortCast()` is declared and never used), `with()` preloading,
`toBase()`, cached ids. Each `values()` call makes a fresh
`HydrationContext`, so a list of entries runs one query per entry per
media, entries or terms field.

## 3. Design

**What the split gets right.** The panel knows nothing about the CMS and
the `tests/Arch` suite enforces it. Plugins declare resources, pages,
navigation, permissions, settings, field types, tools, shared data,
lockables and activity subjects through one `Plugin` contract, and the
panel validates duplicates and missing dependencies at boot. The SDK
makes plugin bundles share one React and loads them only on their pages.
Settings are typed classes with encryption. Permissions can depend on
data (`permissionsUsing()`). Every write is an action with its own
validation and activity line.

**Where modularity left holes.**

1. **No dashboard API.** `Panel`/`Plugin` have no way to add widgets, so
   the dashboard is a sentence. OLD's `addDashboardCard()` with deferred
   loading was the most visible plugin feature.
2. **No extension hooks on the site side.** Template resolution, body
   classes and page-cache decisions had hooks in OLD; NEW has only
   `PageCache::skipWhen()`. Plugins like the visual editor or commerce
   will need them.
3. **Locale ownership is unclear.** Locale names, the admin UI language
   and core translations belonged to OLD's settings; NEW has a config
   list in the CMS and nothing in the panel, so a panel-only app has no
   locale concept and the CMS cannot offer locales in Settings.
4. **Branding belongs to the panel but is a single string.** Logo, colour,
   font and login copy were a product feature; the CSS variables for
   them (`--sunrice-font`, `--hero`, `--orb`) still exist unused.
5. **Permissions keyed by handle.** OLD keyed scoped permissions by id so
   renames never broke roles; NEW keys by handle. Either key by id or
   rename permissions when a handle changes.
6. **References are media-only.** `MediaUsages` is a good panel API, but
   the CMS dropped entry-to-entry and entry-to-term references, which
   drove "used by", safe deletes and `with()` preloading.
7. **Translation model.** Full copies per language are simpler than the
   overlay but lose row-level sharing. A middle ground: keep full copies
   but mark non-translatable keys at every depth and merge them from the
   main language in `values()`.
8. **Two bespoke list pages.** Entries and comments are hand-built pages
   while every other list is a resource table. A reusable `DataTable`
   component (column visibility, sort, filters, bulk, export, DnD) would
   serve resources, entries, comments, media and activity alike.
9. **Admin-layout errors.** 403/404/500 under `/admin` fall through to the
   app's default pages; OLD rendered them inside the panel.

## 4. Ease of use

Ordered by how often an editor meets them:

- **Dashboard** shows nothing to do: no counts, no recent edits, no
  pending comments or submissions.
- **No dark-mode toggle.** `useAppearance` exists and the Blade layout
  honours a saved value, but no control sets it.
- **Ordering by buttons.** Entries, terms, menu items, repeater rows,
  flexible blocks and blueprint fields move one step per click with
  Move up / Move down; OLD dragged them.
- **Entry list** cannot filter by status, author or term, sort by a
  column, or show a field column; editors of a large collection search
  by title only.
- **No saved table preferences** (columns, page size).
- **Editor sections don't collapse** and long blueprints become one tall
  scroll; OLD's `CollapsibleCard` remembered state per section and
  opened on validation errors.
- **Pickers** for entries and terms are a search box and a list; OLD used
  a combobox (`cmdk`).
- **Date fields** are the browser's native inputs; OLD had a calendar.
- **No in-admin help**: no docs, no guide, no help link in the sidebar.
- **Branding**: an admin cannot set a logo or colour, so every install
  looks the same and the login page is a plain card.
- **Comments** cannot be seen from the entry they belong to.
- **Breadcrumbs** appear only when a page declares them; OLD derived them
  from navigation.
- **Copy to clipboard** in media details uses `navigator.clipboard`
  directly, which fails on plain-HTTP development hosts (OLD had a
  fallback).
- **Flash messages** have no id, so the same message twice may not toast
  twice.

## 5. Robustness: bugs found in NEW

1. **Stack overflow on self-referencing blocks.** `DefinitionValidator`
   deliberately allows a fieldset to list itself as a flexible block
   (`tests/Cms/Fields/DefinitionValidatorTest.php`), but
   `Fields/Types/Flexible::toAdmin()` recurses `schema(handle)->toAdmin()`
   with no depth guard (OLD had `static $depth`). Opening the editor for
   such a blueprint, or any A→B→A cycle, exhausts the stack.
2. **Slug race.** `Actions/Entries/EntryInput::taken()` checks `slug` and
   `draft_slug` without a lock, and `draft_slug` has only a plain index.
   Two concurrent drafts can share a slug; the second publish hits the
   unique index as an uncaught `QueryException`.
3. **Stale shared fields in translations.** `CreateTranslation` copies the
   whole `draft_data` including shared fields and `SaveDraft` never strips
   them, so CSV export and `search_text` of secondary languages read stale
   copies.
4. **Version not bumped on publish.** `PublishEntry` leaves `version`
   unchanged, so a stale editor can overwrite a draft after someone else
   published. `SaveGlobals`, `SaveTerm`, `SaveTermContent` and
   `SaveListing` have no concurrency check at all.
5. **Dangling blueprint references.** `DeleteBlueprint` leaves
   `Taxonomy.blueprint`, `GlobalSet.blueprint`, `Collection.listing_blueprint`
   and forms pointing at a deleted handle; the editor then shows no
   fields without saying why.
6. **Role rank is not strict.** `Users/PermissionRank::covers()` is a
   subset check; a user manager can create a role equal to their own
   access and hand it out.
7. **Deleting a role in use** strips it from users silently; OLD refused.
8. **Deleting a user** leaves `author_id` on entries and `uploaded_by` on
   media dangling (no FK, no reassignment).
9. **Security alerts parse free text.** `SecurityAlerts::gainedPower()`
   splits the note on `added:`; a role name containing `;` or `,` breaks
   it. Record `added` and `removed` as structured properties.
10. **Listing and term canonical URLs ignore the page number.** Page 2 of
    a listing canonicalises to page 1 (`Support/Seo.php`, `Site/Page.php`),
    a duplicate-content signal.
11. **Sitemap memory.** `Site/SitemapController::xml()` loads up to 50,000
    translations with relations in one `get()`.
12. **Login attempts while blocked are not recorded**; only the moment
    the limit is hit produces an event, so sustained attacks are
    under-reported.
13. **`sortCast()` in `Query/EntryQuery` is dead code**, so number and date
    fields sort as text.
14. **`Globals::get()` defaults to the main locale** while `Cms::global()`
    uses the current one; easy to misuse in templates.
15. **Redirects drop the query string** (`Site/SiteController::show`).
16. **Preview responses lack `Cache-Control: no-store`.**
17. **`Text` field default `max:255`** silently invalidates longer legacy
    data on the next publish.
18. **`ActivityLog` merges lines by `spl_object_id(request())`**, which is
    reused after garbage collection in long-running workers.
19. **Media upload ignores `title` and `alt`** sent with the file.

## 6. Security

No critical finding. Authentication, CSRF, per-route authorization,
upload checks, rich-text sanitising, secret storage and the MCP token
model are sound, and several things are stricter than OLD (session
regeneration on both login paths, `rel` forced on links, `SafeSvg`
rejecting DOCTYPE subsets, a mandatory honeypot, HMAC-hashed comment IPs,
a cookie-stripping page cache, Larastan at level max with no ignores).

### High

- **S1. AI tokens bypass `sunrice.access` and "sign out everywhere".**
  `Http/Middleware/AuthenticateAiToken` checks `sunrice.ai.connect` and
  the lock state only. Removing panel access or signing a user out
  everywhere leaves their `sra_…` token working, and tokens may never
  expire (`AiAccessController` accepts `null`). Fix: also require
  `Panel::ACCESS`, reject tokens created before `signed_out_at`, and
  default expiry to a value.
- **S2. Anyone can cold the whole site cache.** `Support/ContentWatch`
  bumps the single content version on every `Comment` save, and comments
  are posted anonymously (3 per minute per IP). Each post flushes pages,
  menus, path lookups and the sitemap. Fix: don't bump on comments
  (they render from the database), and split the version per area.
- **S3. Unbounded site search.** `Site/Search` runs `LIKE '%q%'` on a
  `longText` column with no full-text index and a `CASE` sort, limited
  per IP only. Fix: FULLTEXT (MySQL) or `pg_trgm` (Postgres) with
  driver-specific queries, a shorter `search_text` cap, a global limiter
  and a short result cache.

### Medium

- **S4. Plugin routes aren't covered by the "unguarded route fails"
  test.** `tests/Admin/ShellTest.php` asserts `Authenticate` and
  `EnsureCanAccessPanel` on panel routes only. Every CMS route is guarded
  today, but nothing stops an unguarded one. Add an arch test that every
  `sunrice.admin.*` route carries `can:` or `AuthorizeContent`.
- **S5. Comment auto-approval by unverified email.** With "hold first
  comment", any guest whose `author_email` matches a previously approved
  guest publishes without moderation, and reply mails go to that
  address. Key trust on IP hash plus email or a signed cookie; never
  notify unverified guests without opt-in.
- **S6. No security headers on the panel.** No `X-Frame-Options`,
  `Content-Security-Policy` or `Referrer-Policy`. Add a
  `PanelSecurityHeaders` middleware in `PanelRoutes::middleware()`.
- **S7. Privileged-permission alerts narrowed.** `SecurityAlerts`
  treats only `sunrice.users.*` and `sunrice.roles.*` as privileged, so
  granting `cms.settings.code` (site-wide script injection),
  `sunrice.ai.connect` or `sunrice.settings.security` (turn 2FA off)
  raises nothing. Let plugins register privileged permissions.
- **S8. Authors can render any app view.** `SaveEntrySettings` and
  `SaveTermContent` accept any `template` matching `[a-z0-9.-]` from
  users with `update-own`; `SiteController::view()` renders the first
  existing view, so `layouts.app` or `emails.invoice` become public
  pages. Require a `cms.` prefix or an allow-list, and restrict the
  setting to full `update`.
- **S9. Page-cache key pollution.** The default hasher keys on the full
  URI, so `?x=<random>` fills the cache store until TTL; CSRF correctness
  in cached forms depends on the app keeping responsecache's
  `CsrfTokenReplacer`. Ship a hasher that allow-lists `page` and `q`, and
  fail loudly if the replacer is missing.
- **S10. Password reset enumerates accounts.** `PasswordResetController`
  surfaces `passwords.user` for any token. Map `INVALID_USER` to the
  generic invalid-token message.
- **S11. Raster polyglots.** Uploads are served from the public disk
  without `nosniff`; `allowed_extensions` includes `json`, `txt`, `csv`,
  `zip`. Document web-server headers or re-encode raster uploads.
- **S12. Read-only tokens are enforced by name.** `TokenScope::refuses()`
  denies fixed CRUD abilities and registered non-`.view` permissions; an
  unregistered policy ability like `publish` passes the gate backstop and
  relies on laravel/mcp hiding the tool. Default-deny unknown abilities
  when read-only.

### Low

Repeated-403 alert dropped (OLD had it); `mass_deletes` no longer counts
unpublishing; kept edits are unbounded (1 MB each, any count); `SafeUrl`
accepts protocol-relative `//host`; moderator and notify addresses share
one `To:`; AI `list_media` doesn't escape LIKE wildcards; resource CSV
export checks `viewAny` only; login timing differs for unknown emails;
host-keyed caches need `TrustHosts` documented.

## 7. Performance

### High

- **P1. Image sizes generate synchronously by default** (`media.queue`
  is `false`): three encodes inside the upload request. Default to the
  queue when it isn't `sync` and let the UI refresh sizes.
- **P2. Site search full scan** (see S3).
- **P3. One global cache version.** Any save of media (even alt text),
  a comment, a term, a menu item, a global value, a blueprint or a form
  flushes every site cache. On a busy site the cache never warms. Split
  versions per area (pages, menus, sitemap) and bump only for changes
  that affect output.

### Medium

- **P4. Sitemap** loads up to 50,000 rows with relations per rebuild;
  chunk and cache per locale.
- **P5. Admin per-request overhead.** Sidebar visibility closures call
  `Access::entries/terms/submissions` which pluck every collection,
  taxonomy and form handle, plus `AccountGuard::isLocked` and
  `sessionAllowed`, about five extra queries on every admin page. Cache
  the handle lists and the locked-id set.
- **P6. Missing indexes** (Postgres and SQLite don't index FKs):
  `sunrice_revisions.entry_translation_id`, `sunrice_media_usages.media_id`,
  `sunrice_entry_terms.entry_id`, `sunrice_comments.parent_id` and
  `author_email`, `sunrice_menu_items.{entry_id,term_id,collection_id}`,
  `sunrice_entry_translations (status, published_at)` for the scheduled
  sweep, `deleted_at` on entries, media, terms and comments,
  `sunrice_media.mime_type`.
- **P7. Comments admin index** runs five `COUNT(*)` queries; one
  `GROUP BY status` would do.
- **P8. Entry editor payload.** The full blueprint ships on every load;
  `TermsField::toAdmin()` loads every term of the taxonomy with
  translations; `Flexible::toAdmin()` expands every block fieldset;
  `EntriesField::settings()` and `Flexible::settings()` query inside
  `settings()`, which validation calls once per field. No
  `Inertia::defer()` is used anywhere; revisions, term options and
  shared values are candidates.
- **P9. Page render extras.** `Seo::tags()` does `Media::find()` per
  page for the share image; `Seo::alternates()` lazy-loads translations;
  `Page::clean()` touches the session guard on every site page.
- **P10. Entry lists hydrate per entry** (see 2.9): one query per entry
  per reference field.

### Low

`md5_file` of the manifest per request; `CmsPlugin::asset()` stats the
dist file twice; `ScopedPermissions::all()` is three queries per request
when touched; `SettingsStore` caches forever in a possibly per-server
store; the admin chunk is 646 KB (lucide and Radix could split out).

## 8. Documentation

NEW has 22 developer pages (1,827 lines) against OLD's 23 (3,589 lines)
plus a 16-page end-user guide in English and Indonesian and an in-admin
reader for both. Topics with no coverage in NEW:

- **Whole pages:** `translation.md` (machine translation), `upgrading.md`,
  the end-user guide (all 16 pages, both languages), `mail.md` (SMTP,
  queue worker, test email), `commands.md` (one reference), `helpers.md`
  (facade reference), `configuration.md` (one config reference).
- **Sections:** installation upgrades; filtering the activity log and
  turning it off; starter templates and template hooks; whole-entity
  fallback, Draft/Ready, changing the main language, interface text;
  dashboard cards; a custom login screen; accounts that aren't staff;
  testing a plugin with Testbench; the "Shop" worked plugin example;
  custom security rules; sitemap and robots for editors.
- **READMEs:** the root README is 10 lines with no requirements, quick
  start or docs index; package READMEs are 5 and 13 lines.
- **CHANGELOG:** one `Unreleased` block with very long bullets and
  breaking changes bolded inline; no upgrading page to collect them.

What exists is good: `docs/cms/site.md`, `fields.md`, `collections.md`,
`docs/admin/panel.md`, `resources.md`, `settings.md`, `security.md` and
`ai.md` are accurate and specific.

## 9. UI and UX: toward an elegant, Apple-like panel

**Starting point.** `packages/admin/resources/css/admin.css` is
byte-identical to OLD's stylesheet and every `components/ui/*.tsx` is the
same shadcn file (OLD's `calendar.tsx` and `command.tsx` were dropped).
NEW then stopped using most of what that CSS offers: `sunrice-page-enter`,
`sunrice-shell`, `sunrice-atmosphere`, `sunrice-orb`,
`sunrice-card-content` and `sunrice-page-title` have zero references in
NEW's JavaScript or Blade. No web font is loaded (`--font-sans` names
Instrument Sans, `app.blade.php` has no font link), the dashboard is a
sentence, the login page is a centred card, the sidebar eases linearly,
and the CMS bundle has two colour transitions and six spinners. Motion on
both sides is stock shadcn plus `tw-animate-css`: no stagger, shimmer,
spring easing, glass surfaces or page transitions.

**Design direction.** Calm, warm, layered, quiet. Neutral surfaces with a
hint of warmth, one accent used sparingly, large soft radii, two-layer
shadows, generous whitespace, type that is tight at display sizes and
relaxed in body text, and motion that is quick (150–300 ms), eased out,
and always purposeful: things slide from where they came and settle with
a touch of overshoot. Every control answers the pointer within one frame.
Reduced-motion users get the same page without the travel.

**Plan, by layer (no new npm packages; `@dnd-kit` and shadcn's `command`
are on the approved list).**

1. **Typography.** Load Inter or Instrument Sans (400/500/600) in
   `app.blade.php` as OLD's `Branding::fontHref()` did; set
   `--font-sans` to `var(--sunrice-font, 'Inter'), -apple-system,
   'SF Pro Text', system-ui`; `font-feature-settings: 'cv11', 'ss01',
   'calt'`; `tabular-nums` on counts; reinstate `sunrice-page-title`
   (clamped size, `-0.035em` tracking) on every page header.
2. **Colour tokens** in `admin.css`: warm neutrals (background
   `oklch(0.985 0.004 80)`, foreground `oklch(0.26 0.01 60)`, border
   `oklch(0.92 0.006 75)`), `--surface-glass: color-mix(in oklch,
   var(--card) 72%, transparent)`, an `--accent-tint` for selected rows,
   dark surfaces around `oklch(0.17 0.008 260)`. CSS variables mean the
   prefixed CMS pages inherit everything.
3. **Shadows and radius**: `--shadow-card` (1px hairline + 24px soft),
   `--shadow-pop` (6px + 50px) as theme tokens used by `card`, `dialog`,
   `dropdown-menu`, `select`, `popover`; `--radius: 0.625rem`; restore
   the inset panel shadow on `SidebarInset` in `layouts/AppLayout.tsx`.
4. **Easing tokens**: `--ease-out-quart: cubic-bezier(0.22, 1, 0.36, 1)`
   and a `linear()` spring; replace `ease-linear` in `ui/sidebar.tsx`
   with the quart at 250 ms.
5. **Page transitions**: put `sunrice-page-enter` back on
   `<main key={page.component}>`; wrap navigation in
   `document.startViewTransition()` with `::view-transition-old/new(root)`
   keyframes under `@supports`, skipped when reduced motion is on.
6. **Glass chrome**: sticky header `bg-background/70 backdrop-blur-md`
   with a hairline border; sidebar `bg-sidebar/80 backdrop-blur-xl`.
7. **Lists**: a `row-in` keyframe with `animation-delay: calc(var(--i) *
   28ms)` capped at twelve rows for resource, entry and media lists;
   `hover:bg-muted/40` rows; media tiles lift half a pixel with
   `--shadow-card` on hover.
8. **Dialogs, sheets, toasts**: overlay `bg-black/30 backdrop-blur-sm`,
   content `zoom-in-[0.97] slide-in-from-bottom-2` on the spring; sheets
   350 ms in, 200 ms out; sonner toasts with `--shadow-pop`, rounded-xl,
   translucent background.
9. **Controls**: switch thumb on the spring, checkbox tick scaling from
   50%, inputs and the rich-text wrapper with a 4px `color-mix` ring glow
   on focus, buttons keep `active:scale-[0.98]`.
10. **Skeleton shimmer**: a gradient sweep instead of `animate-pulse`,
    shown on `router.on('start')` for lists.
11. **Collapsible sections**: port OLD's `collapsible-card.tsx` and its
    six DOM tests into the panel, export it through the SDK, use it in
    `form-sections.tsx`, the field builder and repeater rows (the
    `.sunrice-card-content` CSS already ships).
12. **Drag-and-drop**: a `SortableList` on `@dnd-kit` with a `DragOverlay`
    ghost (`--shadow-pop`, 1.02 scale, 0.4° tilt), exported through the
    SDK (bump `SDK_VERSION` and `SHARED_MODULES`) for the field builder,
    entries picker, menus, terms and ordered collections.
13. **Dark-mode toggle and user menu**: port OLD's `nav-user.tsx`
    (avatar, Light/Dark/System, log out) into the sidebar footer.
14. **Branding and login**: a `Branding` settings section in the panel
    (logo, colour with computed contrast, font, tagline, login copy and
    image) feeding the existing CSS variables; the split `AuthLayout`
    with `AtmosphereArt`.
15. **Dashboard**: a hero card, per-collection counts, recent edits,
    pending comments and submissions, plugin widgets through a
    `$panel->dashboardWidgets()` API, loaded with Inertia's `Deferred`.
16. **Command palette**: restore shadcn's `command` (cmdk) with Cmd+K
    searching navigation and recent entries.
17. **Reduced motion**: replace the global `0.01ms !important` clamp with
    rules scoped to travel animations so hover and colour transitions
    survive.
18. **Add DOM tests** for the collapsible card, sortable list and
    command palette, and a lint config (none exists on either side).

## 10. Roadmap

One change per pull request, as `AGENTS.md` asks. Ordered by risk, then
by how many editors notice.

**Phase 1: fix before anyone relies on it (security and data).**
S1 AI token access checks; S2 and P3 cache version split; S3 search
index and limiter; bugs 1 (flexible recursion), 2 (slug race), 4
(version on publish and on globals/terms/listings), 5 (blueprint
references), 6 (strict role rank), 7 (roles in use), 8 (user deletion
with reassignment); S8 template allow-list; S10 reset enumeration; S6
security headers; S4 plugin route test; P1 queued image sizes; P6
indexes.

**Phase 2: parity editors will miss first.**
Dashboard with a widget API; dark-mode toggle and user menu; drag-and-drop
ordering; entry list filters, sortable and field columns, saved
preferences; collapsible editor sections; branding settings and login
page; per-user security card and lock reason; test email; admin error
pages; comments inside the entry editor; outdated-translation badge;
terms as lockables; `thumbnail()` and title/alt on upload.

**Phase 3: parity developers will miss first.**
Internal entry links in `link`; route parameters from related entries;
main-language fallback with canonical; `entry-filter` component and the
missing component attributes; SEO field mapping, article and twitter
tags, listing page SEO; `EntryQuery` search, slug terms, `in`, cast-aware
sort, `with()`, caching; template and body-class hooks; starter
templates; `@bodyClass` and helper functions; entry and term references;
rename-field, import-structure, orphans and demo-content commands;
per-entry blueprint override; resource relation fields and date-range
filters; export permission; permissions keyed by id.

**Phase 4: translation and AI.**
Locales with names in Settings; switch main language; machine
translation providers and `sunrice:translate`; row-level sharing in
translations; Indonesian core strings; the sixteen MCP tools (templates
and commands behind the same guards as OLD), stdio transport and
`sunrice:mcp-token`.

**Phase 5: documentation.**
Upgrading, installation, mail, commands, helpers and configuration
pages; the end-user guide in English and Indonesian with an in-admin
reader and `read_docs`; fuller READMEs; a CHANGELOG with short bullets
and a breaking-changes section.

**Phase 6: the luxurious panel.** Section 9, in roughly the order listed:
tokens and typography first (one PR, visible everywhere), then motion
primitives, then components (collapsible, sortable, command), then the
dashboard and login.

## 11. Verification baseline

Checked on this branch in a container with PHP 8.3 (the packages require
8.4, and no 8.4 build was obtainable there):

| Check | Result |
| --- | --- |
| `npm run typecheck` | passes (admin app and CMS app) |
| `npm run test:frontend` | 29 tests pass |
| `composer lint` | passes |
| `composer test` | could not run: Laravel 13 and Symfony 8.1 use PHP 8.4 property hooks, which PHP 8.3 cannot parse (every test errors in `symfony/http-foundation` `Request.php:117`); run on PHP 8.4 or in CI |
| `composer analyse` | could not run for the same reason (Larastan boots the application) |
| `dist/` sizes | admin 812 KB (646 KB app chunk + 85 KB CSS + 15 page chunks), CMS 804 KB (151 KB + 648 KB lazy rich-text chunk); OLD 1.6 MB in one chunk |
