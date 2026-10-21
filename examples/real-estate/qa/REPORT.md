# Lumina Estates on Sunrice: QA report

Campaign date: 2026-10-20. Under test: **Sunrice** (`/home/user/sunrice`, packages `sunrice/admin` + `sunrice/cms`, current branch). Test site: **Lumina Estates** (`/home/user/realestate`, Laravel 13). This report merges the five per-hat findings files. Nothing in `/home/user/sunrice` was changed.

## 1. Summary

**What was built.** A bilingual (English main, Indonesian `/id`) website for a premium real estate agency in Jakarta and Bali. The admin built the whole content model through the panel: 5 taxonomies, 10 fieldsets, 15+ blueprints, 6 collections, 3 globals, 4 forms, 3 menus, redirects, 5 roles/users, 2FA and AI tokens. The developer wrote the Blade templates and helpers against the documented API, with Pest tests and query budgets. The editor entered all the content through the admin UI as Budi (editor role): 16 properties, 4 agents, 5 neighbourhoods, 6 journal posts, 4 testimonials and 12 pages, partly translated, with menus, globals, SEO and comments. Then a visitor and a security tester went over the result.

**How it was tested.** Five hats: admin, developer, editor, frontend visitor (with AI/MCP access), and security tester. The admin, editor and frontend work was driven through the real UI with Playwright (Chromium, 1440×900 desktop and 390×844 mobile; scripts in `qa/scripts/`). The database was MySQL 8 (`lumina`), served by `php artisan serve` on PHP 8.4 with a queue worker and scheduler. Both languages were checked page by page, and a crawl covered all 119 sitemap/page URLs for meta, JSON-LD, accessibility and timings. The security tester swept every admin route as each role, and also tested uploads, SSRF, SQLi, XSS, CSRF, sessions, cache isolation and MCP token scope.

**Verdict.** Sunrice is close to production-quality for a real agency site, and better than most CMSs in several ways:
- The editor experience is calm, and drafts, locks, revisions and translations work well.
- Permissions are scoped and they hold.
- Security defaults are solid: no critical security issue was found.
- The Guide is written for non-developers.
- The site that came out looks professional and on brief.

These problems would bite a real client in the first weeks:
- One data-corrupting admin bug: **saving a nested menu duplicates it** (ED-1).
- **The page cache can be poisoned through comment reply links** (CACHE-1).
- **Two auth issues:** a Host-header reset link (SEC-1) and a login password oracle (SEC-2).
- **Multilingual gaps** that hide most of the catalogue from Indonesian visitors and leave forms in English (I18N-1/2/3).
- **N+1 hydration** on listing pages and in entries fields (CACHE-2/3).
- **Settings screens that show the old values after saving** (ADM-1).

**Counts after de-duplication.** There are 103 findings with stable ids; "what worked" notes are not counted. Eight ids gather several small items of the same severity and area into one entry (ADM-10, STR-14, ED-17, ED-18, SITE-3, SITE-5, I18N-12, FORM-6), and each counts once.

| Critical | High | Medium | Low | Polish | Total |
| --- | --- | --- | --- | --- | --- |
| 1 | 13 | 44 | 38 | 7 | 103 |

By area: Install & docs 4, Admin UI & settings 10, Content modelling & structure 14, Editor experience 18, Site rendering & templates API 5, Multilingual 13, Forms & comments 6, Caching & performance 10, Security 12, AI access 5, Accessibility 6.

Some findings are app-side rather than Sunrice bugs; each one is marked "(app, not Sunrice)".

**The one blocker: no photos yet.** The environment's network policy blocks `images.unsplash.com` (403). By instruction, no other photos were used, so the site has **no photography**: the hero, cards, galleries, agent portraits and covers show quiet placeholders. Image weight, `srcset`, lazy loading, LCP and the gallery `<dialog>` could not be tested. The wanted photos (search terms, alt texts and the field for each) are listed in [`unsplash-wanted.md`](unsplash-wanted.md). Re-test FE gallery/LCP after the import.

**Where hats disagreed** (details in the findings):
- STR-2 hyphenated handles: admin rated it high, developer low.
- ED-3 / SEC: the security tester rated picker scoping correct (no leak); the editor rated the same behaviour a high usability problem.
- SEC-8 media accepts a text `.jpg`: the editor rated it medium, while the security tester found every dangerous variant rejected.
- CACHE-8: the developer reported "repeat visits cost 0 queries", but the frontend tester found that each cookieless hit still writes a session row.

## 2. Fix first (top 20)

Ranked by user impact × likelihood.

| # | Id | Severity | Why first |
| --- | --- | --- | --- |
| 1 | [ED-1](#ed-1) | critical | Saving any nested menu (e.g. renaming one label) duplicates items on the live site |
| 2 | [CACHE-1](#cache-1) | high | One `?reply_to=` visit poisons a kept page; every new comment becomes a reply to someone else |
| 3 | [SEC-1](#sec-1) | high | Password-reset link host comes from the request `Host` header: token theft / account takeover |
| 4 | [I18N-2](#i18n-2) | high | Indonesian listings, terms and search show only translated entries (6 of 15 properties) |
| 5 | [ADM-1](#adm-1) | high | Settings pages show pre-save values: 2FA looks on when it's off |
| 6 | [ED-2](#ed-2) | high | A page's parent is silently lost unless a separate "Save settings" button is pressed |
| 7 | [SEC-2](#sec-2) | high | Login confirms email + correct password for accounts without panel access |
| 8 | [CACHE-3](#cache-3) | high | Linked entries hydrate one by one: 121 queries on the real homepage |
| 9 | [CACHE-2](#cache-2) | high | Listing/term/search `$entries` aren't hydrated together: ~5 queries per card |
| 10 | [I18N-1](#i18n-1) | high | Form labels, options and validation messages stay English on Indonesian pages |
| 11 | [I18N-3](#i18n-3) | high | Menu filter links send Indonesian visitors to English; English filter values are ignored on /id |
| 12 | [STR-1](#str-1) | high | Structure export/import drops "SEO from fields" and forms' notify roles |
| 13 | [ED-3](#ed-3) | high | Authors / scoped roles can't fill Author, Agent or Related fields ("No entries match.") |
| 14 | [ED-4](#ed-4) | high | Reordering pages shows a flat list that mixes children with unrelated pages |
| 15 | [SEC-3](#sec-3) | medium | Translator can re-parent and reorder pages and close comments |
| 16 | [CACHE-4](#cache-4) | medium | Kept pages outlive a deploy and link deleted CSS/JS (unstyled site for up to an hour) |
| 17 | [FORM-1](#form-1) | medium | Honeypot timing check is void on kept pages |
| 18 | [CACHE-5](#cache-5) | medium | Any `page`/`q`/filter value creates a new kept page on any URL (cache flooding) |
| 19 | [SEC-6](#sec-6) | medium | 2FA can be enabled while mail is the `log` mailer: everyone locked out |
| 20 | [ED-6](#ed-6) | medium | Scheduling: picker opens on the wrong month, past date publishes at once, header says "Published" |

## 3. Findings by area

Each finding gives its severity, the hats that hit it, where it happens, steps, expected and actual behaviour, the source file when known (paths relative to `/home/user/sunrice/packages/`), screenshots (relative to this file) and a suggested fix. Within each area, findings are ordered by severity.

### 3.1 Install & docs

#### INS-1
**Guide → Structure leaves out what actually blocks a first build**
- **Severity:** medium · **Hats:** admin (developer agrees on reserved handles and hyphens)
- **Where:** `/admin/guide/website/structure`
- **Actual:** The page doesn't cover:
  - the order things must be created in (taxonomies → fieldsets that reference nothing → blueprints → collections → blueprints with Entries fields → page fieldsets → back to taxonomies to tick collections);
  - the reserved field handles;
  - that a Fieldset field is flattened into its parent and its own handle ignored;
  - that no handle may contain hyphens (the Guide says this only for collections).

  The admin found each of these by a failed save.
- **Expected:** A short "Setting up a new site, in order" section.
- **Fix:** Add that section, plus the reserved list (`Blueprint::RESERVED`, `cms/src/Models/Blueprint.php:28`).

#### INS-2
**`Settings::save()` doesn't fire `SettingsSaved`, although the docs say it does**
- **Severity:** medium · **Hats:** developer
- **Where:** admin docs `settings.md`; `admin/src/Actions/Settings/SaveSettings.php:32` (only the action dispatches the event)
- **Steps:** 1. In a seeder: `$s = app(SiteSettings::class); $s->homepage = $id; $s->save();` 2. Request `/` in the same process.
- **Expected:** The homepage is served at `/`.
- **Actual:** 404. `Site` keeps the homepage it already read.
- **Workaround:** `app(SaveSettings::class)->handle(...)` (`tests/Support/LuminaSite.php`).
- **Fix:** Dispatch from `Settings::save()`, or correct the docs.

#### INS-3
**Guide wording doesn't match the screens**
- **Severity:** low · **Hats:** admin
- **Mismatches:**
  - Collections say "Reorder in the list", but the form says "Move up, Move down".
  - The Languages page says "the panel refuses an empty list", but it opens empty (ADM-5).
  - Welcome ("no profile page") and Users and roles ("everyone may edit their own name, email and password") read as contradictory, and neither says how an editor changes their own password.
  - The editor role is described as "Not structure", but the seeded role can manage menus (SEC-10).
  - The Security screen points to `php artisan sunrice:two-factor off` without a "For developers" box.
- **Fix:** Align the wording; add a self-service password path.

#### INS-4
**Docs gaps on what templates receive**
- **Severity:** low · **Hats:** developer (admin also saw the `/team` 500)
- **Where:** `site.md` → Templates → "Views get"; `fields.md`
- **Actual:**
  - `$richText` and `$draft` are passed to entry views but missing from the table.
  - The docs don't say that a single entries field gives one `EntryTranslation` (or null), or that a single terms field gives one `Term`.
  - Listing `$values` holds only the listing blueprint's handles, and is empty without one. On a collection with no listing blueprint, `$values['intro']` threw "Undefined array key" and `/team` answered 500 (template bug, fixed with `?? null`).
  - Listing/term `$entries` aren't hydrated together (CACHE-2).
- **Fix:** Complete the table and note these return shapes.

### 3.2 Admin UI & settings

#### ADM-1
**Settings pages show the pre-save values after saving (2FA switch, number fields)**
- **Severity:** high · **Hats:** admin
- **Where:** Settings → Security → Two-factor login; Settings → Comments → "Close comments after (days)" (likely every settings section)
- **Steps:** 1. With 2FA on, switch it off and Save. The "Security settings saved." toast appears and the alert email is sent. 2. Look at the switch. 3. Separately, type 365 in "Close comments after", Save, and look at the field.
- **Expected:** The form shows what was saved.
- **Actual:**
  - The 2FA switch springs back to **on** while the DB holds `{"twoFactor":false}`.
  - The number field goes blank while the DB holds 365. The reverse also happens (clear the field, save: DB null, field shows 365). Typing into the stale field then shows "365365".
  - Only a reload shows the truth.
- **Screenshots:** [bug-2fa-switch-stale-after-save](report-images/bug-2fa-switch-stale-after-save.jpg), `screenshots/admin-88-2fa-off.jpg`, `screenshots/admin-21-comments-close-days-after-save.jpg`, `screenshots/admin-15-comments-saved.jpg`
- **Fix:** Re-initialise the form state from the server props after a successful save (or return the saved values).

#### ADM-2
**A wide logo is squeezed into a square tile and becomes the favicon**
- **Severity:** medium · **Hats:** admin
- **Where:** Settings → Branding → Logo; sidebar; sign-in page; browser tab
- **Steps:** Upload a 208×56 wordmark SVG, choose it as the Logo, then Save.
- **Expected:** The logo shown at its own proportions, replacing the name. The favicon stays the generated letter because the logo isn't square, as the help text promises.
- **Actual:**
  - The logo is shrunk into the 32 px square next to the name.
  - The preview crops it to "UM / TATES".
  - The favicon becomes the wide SVG.
- **Screenshots:** [bug-wide-logo-squeezed](report-images/bug-wide-logo-squeezed.jpg), `screenshots/admin-95-branding-with-logo.jpg`, `screenshots/admin-96-signin-with-logo.jpg`
- **Fix:** Support wide logos (show the logo instead of the name), and only use a square logo as the favicon.

#### ADM-3
**Admin fonts load from fonts.bunny.net with no note**
- **Severity:** low · **Hats:** admin (editor timings and developer env notes)
- **Where:** Settings → Branding → Font (every choice, including the default)
- **Actual:**
  - Every admin page requests `fonts.bunny.net`. It is blocked here, so the panel silently falls back and the font choice appears to do nothing.
  - This is a third-party request (GDPR, intranet) with no hint that "System" avoids it.
  - It adds to admin page times (CACHE-9).
- **Fix:** Bundle the fonts, or document the CDN and recommend "System" for offline/privacy installs.

#### ADM-4
**Activity log: raw keys, no before/after, no "who" filter, awkward sentences**
- **Severity:** low · **Hats:** admin
- **Where:** Administration → Activity log; dashboard Recent activity
- **Actual:**
  - Details are internal keys (`seo_description_field`, `closeAfterDays`, `permissions`) with no values, and menu updates have none at all.
  - The type filter lists "Menu" twice.
  - There's no Who filter (although the dashboard links use `?subject=1`).
  - Sentences read "Ayu Pratiwi logged in Ayu Pratiwi", "Someone login failed Ayu Pratiwi", "Eko Wibowo page refused Eko Wibowo".
- **Screenshots:** `screenshots/admin-80-activity.jpg`, `screenshots/admin-81-activity-filtered.jpg`, `screenshots/admin-82-activity-updated.jpg`, `screenshots/admin-02-login-wrong-password.jpg`, `screenshots/admin-03-dashboard-first.jpg`
- **Fix:** Store before→after diffs (especially for permissions), add a Who filter, and use sentence templates per event.

#### ADM-5
**Languages settings: starts empty, free-text syntax, artisan to change main language**
- **Severity:** low · **Hats:** admin
- **Where:** Settings → Languages (`/admin/settings/locales`)
- **Actual:**
  - The textarea is empty on a site that already has en/id, with the placeholder "en: en / id: id".
  - The format is "code: name" per line, with no validation, and the main language is just the first line.
  - Changing the main language means running `php artisan sunrice:switch-main-language`.
  - The URL is `/locales` while the menu says Languages (`/settings/languages` 404s; Site code is at `/settings/code`).
- **Screenshot:** `screenshots/admin-10-languages-before.jpg`
- **Fix:** Prefill from config; use a small repeater (code, name, main) with validation.

#### ADM-6
**Structured data publisher has no name, address or sameAs**
- **Severity:** low · **Hats:** admin
- **Where:** Settings → Site → Structured data
- **Actual:** Only Organization/Person and a logo. There's no name, URL, address, phone or social profiles, and no RealEstateAgent/LocalBusiness option.
- **Fix:** Add these fields, or state that the site name is used.

#### ADM-7
**Mail page doesn't flag the default `hello@example.com` sender**
- **Severity:** low · **Hats:** admin
- **Where:** Settings → Mail
- **Actual:** The From address is read-only "Lumina Estates <hello@example.com>". The page is honest about the log mailer, and the test email works.
- **Screenshots:** `screenshots/admin-17-mail.jpg`, `screenshots/admin-19-mail-test-sent.jpg`
- **Fix:** Warn while the From address is still the example default.

#### ADM-8
**Dashboard after setup: no "what's next", and Recent activity is security noise**
- **Severity:** low · **Hats:** admin
- **Where:** `/admin`
- **Actual:**
  - "Recently edited: Nothing has been edited yet." after creating 15 blueprints (it counts entries only).
  - Recent activity is all alert, refused and token lines.
  - Nothing says a homepage, pages, the legal menu, mail and globals are still missing.
- **Screenshot:** `screenshots/admin-83-dashboard-after-setup.jpg`
- **Fix:** Add a setup checklist; let Recently edited include structure, or rename it.

#### ADM-9
**Creating a user means typing their password; no invitation**
- **Severity:** low · **Hats:** admin
- **Where:** Users → New user
- **Actual:** The password is required, and there is no invitation email or forced change at first sign-in. Users can't change their own password except through "Forgot password?".
- **Fix:** Add a "send invitation" option and a minimal own-account page.

#### ADM-10
**Polish: admin screens**
- **Severity:** polish · **Hats:** admin
- **Items:**
  - Saving Branding answers 409 (by design: a full reload), which logs a console error. `screenshots/admin-06-branding-saved.jpg`
  - The brand colour error "format is invalid" doesn't show the hex format, and the input is silently cut to 7 characters. `screenshots/admin-07-branding-invalid-colour.jpg`
  - The sign-in `<title>` reads "Log in to Lumina Estates · Lumina Estates".
  - The "Add the site name to titles" preview always shows `"About | Acme"`. `screenshots/admin-12-site-structured.jpg`
  - The Security page has a single switch and points to artisan. `screenshots/admin-20-security.jpg`
  - Role names show as slugs (`super-admin`) next to "Listings manager".
  - The translator sees every collection; there is no per-collection translate permission.
  - Two places are called "Menus" (Content → Menus, Structure → Menu settings), and admin URLs are inconsistent (`/admin/cms/blueprints` vs `/admin/collections`).
  - Developer wording on admin forms (Template "cms.{handle}", `<x-sunrice-cms::form …/>`, `SUNRICE_CAPTCHA_SITE_KEY`).
- **Fix:** Small copy and UI fixes; tuck developer hints under "For developers".

### 3.3 Content modelling & structure

#### STR-1
**Structure export/import drops "SEO from fields" and forms' "Also email roles"**
- **Severity:** high · **Hats:** developer
- **Where:** `php artisan sunrice:export-structure`; `cms/src/Structure/StructureImporter.php:72` (`COLLECTION`), `:74` (`TAXONOMY`), `:78` (`FORM`)
- **Steps:** 1. Set Properties → SEO from fields (description ← summary, image ← gallery). 2. Export the structure and import it on a fresh install. 3. Open a property.
- **Expected:** The versioned content model reproduces the site's SEO behaviour.
- **Actual:**
  - `seo_title_field`, `seo_description_field` and `seo_image_field` aren't exported or imported, and neither are forms' `notify_roles`.
  - A fresh install has no meta descriptions or share images from fields.
  - There's no public action to set them (`Admin\SeoMapping` is @internal).
- **Workaround:** App command `lumina:export-structure` plus `StructureSeeder` set the model attributes.
- **Fix:** Add the keys to the export/import whitelists and a test that round-trips every model attribute.

#### STR-2
**Handles can't contain hyphens; the agreed model couldn't be built as written**
- **Severity:** medium (admin rated high, developer low) · **Hats:** admin, developer
- **Where:** Every handle field (fieldsets, blueprints, taxonomies, collections, globals, forms, menus)
- **Steps:** New fieldset with handle `contact-person`, then Create.
- **Expected:** Hyphens accepted (as in the brief and other CMSs), or converted.
- **Actual:**
  - "The handle field format is invalid." Everything became `property_types`, `rich_text`, and so on.
  - Views are now named `taxonomy-property_types.blade.php` next to dashed URLs.
  - The helper text does say "underscores".
- **Fix:** Accept hyphens (normalise them), or have the error suggest the underscore version.

#### STR-3
**Reserved field handles are only discovered on save**
- **Severity:** medium · **Hats:** admin, developer
- **Where:** Blueprint editor; `cms/src/Models/Blueprint.php:28`
- **Steps:** Build the 23-field `property` blueprint with a select `status`, then Create.
- **Expected:** A warning while typing; the list in the Guide/docs.
- **Actual:** The toast `"status" is used by the content itself` appears only after the whole blueprint is built (same for `author`). The list (`id, title, slug, url, status, locale, published_at, created_at, updated_at, parent, children, collection, blueprint, data, seo, author`) is documented nowhere; fields.md only gives examples.
- **Fix:** Validate inline as the user types; publish the list.

#### STR-4
**A Fieldset field is flattened into its parent; its own handle is ignored**
- **Severity:** medium · **Hats:** admin
- **Where:** Blueprint `company` → Fieldset field `primary_contact` → Contact person
- **Steps:** A blueprint with `phone` and `email` plus a Fieldset field whose fieldset also has `phone` and `email`, then Create.
- **Expected:** Stored under `primary_contact.*`, as the label and handle boxes suggest.
- **Actual:** "There is already a field "phone" here." The include is pasted into the parent.
- **Workaround:** Wrap the include in a Group `primary_contact`.
- **Screenshot:** `screenshots/admin-43-bp-company-group-workaround.jpg`
- **Fix:** Either namespace includes under their handle, or remove the handle box and explain the flattening.

#### STR-5
**"SEO from fields" lists the fields of every blueprint**
- **Severity:** medium · **Hats:** admin
- **Where:** Collections (and Taxonomies) → SEO from fields
- **Steps:** Edit Neighbourhoods and open Description.
- **Expected:** Only the Neighbourhood blueprint's text fields (`intro`).
- **Actual:** About, Address, Client, Email, Legal name, Message, Newsletter heading…: fields from every blueprint, including forms and globals.
- **Screenshots:** [bug-seo-fields-all-blueprints](report-images/bug-seo-fields-all-blueprints.jpg), `screenshots/admin-47-seo-from-fields-all-blueprints.jpg`
- **Fix:** Filter by the collection's blueprint(s).

#### STR-6
**Raw validation key when an Entries field has no collection**
- **Severity:** medium · **Hats:** admin
- **Where:** Blueprint editor, Entries field before any collection exists
- **Actual:**
  - The toast reads "The fields.0.config.collections field is required.", with no inline error.
  - "Nothing to choose from yet" doesn't say what to create. The same happens for Flexible blocks and the Link field.
- **Screenshot:** `screenshots/admin-40-entries-field-no-collections.jpg`
- **Fix:** Use human attribute names and inline errors; "Create the Agents collection first (Structure → Collections)".

#### STR-7
**Blueprint pickers offer every blueprint for every purpose**
- **Severity:** low · **Hats:** admin
- **Where:** Global Sets, Forms, Listing page fields, Term fields
- **Actual:** A global set can pick "Contact form", and a form can pick "Agent".
- **Fix:** Give blueprints a kind (entries, listing, terms, global, form).

#### STR-8
**A taxonomy must be attached twice**
- **Severity:** low · **Hats:** admin
- **Actual:** A Terms field in the blueprint doesn't attach the taxonomy; you also tick the collection on the taxonomy. The collection form doesn't show its taxonomies.
- **Fix:** Derive one from the other, or show both sides.

#### STR-9
**Handle mutability is inconsistent**
- **Severity:** low · **Hats:** admin
- **Actual:** Collections, taxonomies and forms "can be changed later"; blueprints, fieldsets, globals and menus "can't change later". A typo means delete and recreate (and a blueprint in use can't be deleted).
- **Fix:** Allow renaming, or warn before Create.

#### STR-10
**Blueprint editor: every field card expanded; "Extra rules" looks pre-filled**
- **Severity:** low · **Hats:** admin
- **Actual:** The `property` blueprint page is ~16,600 px tall, with no collapse-all. The placeholder `min:3` / `regex:/^[A-Z]/` looks like real rules, and "Laravel rules, one per line" is developer language.
- **Screenshot:** `screenshots/admin-48-bp-property.jpg`
- **Fix:** Collapsed cards with a summary; a clearer placeholder.

#### STR-11
**Form file field: one file only**
- **Severity:** low · **Hats:** admin
- **Actual:** The valuation form's `photos` field can take one photo.
- **Fix:** Add a "multiple files" option with a max count.

#### STR-12
**Schema types limited**
- **Severity:** low · **Hats:** admin
- **Actual:** The choices are Article, Blog post, News article, Web page, About and Contact. Nothing fits people (agents) or offers/residences (properties). The developer worked around it with `:schema` on the seo component.
- **Fix:** Add Person, Product/Offer, Place, or a custom type.

#### STR-13
**Menus need pages first; nothing says so**
- **Severity:** low · **Hats:** admin
- **Actual:** The `legal` menu stayed empty ("No entries match.") because no pages existed yet, and the Guide doesn't suggest the order.
- **Screenshot:** `screenshots/admin-65-menu-legal-no-pages.jpg`
- **Fix:** Add a hint in the menu's empty search state.

#### STR-14
**Polish: structure screens**
- **Severity:** polish · **Hats:** admin
- **Items:**
  - Flexible blocks are stored alphabetically, so Hero can't come first in "Add block".
  - Taxonomy, collection, global, menu and form handles aren't generated from the title (fieldset and blueprint handles are).
- **Fix:** Keep the order of ticked blocks; auto-generate handles everywhere.

### 3.4 Editor experience

#### ED-1
**Saving a menu that has nested items duplicates the menu**
- **Severity:** critical · **Hats:** editor (it also happened to the admin's earlier `main`)
- **Where:** `/admin/cms/menus/main`; `cms/src/Actions/Menus/SaveMenu.php:58` (`$menu->items()->delete()` inside the transaction)
- **Steps:** 1. Build a menu with a child item (Properties › For sale) and Save. 2. Reload. 3. Save again without changes. 4. Reload.
- **Expected:** The same 11 items.
- **Actual:**
  - 18 items after the second unchanged save, although the PUT sends 11.
  - Old rows are only partly deleted: the first top-level item and its children go, the others stay next to the new copies.
  - The live main menu showed Neighbourhoods, Team, Journal, About, Our story, Services and Contact twice each, with the Indonesian label on one copy only.
  - Flat menus clear correctly. The editor's hypothesis: the delete runs on a relation ordered by `sort_order, id` while `parent_id` cascades.
- **Screenshots:** [bug-menu-duplicates](report-images/bug-menu-duplicates.jpg), `screenshots/editor-81-menu-main-saved.jpg`, `screenshots/editor-83-menu-main-built.jpg` (clean rebuild)
- **Workaround:** Remove every item, save and reload until empty, then build and save once. The next edit duplicates again.
- **Fix:** Delete with an unordered query (e.g. `MenuItem::where('menu_id', …)->delete()`, children first or no cascade conflict), and add a test that saves a nested menu twice.

#### ED-2
**Page parent is saved only by a separate "Save settings" button and is lost without warning**
- **Severity:** high · **Hats:** editor
- **Where:** Any page → Page settings → Parent
- **Steps:** Open "Our story", pick "About" as parent, then press Save draft, Ctrl+S or Publish changes, or leave the page.
- **Expected:** The parent is saved with the page, or a warning appears.
- **Actual:**
  - Only the card's own "Save settings" button saves the parent; nothing warns on leave, and the header says "✓ Published".
  - The parent can't be set while creating a page.
  - All six child pages had lost their parent.
- **Screenshots:** [bug-page-parent-save-settings](report-images/bug-page-parent-save-settings.jpg), `screenshots/editor-57-parent-picked.jpg`, `screenshots/editor-55-pages-tree.jpg`
- **Fix:** Include the page settings in the main save and the dirty state; allow the parent on create.

#### ED-3
**Pickers only list collections the user can view: authors and scoped roles can't fill linking fields**
- **Severity:** high · **Hats:** editor, admin (open question about Eko), security (sees the scoping as correct)
- **Where:** Journal → new post as Citra (author, journal only); `/admin/cms/entry-picker?search=Rafael&collections[]=agents` → `{"items":[]}`
- **Steps:** As Citra, type "Rafael" in Author or "Seminyak" in Related properties.
- **Expected:** The entries she may link to, or a clear note.
- **Actual:** "No entries match." reads as if the agent doesn't exist, and Budi had to finish the post. Eko (Listings manager) faces the same with Agent and Neighbourhood on properties (the admin flagged it as an open question).
- **Hats disagree:** The security tester rated "pickers only return entries from collections the user may view" as correct (no cross-scope leak). Both are true: it isn't a leak, but it blocks the workflow.
- **Screenshot:** `screenshots/editor-45-citra-author-picker-empty.jpg`
- **Fix:** A separate "may link to" scope (titles only) for pickers, or a clear message ("You can't see Agents; ask an editor").

#### ED-4
**Reordering a hierarchical collection scrambles children among unrelated pages**
- **Severity:** high · **Hats:** editor
- **Where:** Pages → Reorder
- **Steps:** With About › Our story and Services › four children, drag Home to the top, then Done.
- **Expected:** A tree, ideally with drag-to-nest.
- **Actual:** A flat list sorted by `sort_order` across levels: Home, About, Our story, Services, Buy, **Sell with us**, Sell, **Contact**, Rent, **Privacy policy**, Property management, **Terms**. The site's order per level stays right, but the editor can't see what they are ordering.
- **Screenshots:** [bug-pages-reorder-mixed-levels](report-images/bug-pages-reorder-mixed-levels.jpg), `screenshots/editor-62-pages-after-drag.jpg`
- **Fix:** Render reorder mode as a tree (sort among siblings, indent to nest).

#### ED-5
**Command palette finds entries only on CMS pages, not on the Dashboard**
- **Severity:** medium · **Hats:** editor
- **Where:** Ctrl+K on `/admin`, `/admin/media`
- **Actual:** "Nothing found." for "Menteng", and no request is sent. On `/admin/cms/...` pages it lists 4 entries. Indonesian titles aren't found either.
- **Screenshots:** [bug-palette-dashboard-nothing-found](report-images/bug-palette-dashboard-nothing-found.jpg), `screenshots/editor-107-palette-search.jpg`
- **Fix:** Register the CMS palette source on every panel page (it's small), and search translations too.

#### ED-6
**Scheduling: "✓ Published" button on scheduled entries, wrong picker month, past date publishes immediately**
- **Severity:** medium · **Hats:** editor
- **Where:** Entry → Publish ▾ → Publish on a date…
- **Actual:**
  - The chip says Scheduled, but the main button is a disabled "✓ Published", and there's no unschedule.
  - The picker opened on the entry's old month (September), so the editor picked 20 Sep. A past date publishes at once with no confirmation.
  - The time input is too narrow ("05:12 PI") and uses a 12-hour clock beside a 24-hour field.
  - Scheduled publishing itself worked on time.
- **Screenshots:** [bug-scheduled-shows-published](report-images/bug-scheduled-shows-published.jpg), `screenshots/editor-38-scheduled-state.jpg`, `screenshots/editor-33-schedule-picker.jpg`, `screenshots/editor-34-schedule-set.jpg`
- **Fix:** A "Scheduled for …" header state with Change/Unschedule; open the picker on today; confirm past dates.

#### ED-7
**Dated collections have no date field**
- **Severity:** medium · **Hats:** editor
- **Where:** Journal → New entry
- **Actual:** The only way to back-date a post is Publish ▾ → "Publish on a date…" with a past date. Changing a date later means Unpublish and re-publish.
- **Screenshot:** `screenshots/editor-40-journal-create.jpg`
- **Fix:** Show a "Published date" field in the Status card.

#### ED-8
**Revisions show only title and time**
- **Severity:** medium · **Hats:** editor
- **Actual:** No author, no list of changed fields, no compare; you find a revision by restoring it. Restore into the draft plus "Undo the restore" works well.
- **Screenshots:** `screenshots/editor-28-revisions-card.jpg`, `screenshots/editor-26-after-restore.jpg`
- **Fix:** Show who published, which fields changed, and a compare view.

#### ED-9
**Long forms: blocks and repeater rows can't be collapsed, have generic names, and "Show" looks like expand**
- **Severity:** medium · **Hats:** editor
- **Where:** Home (7 blocks, ~5,000 px), property Features, neighbourhood Highlights
- **Actual:** "Item 1", "Feature 2". The per-row "Show" switch means "show on site" (`_hidden`) but reads like expand.
- **Screenshots:** `screenshots/editor-60-home-saved.jpg`, `screenshots/editor-13-neighbourhood-ubud.jpg`
- **Fix:** Collapsible rows titled by their first text field; rename the switch "Visible on site".

#### ED-10
**Menu "Out a level" adopts the following siblings**
- **Severity:** medium · **Hats:** editor
- **Steps:** Under Properties: For sale, For rent, Villas. Press Out a level on For rent, then In a level.
- **Actual:** Villas became For rent's child and stayed there.
- **Screenshot:** `screenshots/editor-80-menu-outdented.jpg`
- **Fix:** Outdent only the item (and its own children), or explain the behaviour.

#### ED-11
**Validation: stale server errors next to native bubbles; no length counter**
- **Severity:** medium · **Hats:** editor
- **Where:** Testimonial new entry; property summary
- **Actual:**
  - "The title field is required." stays under a filled title while Chrome shows "Value must be less than or equal to 5."
  - A 371-character summary is refused only on save (message shown twice in the toast), with no `maxlength` or counter.
  - Negative and decimal numbers are blocked only by native bubbles.
- **Screenshots:** `screenshots/editor-144-validation-rating.jpg`, `screenshots/editor-145-validation-summary.jpg`, `screenshots/editor-146-validation-numbers.jpg`
- **Fix:** Clear field errors on change; one validation style; counters for max lengths.

#### ED-12
**Homepage setting: editors can't set it, and the picker lists every entry**
- **Severity:** medium · **Hats:** editor
- **Actual:** Budi gets 403 on Settings. For Ayu it's an unsearchable 44-option list mixing all collections and drafts.
- **Screenshots:** `screenshots/editor-63-budi-settings.jpg`, `screenshots/editor-65-homepage-set.jpg`
- **Fix:** A searchable picker limited to live pages; consider an editor-level permission for the homepage.

#### ED-13
**Trash UX**
- **Severity:** medium · **Hats:** editor
- **Actual:**
  - The confirm button says **Delete** for a reversible trash (the confirm text itself is good), and the toast has no Undo.
  - The Trash filter shows rows as "Published".
  - Restore appears only after ticking rows.
  - `?status=trash` is ignored in the URL.
- **Screenshots:** `screenshots/editor-95-trash-confirm.jpg`, `screenshots/editor-97-properties-trash.jpg`, `screenshots/editor-98-trash-selected.jpg`
- **Fix:** Label the button "Move to trash", add Undo, show a "Trashed" status, add a row-level Restore, and keep the filter in the URL.

#### ED-14
**Entry pickers show nothing until you type**
- **Severity:** medium · **Hats:** editor
- **Where:** Agent, Neighbourhood, Team, Testimonials, Featured properties, Parent, menu Entry
- **Screenshot:** `screenshots/editor-14-entry-picker-search.jpg`
- **Fix:** List recent or first N entries on focus.

#### ED-15
**No review step for authors**
- **Severity:** medium · **Hats:** editor
- **Actual:** Citra sees Save draft and Duplicate only. Nobody is notified, and the dashboard doesn't show drafts waiting for review.
- **Screenshot:** `screenshots/editor-44-citra-post-created.jpg`
- **Fix:** "Submit for review" with a notification and a dashboard widget.

#### ED-16
**CSV export: link fields as raw JSON, rich text as HTML, two "Status" columns**
- **Severity:** medium · **Hats:** editor
- **Actual:** `Map` is `{"url":"…","type":"url",…}`, and the entry status and the `property_status` field are both headed "Status".
- **Fix:** Export the URL and the field label ("Status (field)").

#### ED-17
**Lower-priority editor friction**
- **Severity:** low · **Hats:** editor
- **Items:**
  - Lists show "/slug" instead of the real path. `screenshots/editor-06-agents-list.jpg`
  - Language badges ("ID", outlined, "ID ·!") explain themselves only in a `title` tooltip. `screenshots/editor-92-list-outdated-badges.jpg`
  - Prices are raw numbers (`18500000000`) with no formatted echo.
  - Selects offer "None" twice, or "None" next to a default.
  - "Used by" shows raw keys (`sections.2.values.properties`). `screenshots/editor-67-translation-form.jpg`
  - Testimonials (no URL) offer Preview, and draft previews have no "preview" ribbon. `screenshots/editor-116-preview-23.jpg`, `screenshots/editor-116-preview-26.jpg`
  - View-only lock mode has no "Take over" button, and a lock stayed after a closed browser. `screenshots/editor-111-lock-view-only.jpg`
  - Rich-text links get `rel="nofollow"` even when internal, and the dialog can't pick an entry.
  - Media search needs Enter; entry search doesn't.
  - Comment replies can only be posted under the editor's own name.
  - Submissions have no handled/unread state or assignee; the list lacks topic and message.
  - Listing pages and globals go live on Save (no draft, preview or revisions), unlike entries.
- **Fix:** Address individually; the rich-text `nofollow` and the badges matter most.

#### ED-18
**Polish: editor UI**
- **Severity:** polish · **Hats:** editor
- **Items:**
  - The nested term select shows "— — Kemang" as the chosen value.
  - The Columns menu closes after each toggle.
  - The filter label "Property types: a…" is truncated at 1440 px. `screenshots/editor-19-properties-list.jpg`
  - Duplicate keeps the `-copy` slug after the title is renamed.
  - The sidebar keeps the Dashboard highlighted after cancelling the native leave confirm, and that warning is a browser `confirm()`. `screenshots/editor-22-after-ctrl-s.jpg`
  - Media picker selection is only a thin border. `screenshots/editor-129-media-picker-selected.jpg`
  - "Shared by all languages: edit it in English." is repeated under every read-only field.

### 3.5 Site rendering & templates API

#### SITE-1
**`hide_empty` on a hierarchical terms filter hides parents, and their value is then ignored**
- **Severity:** medium · **Hats:** developer
- **Where:** `<x-sunrice-cms::entry-filter :filters="['location' => ['type' => 'terms', 'taxonomy' => 'locations', 'hide_empty' => true]]">`
- **Steps:** Put properties only in Canggu, Seminyak and Kemang, then visit `/properties?location=bali`.
- **Expected:** Bali is offered with child-inclusive counts, and filters to everything under it.
- **Actual:** Bali, Badung and Jakarta are dropped (count 0). `?location=bali` is "not among the options" and silently ignored, so the page shows everything. Children keep `depth` 2 under missing parents.
- **Fix:** Count including descendants when `includeChildren` is set; never drop ancestors of non-empty terms.

#### SITE-2
**Menu active state: no trail for entries under a listing; both filter links "current"**
- **Severity:** medium · **Hats:** frontend
- **Where:** `/properties/*`, `/journal/*`, `/team/*`, `/neighbourhoods/*`, `/properties`
- **Actual:**
  - Detail pages get no `aria-current` or trail.
  - On `/properties`, Properties, For sale and For rent are all `aria-current="page"`, because matching ignores the query string (documented in menus.md).
  - Pages under Services don't light up Services.
- **Fix:** Mark a listing item as the trail for its collection's entries; compare the query string when the item has one.

#### SITE-3
**SEO and JSON-LD rough edges**
- **Severity:** low · **Hats:** frontend (119 URLs crawled)
- **CMS-side:**
  - The 404 page has a canonical to the missing URL and no `noindex`.
  - Paginated listings repeat page 1's `<title>`, and "previous" points to `?page=1`.
  - Hidden pages get `noindex, nofollow` (only noindex was asked for).
  - BlogPosting `author` is the Organization although the post has an author agent.
  - The WebSite `@id` is the same across languages with different `url`s.
  - Trailing-slash and `/index.php` variants answer 200.
  - Term and listing URLs in `sitemap.xml` have no hreflang alternates.
- **App-side (not Sunrice):**
  - `knowsLanguage` uses raw select values.
  - Rental Offers have no unit, and rented/sold listings still say "Asking price".
  - `robots.txt` has no `Sitemap:` line.
  - `og:image` is empty (no photos yet).
- **Fix:** Address the CMS-side items in the seo component, router and sitemap.

#### SITE-4
**Fonts downloaded twice (woff2 preloaded, woff used)**
- **Severity:** medium · **Hats:** frontend · **(app, not Sunrice)**
- **Where:** `build/assets/fonts-*.css`
- **Actual:** Each face is declared twice (woff2, then woff, with the same descriptors), so the later rule wins. The six preloaded woff2 files (88 KB) go unused while seven woff files (169 KB) are used: 257 KB on a first visit, about 2/3 of the page weight without images.
- **Fix:** One `src: url(woff2) format("woff2"), url(woff) format("woff")` per face, or woff2 only.

#### SITE-5
**Visual polish on the site templates**
- **Severity:** polish · **Hats:** frontend · **(app, not Sunrice)**
- **Items:**
  - The footer prints "© 2026 PT Lumina Estates Indonesia" twice.
  - The 404 numerals overlap the heading, and "This page has moved on" suggests a move. `screenshots/frontend-12-404.jpg`
  - Rented and sold properties show "Asking price" and the viewing form. `screenshots/frontend-16-property-rented.jpg`
  - Full prices are long on cards ("IDR 95,000,000,000"); "IDR 95 bn" would read better.
  - The newsletter copy says "quarterly" above "one email a month".
- **Screenshot:** `screenshots/frontend-01-home.jpg`

### 3.6 Multilingual

#### I18N-1
**Form labels, options and validation messages stay English on Indonesian pages**
- **Severity:** high · **Hats:** developer, frontend
- **Where:** `/id/sell-with-us` (auto-rendered `<x-sunrice-cms::form handle="valuation" />`); `/id/kontak` (custom markup)
- **Steps:** 1. Open `/id/sell-with-us`. 2. On `/id/kontak`, submit with an empty name and an invalid email.
- **Expected:** Indonesian labels, options and errors.
- **Actual:**
  - The auto-rendered form shows "Name", "Email", "Property type", "House/Villa/…", because blueprint labels, instructions and options have one language.
  - With custom translated markup, the field errors still read "The Name field is required." under "NAMA ANDA", because validation attributes come from the blueprint's English labels (and the app has no `lang/id/validation.php`).
- **Screenshots:** [bug-id-form-english-errors](report-images/bug-id-form-english-errors.jpg), `screenshots/frontend-20-contact-invalid-id-form.jpg`
- **Fix:** Translatable labels, instructions and option labels on fields, used both for rendering and as validation attribute names.

#### I18N-2
**Indonesian listing, term and search pages hide every untranslated entry, although the fallback serves them**
- **Severity:** high · **Hats:** frontend
- **Where:**
  - `/id/properties`: 6 properties vs 15.
  - `/id/property-type/vila`: 2 vs 4.
  - `/id/location/seminyak`: empty.
  - `/id/search?q=villa`: 1 result vs 4.
- **Steps:** Compare `/properties` with `/id/properties`, then open `/id/properties/signature-apartment-kebayoran-baru`: it works, with the fallback note.
- **Expected:** With `site.fallback` on, listings, terms, search and filter counts include the fallback entries (or an option to).
- **Actual:** Only translated entries are listed, searched and counted ("Dijual (4)"). An Indonesian visitor sees a 6-property agency.
- **Screenshots:** [bug-id-listing-only-translated](report-images/bug-id-listing-only-translated.jpg), `screenshots/frontend-57-id-properties-only-translated.jpg`, [site-property-id-fallback](report-images/site-property-id-fallback.jpg)
- **Fix:** Make listing, term and search queries fallback-aware (one row per entry: the locale's translation, else the main one).

#### I18N-3
**Menu filter links and filter URLs don't work across languages; listing filter parameters undocumented**
- **Severity:** high (frontend; admin rated the authoring side low) · **Hats:** frontend, admin
- **Where:** Main menu on `/id` ("Dijual" → `/properties?listing_type=for-sale`); `/id/properties?listing_type=for-rent`
- **Actual:**
  - The menu link lands on the English site.
  - On `/id/properties`, the English slug is silently ignored because term slugs are per language (`dijual`), so all 6 show with no chip.
  - The language switcher drops filters.
  - The admin had to type `/properties?listing_type=…` as a plain link: there's no "listing + filter" link type, and neither the Guide nor the docs list the query parameters.
- **Screenshot:** `screenshots/frontend-58-id-menu-for-sale-goes-english.jpg`
- **Fix:** A language-aware "Listing with filter" menu item, filters that accept the main-language slug or id in every language, and documented parameters.

#### I18N-4
**Form thank-you message is one text for all languages**
- **Severity:** medium · **Hats:** admin, frontend
- **Where:** Forms → Thank-you message; `/id/kontak` after a valid submit
- **Actual:** Indonesian visitors get "Thank you for getting in touch…".
- **Screenshots:** `screenshots/frontend-21-contact-thanks-id.jpg`
- **Fix:** Make the thank-you message translatable.

#### I18N-5
**Select fields give templates only the stored value; option labels can't be read or translated**
- **Severity:** medium · **Hats:** developer
- **Where:** property `property_status`, `price_suffix`; agent `languages`
- **Actual:** Templates get `under_offer` and `per_month`, so every label was re-declared in `lang/*/site.php`.
- **Fix:** Something like `$field->optionLabel($value, $locale)`, with translatable option labels (shares a root with I18N-1).

#### I18N-6
**Collection and taxonomy titles are single-language: listing `<title>` and the CMS BreadcrumbList stay English on /id**
- **Severity:** medium · **Hats:** developer
- **Where:** `/id/properties`, `/id/journal`, `/id/team`
- **Actual:** `<title>Properties | Lumina Estates</title>`, and the JSON-LD BreadcrumbList has `"Home"`, `"Properties"`. "Home" isn't a translation key. Templates can work around the heading, but not the JSON-LD.
- **Fix:** Translatable collection and taxonomy titles; put "Home" in `sunrice-cms::site.*`.

#### I18N-7
**Translations of dated entries get their own publish date**
- **Severity:** medium · **Hats:** frontend
- **Where:** `/id/journal`
- **Actual:** Every Indonesian post reads "20 Oktober 2026" (the day it was translated), while the English originals run 7 Oct, 28 Sep…. The order and `datePublished` differ per language.
- **Fix:** Share the entry's date across translations, or default to it.

#### I18N-8
**Indonesian globals start empty, with no fallback note**
- **Severity:** medium · **Hats:** editor
- **Where:** Globals → Company → Indonesian
- **Actual:** Tagline, address, office hours and the whole Primary contact group are empty, and nothing says whether the site falls back to English. Partly a modelling choice (the contact fields were made translatable).
- **Screenshots:** [bug-id-globals-empty](report-images/bug-id-globals-empty.jpg), `screenshots/editor-73-company-id.jpg`
- **Fix:** Copy the main language on first open (as entries do), or show "Uses the English value".

#### I18N-9
**Image alt text and caption are single-language**
- **Severity:** medium · **Hats:** editor
- **Where:** Media → image detail
- **Screenshot:** `screenshots/editor-121-media-detail.jpg`
- **Fix:** Alt text and caption per language (or per use).

#### I18N-10
**Term pages: no description field; SEO blocked until the term has a title in that language**
- **Severity:** medium · **Hats:** editor
- **Where:** Terms → Property types → Villa
- **Actual:** There's nowhere to write a term intro. In Indonesian the form hides behind "give it a title in the list first", so 37 terms had to be renamed one by one.
- **Screenshots:** `screenshots/editor-86-term-villa-edit.jpg`, `screenshots/editor-88-terms-indonesian-list.jpg`
- **Fix:** Edit the title on the term page itself; offer an optional description field.

#### I18N-11
**Docs' breadcrumb example links Indonesian visitors to English pages**
- **Severity:** medium · **Hats:** developer
- **Where:** `site.md` → Filtering entries (`$ancestor->mainTranslation()?->url()`)
- **Actual:** It links to `/about`. The right call, `Sunrice\Cms\Site\Site::urlIn($translation, $locale)`, is @api but undocumented.
- **Fix:** Correct the example and document `Site::urlIn()`.

#### I18N-12
**Lower-priority multilingual gaps**
- **Severity:** low · **Hats:** developer, frontend, editor
- **Items:**
  - CMS visitor words without Indonesian lines: the entry-filter component (Filter, All, Sort by, Show results…) and the draft banner use bare `__()` keys, and `resources/lang-core/id.json` has three lines (developer).
  - Links from /id pages to untranslated entries (agent cards, `offeredBy.url`) go to the English URL (frontend).
  - The site description is single-language, and the same generic description sits on ~70 agent, term and topic pages (frontend).
  - `/id/about` is 404 (slug `tentang-kami`); the main-language slug could redirect (frontend).
  - Comment and submission times are UTC for a WIB (UTC+7) company, and no setting or doc mentions it (frontend).
  - "Bahasa Indonesia" vs "Indonesian" naming varies by screen (editor).
  - Without a translation driver, nothing hints that machine translation exists (editor).
- **Fix:** Address individually; the fallback links and the CMS lang lines are cheap.

#### I18N-13
**`og:locale` is "id"/"en" instead of "id_ID"/"en_US"**
- **Severity:** polish · **Hats:** developer
- **Where:** `<x-sunrice-cms::seo>`
- **Fix:** Map locales to `language_TERRITORY`.

### 3.7 Forms & comments

#### FORM-1
**The honeypot timing check is void on kept pages**
- **Severity:** medium · **Hats:** frontend (security relevance)
- **Where:** `/contact` (kept), `POST /forms/contact`; `cms/src/Site/RequireHoneypot.php`
- **Steps:** GET `/contact` (a HIT rendered minutes ago), then POST immediately with curl, using the page's `_token`, `my_name_*` and `valid_from`.
- **Expected:** A too-fast submit is refused.
- **Actual:** Accepted (302, email sent). `valid_from` is the render time, and the honeypot field name is the same for everyone until the page re-renders. The CSRF token is swapped per visitor; the honeypot fields aren't.
- **Fix:** Swap the honeypot fields per visitor like CSRF, or skip the timing check on kept pages and document it.

#### FORM-2
**After a form, the visitor lands at the top of the page and focus goes to `<body>`**
- **Severity:** medium · **Hats:** frontend (also a11y)
- **Where:** Footer newsletter (every page), contact, inquiry, valuation
- **Actual:** The redirect goes to the bare URL, so the message is ~4,000 px below the fold. Comments do this right (`#comments`).
- **Screenshot:** `screenshots/frontend-26-newsletter-en-ok.jpg` (scrolled for the shot)
- **Fix:** Redirect to `#form-{handle}` and move focus to the message or error summary.

#### FORM-3
**Rate limit counts failed tries, then shows a bare white 429 page**
- **Severity:** medium · **Hats:** frontend
- **Where:** `POST /forms/contact` (5/min), comments (3/min)
- **Actual:** The 6th try within a minute, failed validation included, gets Laravel's unstyled "429 | Too Many Requests", and the typed message is lost. The limit is per IP, so an office NAT shares it.
- **Screenshots:** [bug-rate-limit-bare-429](report-images/bug-rate-limit-bare-429.jpg), `screenshots/frontend-23-rate-limit.jpg`
- **Fix:** Don't count validation failures; flash a friendly error back to the form with the input kept.

#### FORM-4
**Uploads over PHP's limit fail with "The Photos failed to upload."**
- **Severity:** low · **Hats:** frontend
- **Where:** `/sell-with-us`, 3.4 MB JPG (rule 5 MB; PHP `upload_max_filesize` 2 MB here)
- **Screenshots:** `screenshots/frontend-25-valuation-mid3-jpg.jpg`, `screenshots/frontend-25-valuation-big6-jpg.jpg`
- **Fix:** Detect `UPLOAD_ERR_INI_SIZE` and say the limit; show the accepted types and size on the form.

#### FORM-5
**Comment unsubscribe page is unbranded and unsubscribes on GET**
- **Severity:** low · **Hats:** frontend
- **Actual:** Mail scanners that prefetch links will unsubscribe people.
- **Screenshot:** `screenshots/frontend-37-unsubscribe.jpg`
- **Fix:** Confirm with a POST button; use a site-overridable view.

#### FORM-6
**Polish: forms and comments**
- **Severity:** polish · **Hats:** frontend
- **Items:**
  - The notification email has no admin link, no page or language, shows only the uploaded file's name, and lists empty fields.
  - The inquiry's preferred date accepts the past (2020-01-01).
  - A post with no comments is headed "0 comments" instead of "Leave a comment".
  - Each held comment sends its own moderator email (3 in a minute).

### 3.8 Caching & performance

#### CACHE-1
**Comment reply links don't work with the page cache, and one visit can poison the kept page for everyone**
- **Severity:** high · **Hats:** frontend
- **Where:** Journal posts, `?reply_to=N`; `cms/src/View/Components/Comments.php`, `cms/src/Site/PageCacheHasher.php`
- **Steps:** 1. As a guest, click Reply (`?reply_to=1#comment-form`): a HIT with no "Replying to…". 2. When a post isn't kept yet, open it with `?reply_to=1` first, then without.
- **Expected:** `reply_to` varies the cache key, or such requests skip the cache (as `?draft` and previews do).
- **Actual:**
  - Step 1: Reply silently does nothing.
  - Step 2: the plain URL serves `<input name="parent_id" value="1">` and "Replying to Sophie Laurent · Cancel" to everyone until the next change or the TTL, so every new comment becomes a reply to comment 1.
- **Workaround:** Add `reply_to` to `sunrice-cms.cache.query`.
- **Fix:** The CMS should skip the cache for (or vary on) its own `reply_to` parameter.

#### CACHE-2
**Listing, term and search pages hand templates entries that aren't hydrated together (N+1 per card)**
- **Severity:** high · **Hats:** developer
- **Where:** `$entries` in `cms.{collection}-listing` and `cms.taxonomy-*`, `$results` in `cms.search`
- **Actual:** ~5 queries per card when a card calls `values()`: journal 61, topic 69, search 45 (the summary says "search 107 on real data"; see CACHE-3). With `Cms::entries(...)->paginate()` instead: 32 and 34. The docs' budgets (5/6/7) hold only for templates that never call `values()`.
- **Workaround:** Every template re-queries (4 wasted queries for the CMS's unused paginate).
- **Fix:** Give the page's paginator one shared `HydrationContext`, as `Cms::entries()` does.

#### CACHE-3
**An entries field's chosen entries hydrate one by one: 121 queries on the real homepage**
- **Severity:** high · **Hats:** developer
- **Where:** `/` (featured_properties, team, testimonials blocks), `/journal/{slug}` (`related`), `/search`
- **Actual:** Each linked entry's `values()` uses a fresh context: home 121, post 91, search 107 (live MySQL, warm). With the app helper `App\Support\Hydrate::together()` (shared context, `Schema::references()` + `HydrationContext::preload()`): home 38, post 46, search 28.
- **Fix:** An `Items` of linked entries (and search and listing paginators) should share one context and preload references.

#### CACHE-4
**Kept pages outlive a front-end deploy and link deleted asset files**
- **Severity:** medium · **Hats:** developer
- **Where:** `SUNRICE_PAGE_CACHE=true`; caching.md ("no clearing needed"); installation.md → Upgrading
- **Steps:** Visit a page (kept), run `npm run build`, then visit again as a guest.
- **Actual:**
  - A HIT with `<link href="/build/assets/app-B8_fWhSV.css">` (404), so the page is unstyled for up to `cache.ttl` (1 h).
  - New Blade doesn't show either.
  - `view:clear` doesn't help.
- **Screenshots:** [bug-stale-page-cache-unstyled](report-images/bug-stale-page-cache-unstyled.jpg), `screenshots/developer-01-stale-page-cache.jpg`
- **Fix:** Include the Vite manifest hash and a view/deploy version in the content version, or document `responsecache:clear` in the deploy steps.

#### CACHE-5
**Any value of `page`, `q` or a `cache.query` parameter makes a new kept page, on every page**
- **Severity:** medium · **Hats:** frontend (security: store flooding)
- **Where:** `/about?page=7771`, `/?q=x`, `/properties?bedrooms_min=<random>`
- **Actual:**
  - MISS then HIT for every new value, even where the page doesn't paginate or search.
  - Invalid filter values (`bedrooms_min=abc`) are ignored by the filter but still kept.
  - Each costs a full render (~250 ms) plus a stored copy.
  - caching.md promises "nobody can fill the store with copies of one page". `utm_*`, `fbclid` and `?PAGE=2` are correctly ignored.
- **Fix:** Vary on `page`/`q` only where the page reads them, and only for valid values.

#### CACHE-6
**The entry page's own `$values` load linked records one field at a time**
- **Severity:** medium · **Hats:** developer
- **Where:** `/properties/villa-samudra`
- **Actual:**
  - 2 queries per terms field and 3 per entries field, then `$agent->values()` +4, `Term::url()` +2 and `$term->parent->title()` +2.
  - The property page takes 56 queries warm (45 in Indonesian).
  - fields.md promises "one query per kind".
- **Fix:** Batch by kind across fields; document `HydrationContext` for templates.

#### CACHE-7
**Listing and term pages always run their own entries query**
- **Severity:** low · **Hats:** developer
- **Actual:** The CMS paginates `$entries` (count, select and 2 eager loads) even when the template uses the filter component or its own query. A term page's `$entries` excludes children, so a location page needs its own query anyway.
- **Fix:** Load `$entries` lazily, or let a view declare that it queries itself.

#### CACHE-8
**Cached pages still cost a framework boot and a session row**
- **Severity:** low · **Hats:** frontend (corrects developer's "0 queries")
- **Actual:**
  - Every cookieless hit writes a DB session row (594 → 604 for 10 hits).
  - Every hit sets two cookies and sends `Cache-Control: no-cache, private`.
  - HIT TTFB is ~150 ms vs 130 ms for `/up`.
- **Fix:** Document it; consider not starting a session for kept GETs until a form needs one.

#### CACHE-9
**Admin screens take about 1.1–2.2 s**
- **Severity:** low · **Hats:** editor
- **Actual:** `/admin` 1.5 s, lists 1.2 s, Home editor 2.2 s, Create draft ~1.5 s (Playwright to network idle). Partly the blocked font CDN (ADM-3).
- **Fix:** Profile once ADM-3 is fixed.

#### CACHE-10
**Every page pays ~8 queries for globals and the newsletter form**
- **Severity:** low · **Hats:** developer
- **Actual:** Global values, each global's blueprint by handle, all fieldsets, and the form and its blueprint. None of it is kept in the site cache.
- **Fix:** Cache globals and form definitions with the site cache version.

### 3.9 Security

#### SEC-1
**Password-reset link uses the attacker-controlled Host header**
- **Severity:** high · **Hats:** security
- **Where:** `admin/src/Notifications/ResetPanelPassword.php:36` (`url(route('sunrice.admin.password.reset', …, false))`)
- **Steps:** `POST /admin/forgot-password` with `{"email":"dewi@…"}` and `Host: evil.example`.
- **Expected:** Links are built from `APP_URL`.
- **Actual:** The queued mail contains `http://evil.example/admin/reset-password/<token>?email=…`. A victim who clicks hands the token to the attacker. The app has no `TrustHosts`. The 2FA code and security-alert mails use `route()` too.
- **Fix:** Build notification URLs from `config('app.url')` (e.g. `URL::forceRootUrl`); document `TrustHosts`.

#### SEC-2
**Login form is a password oracle for accounts without panel access**
- **Severity:** high (practically medium, per security) · **Hats:** security
- **Where:** `admin/src/Actions/Auth/AttemptLogin.php:80-86`
- **Actual:** A right password on a non-panel account gets "This account can't use the admin panel."; a wrong one gets "These credentials do not match our records." This confirms both the account and its password (e.g. an app's customers sharing the users table).
- **Screenshots:** [bug-login-password-oracle](report-images/bug-login-password-oracle.jpg), `screenshots/security-02-password-oracle-no-access.jpg`
- **Fix:** Return the generic `auth.failed` message and log the real reason.

#### SEC-3
**Translator can change shared, structural entry settings and reorder entries**
- **Severity:** medium · **Hats:** security
- **Where:** `cms/src/Http/Controllers/EntryManageController.php:86-114`; `cms/src/Support/Access.php:39-59`
- **Steps (as Dewi):**
  - `PUT /admin/cms/entries/30/settings {"locale":"id","comments_closed":true}` → 303, the flag flips.
  - `{"locale":"xx","parent_id":38}` on entry 37 → 303, the parent changes.
  - `POST …/38/move {"locale":"id","direction":"up"}` → 302.
- **Expected:** A translator edits only the translatable text of non-main languages.
- **Actual:** The locale escape hatch passes the gate. `template` and `blueprint` are re-checked (403), but `parent_id`, `comments_closed`, move and reorder are not.
- **Fix:** Require collection-scope `Access::entries($user,'update',$collection)` for those fields and actions, as `template` already does (`EntryManageController.php:97`).

#### SEC-4
**Inertia DevTools recorder is open to anyone in `local`**
- **Severity:** medium (dev install; would be high in production) · **Hats:** security
- **Where:** `vendor/inertiajs/inertia-laravel/src/DevTools/Http/Authorize.php:24-27`; `/_inertia/devtools/entries`
- **Actual:**
  - Unauthenticated, it returns 4,000+ recorded requests, with every page's props (users and emails, dashboard).
  - Cookies and the one-time token are redacted.
  - `cms_render_page` can also fetch it with a read-only MCP token.
- **Screenshot:** `screenshots/security-01-inertia-devtools-open.jpg`
- **Fix:** Disable DevTools for the panel by default (`inertia.devtools.enabled=false`, or exclude the panel path); refuse `/_inertia/*` in `cms_render_page`.

#### SEC-5
**`APP_DEBUG=true` leaks traces and paths; `X-Powered-By` sent**
- **Severity:** medium · **Hats:** security · **(app config, not Sunrice)**
- **Actual:**
  - Front-of-site errors show traces, and `X-Powered-By: PHP/8.4.28-dev` is sent everywhere.
  - Panel errors are clean regardless, thanks to its own handler.
- **Fix:** Ship `.env.example` with `APP_DEBUG=false`; `expose_php=Off`; optionally a CMS front-end error view.

#### SEC-6
**Two-factor login can be turned on while mail is the `log` mailer**
- **Severity:** medium · **Hats:** admin
- **Where:** Settings → Security
- **Actual:** It saves silently although the Mail page already says "Nobody receives login codes". On a real site everyone is locked out, and the way back is `php artisan sunrice:two-factor off`.
- **Fix:** Refuse or confirm when the mailer can't deliver; require a successful test email first.

#### SEC-7
**Built-in author role can create in every collection; no per-collection "own entries" permissions**
- **Severity:** medium · **Hats:** admin
- **Where:** Roles → author
- **Actual:**
  - The seeded author has global View and Create entries.
  - There are per-collection View and Create, but no per-collection "edit or trash own".
  - The admin edited the built-in role, and nothing says whether a later update resets it.
- **Screenshot:** `screenshots/admin-70-role-author-scoped.jpg`
- **Fix:** Per-collection own-entry permissions; document whether seeded roles are overwritten.

#### SEC-8
**Media library accepts a text file named `.jpg`**
- **Severity:** medium (editor; security found no dangerous variant accepted) · **Hats:** editor, security
- **Where:** Media → Upload
- **Actual:**
  - The editor's `qa-test-fake.jpg` (12 bytes of text) was stored as a generic document, and image pickers leave it out.
  - The `accept` list includes `.svg`, `.json`, `.zip` and Office files for everyone with media access.
  - The security tester confirmed `.php`, polyglots, HTML-in-.jpg and SVG with scripts are rejected.
- **Screenshot:** `screenshots/editor-120-media-huge-upload.jpg`
- **Fix:** Refuse files whose content doesn't match their extension ("This isn't a valid image").

#### SEC-9
**Forgot-password has no per-IP rate limit or alert**
- **Severity:** low · **Hats:** security
- **Where:** `routes/admin.php` (`throttle:sunrice-password`)
- **Fix:** Add a per-IP limiter like login's.

#### SEC-10
**Editor role can create and delete whole menus**
- **Severity:** low · **Hats:** admin
- **Actual:** The seeded editor has `cms.menus.create` and `cms.menus.delete`, so Budi can delete the `main` menu the templates rely on. This contradicts the Guide.
- **Screenshot:** `screenshots/admin-72-role-budi-dashboard.jpg`
- **Fix:** Remove both from the seeded editor role.

#### SEC-11
**Login code in the email subject**
- **Severity:** low · **Hats:** admin
- **Actual:** "Your Lumina Estates login code: 529709" shows on lock screens.
- **Fix:** Add a config option to keep the code out of the subject.

#### SEC-12
**Valuation form accepts a JPEG-signature file followed by PHP code**
- **Severity:** low · **Hats:** frontend (stored on the private disk; security confirmed downloads are `octet-stream` + `nosniff`)
- **Screenshot:** `screenshots/frontend-25-valuation-polyglot-jpg.jpg`
- **Fix:** Re-encode images or scan for `<?php` in form uploads.

### 3.10 AI access

#### AI-1
**`cms_render_page` renders with host `localhost` and returns framework paths in `error`**
- **Severity:** low · **Hats:** frontend, security (L2)
- **Where:** `cms/src/Ai/RenderPage.php:68`
- **Actual:** Canonical and links come out as `http://localhost/id/kontak` although `APP_URL` is `http://127.0.0.1:8200`. A 404 returns `" in vendor/laravel/framework/src/Illuminate/Foundation/Application.php:1445"`.
- **Fix:** Render with `APP_URL` as the root; return a clean message.

#### AI-2
**The AI access page doesn't say what "full" means for this account**
- **Severity:** low · **Hats:** admin
- **Actual:**
  - For a super admin, a full token can change users, roles and Site code, run commands, and delete.
  - `cms_write_template` is advertised although `SUNRICE_AI_TEMPLATES` is off; it refuses when called.
- **Screenshot:** `screenshots/admin-79-ai-tokens-list.jpg`
- **Fix:** List this account's risky abilities in the dialog; hide tools that are switched off.

#### AI-3
**Read-only tokens can read every form submission (personal data)**
- **Severity:** low · **Hats:** frontend
- **Fix:** Say so on the AI access page, or add a "no personal data" scope.

#### AI-4
**`cms_seo_audit` judges the entry title, not the rendered `<title>`**
- **Severity:** low · **Hats:** frontend
- **Actual:** "Home: meta title is short (4 characters)" although the page title is "Lumina Estates". Contact, which has a form, is "thin content".
- **Fix:** Audit the rendered head.

#### AI-5
**Small MCP rough edges**
- **Severity:** low · **Hats:** frontend
- **Actual:**
  - `cms_reorder` accepts a partial `ids` list and logs "reordered" even when nothing changed.
  - An empty single entries field (`[]`) is refused ("must be an integer"), and `null` is undocumented.
- **Fix:** Validate complete lists; accept `[]`/`null` to clear.

### 3.11 Accessibility

#### A11Y-1
**Filter selects submit on every arrow key**
- **Severity:** medium · **Hats:** frontend · **(app template, `data-autosubmit`)**
- **Where:** `/properties`
- **Actual:**
  - The first ArrowDown reloads the page (WCAG 3.2.2), and focus returns to the top.
  - Reaching "For rent" takes two reloads.
  - The URL fills with empty parameters.
- **Screenshot:** `screenshots/frontend-52-filter-keyboard.jpg`
- **Fix:** Submit on the button, or on change only for pointer input; drop empty parameters.

#### A11Y-2
**Two nested `<main>` landmarks on every admin page**
- **Severity:** low · **Hats:** admin
- **Where:** `main[data-slot=sidebar-inset]` wraps `main.sunrice-page-enter`
- **Fix:** Make the inner one a `div`.

#### A11Y-3
**Mobile menu doesn't keep focus inside**
- **Severity:** low · **Hats:** frontend · **(app template)**
- **Actual:** Tab past the last link moves into the page behind. Esc and focus return work.
- **Fix:** Trap focus, or make the page `inert` while the menu is open.

#### A11Y-4
**Fallback content isn't marked as English**
- **Severity:** low · **Hats:** frontend
- **Where:** `/id/properties/signature-apartment-kebayoran-baru`
- **Actual:** The page is `<html lang="id">` around English body text, and `og:locale` says `en`.
- **Fix:** Expose the fallback locale to templates (or wrap fallback content in `lang`).

#### A11Y-5
**Heading jumps and an unlabelled link on the site**
- **Severity:** low · **Hats:** frontend · **(app template)**
- **Actual:** Listing and term pages go H1 → H3. The agent avatar link has no text (`tabindex="-1"` but not `aria-hidden`).
- **Fix:** Add an H2 for the results; hide the avatar link from assistive tech.

#### A11Y-6
**Admin drag-and-drop announcements and labels**
- **Severity:** polish · **Hats:** editor
- **Actual:** dnd-kit's default announcements read "Draggable item 47 was dropped over droppable area 36". The Map link field's input is labelled "Address" right after the property's own Address field.
- **Fix:** Announce titles ("Home moved above About"); label link inputs with their field name.

## 4. Performance

Query counts are from the developer's findings; timings are from the frontend tester's (guest, page cache on, `php artisan serve` without OPcache).
- MISS is the first render of a page (site cache warm).
- HIT is a kept page (median of 3).
- Requests and transfer are from a cold browser load. No images exist yet, so weights will grow after the photo import.

| Page | Queries, test fixture¹ | Queries, live MySQL² | Before app hydration fix³ | MISS TTFB | HIT TTFB | HTML KB | Requests | Transfer KB |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Home `/` | 43 | 38 | 121 | 274 ms | 180 ms | 82 | 17 | 390 |
| Home `/id` | – | – | – | 247 ms | 153 ms | 82 | 17 | 390 |
| About / page | 18 | 17 | – | 240 ms | 164 ms | 55 | 15 | 300 |
| Our story (child page) | 19 | – | – | 236 ms | 178 ms | 54 | 15 | 299 |
| Contact | 16 | 15 | – | 251 ms | 163 ms | 64 | 15 | 309 |
| Sell with us | 16 | – | – | 263 ms | 182 ms | 59 | 15 | 304 |
| Properties listing | 39 | 37 | – | 719 ms | 383 ms | 83 | 16 | 358 |
| Properties, filtered | 45 | 43 | – | 303 ms | 156 ms | 73 | 16 | ~350 |
| Property | 56 | 52 | – | 294 ms | 147 ms | 79 | 16 | 355 |
| Property (id) | 45 | – | – | 261 ms | 153 ms | 77 | 16 | 353 |
| Team | 20 | 18 | – | 235 ms | 149 ms | 56 | 15 | 301 |
| Agent | 26 | 25 | – | 211 ms | 137 ms | 70 | 16 | 346 |
| Neighbourhoods | – | – | – | 213 ms | 166 ms | 58 | 15 | 303 |
| Neighbourhood | 23 | 22 | – | 252 ms | 150 ms | 64 | 16 | 340 |
| Journal listing | 32 | 36 | 61 | 251 ms | 184 ms | 63 | 15 | 308 |
| Journal post | 56 | 46 | 91 | 285 ms | 152 ms | 69 | 17 | 377 |
| Topic term | 34 | 39 | 69 | 295 ms | 408 ms⁴ | 57 | 15 | 302 |
| Location term (Bali) | 35 | 36 | – | 414 ms | 171 ms | 70 | 16 | 345 |
| Property type (Villa) | – | – | – | 229 ms | 173 ms | 64 | 16 | 340 |
| Search `?q=villa` | 45 | 28 | 107 | 248 ms | never kept (~237 ms) | 60 | 15 | 305 |
| 404 | – | – | – | 221 ms | never kept (~200 ms) | 52 | 14 | 279 |

1. `tests/Feature/Site/QueryBudgetTest.php` (3 properties, 2 agents, 7 posts, warm site cache), constant in the number of cards because the templates use the app's workarounds.
2. The editor's real content (16 properties, 6 posts), warm cache, page cache off, after the app-level hydration fixes. All 20–80 ms of query time.
3. Without the app workarounds (CACHE-2/3). The developer's top-10 summary calls the 61/69/107 figures "real data". In the body, 61 (journal) and 69 (topic) are from the listing section, and 107 (search), 121 (home) and 91 (post) are from the live MySQL entries-field section. The listing section also gives "search with 3 results: 45", which matches the fixture column.
4. Noisy: other agents were loading the server.

**Baselines:**
- Laravel `/up` takes 130 ms; a static file 1–2 ms.
- DOMContentLoaded/load takes 200–600 ms on localhost.
- CLS is 0 on all 119 crawled pages.
- Guests' repeat visits with the page cache on run 0 CMS queries, but still write one session row (CACHE-8).
- Fonts are 257 KB of each first visit (SITE-4, app-side).
- Admin screens take 1.1–2.2 s (CACHE-9).

## 5. What worked well

**Admin and structure**
- Calm, consistent UI with good empty states, friendly 403/404 pages and honest toasts.
- Nested terms with an "Under" picker.
- Redirects, listing and term routes work the moment they're saved.
- The Mail page tells the truth about the log mailer, and the test email works.
- The Guide (with an Indonesian version) is readable by non-developers.

**Permissions and security**
- Scoped per-collection, per-taxonomy and per-form permissions are enforced server-side across every route and role. Refused pages are logged and alerted.
- Privilege escalation is blocked (`PermissionRank`).
- No data leaks in Inertia props.
- CSRF everywhere.
- The upload blocklist plus content sniffing plus the SVG script scan held, and form uploads go to a private disk.
- SSRF-safe URL import.
- Allow-listed sorting (no SQLi).
- Rich text is sanitised, and JSON-LD is escaped.
- Session rotation, and "sign out everywhere" also kills remember-me cookies and AI tokens.
- Email 2FA with a masked address, resend timer and lockout.
- Security alert emails.

**Editing**
- Drafts on top of published content, and Ctrl+S saves.
- Restoring a revision goes into the draft, with "Undo the restore".
- Excellent edit locks (take over keeps the other person's edits for "Load into the form").
- Scheduled publishing fires on time.
- Duplicate copies everything into a draft.
- Bulk actions and taxonomy filters live in the URL, and column and sort choices are remembered.
- "Used by" everywhere (entries, trash confirmations, media).
- Keyboard-accessible drag-and-drop.
- The rich text editor has Markdown shortcuts and clean paste.
- Comment moderation and submission details are straightforward.
- SEO cards with plain-language help, and noindex really applied.

**Translations**
- Adding a language copies the content.
- Shared fields are read-only with an explanation.
- The Outdated chip appears when the main language changes and clears when the translation is saved.
- Indonesian slugs work at once.
- The site's fallback for untranslated entries shows a clear note, with canonical to the main page and hreflang.
- The language switcher lands on the translated page.

**Developer API**
- Predictable template resolution, and honest starter templates.
- `<x-sunrice-cms::entry-filter>` with a slot gives full custom markup, shareable URLs, no JavaScript, and is safe against made-up values.
- `<x-sunrice-cms::form>` with a slot (`old()`, `error()`, per-form error bags), honeypot and CSRF handled, JSON responses.
- `:schema` on the seo component.
- A solid CMS graph (WebSite + SearchAction, Organization, BreadcrumbList).
- Previews and `?draft` work with no extra code.
- Structure import through the real actions.
- `Cms::entries()` results are hydrated efficiently.

**Site and caching**
- Every page type renders in both languages with titles, canonical, hreflang, og tags and valid JSON-LD.
- Sitemap with 104 URLs (Terms excluded).
- Search is safe with odd input.
- Accessible form errors (`aria-invalid`, `aria-describedby`).
- Skip link and visible focus rings; no overflow at 390 px; works with reduced motion and without JS.
- The page cache is fresh on publish and trash, never used for signed-in users, ignores `utm_*`, and swaps CSRF per visitor.
- Previews are signed, `no-store` and `noindex`.

**AI access (MCP)**
- Clear token dialog, read-only by default option, and expiry.
- Read-only is enforced by omitting the write tools.
- Writes are sanitised.
- Every action appears in the activity log as "AI agent · <token name>".
- Calls take 220–410 ms.

## 6. Appendix

- **Per-hat findings:** [admin](findings-admin.md) (includes the full setup summary and actual handles), [developer](findings-developer.md), [editor](findings-editor.md) (content inventory), [frontend](findings-frontend.md) (visitor test content created), [security](findings-security.md) (test content cleaned up). Brief: [BRIEF.md](BRIEF.md). Wanted photos: [unsplash-wanted.md](unsplash-wanted.md).
- **Screenshots:** `screenshots/` (386 PNGs, ~102 MB, named `<hat>-<nn>-<slug>.png`). The curated set is `report-images/` (40 JPEGs, 1280 px max width, quality 78, ~4 MB), described in [`report-images/index.json`](report-images/index.json). Note: `screenshots/admin-77-*.png` and `admin-78-*.png` show full AI token values; don't publish them.
- **Scripts:** [`scripts/`](scripts/). These include:
  - `admin-*.mjs` (admin setup, `admin-lib.mjs` shared);
  - `editor-*.mjs` (content entry, `editor-lib.mjs` / `editor-content.mjs`);
  - `frontend-*.mjs` (`frontend-01-crawl.mjs` crawls every sitemap URL for meta, JSON-LD, a11y and timings; `frontend-mcp.mjs` is a minimal MCP client);
  - `developer-form-cache.mjs`, `shot.mjs`, `shots.mjs`, `mobile-nav.mjs`;
  - `sec_*.py` and `security-01-shots.cjs` (security sweeps).

  App tests: `tests/Feature/Site` (including `QueryBudgetTest.php`).
- **Re-running the browser checks:**
  1. Start the site: `cd /home/user/realestate && /opt/php84/bin/php artisan serve --port=8200` (plus `queue:work` and `schedule:work` for mail and scheduling). The database is MySQL `lumina`; the super admin is `ayu@lumina-estates.test` / `Lumina!Admin2026`.
  2. Playwright is preinstalled (never run `playwright install`): `export NODE_PATH=/tmp/claude-0/-home-user/f810cfc1-6e1b-5a9c-8d44-3ce703373f7b/scratchpad/qa/node_modules`, with Chromium at `/opt/pw-browsers/chromium-1194/chrome-linux/chrome` (the scripts pass it as `executablePath`).
  3. Run a script, e.g. `node qa/scripts/frontend-01-crawl.mjs` (site crawl) or `node qa/scripts/frontend-02-shots.mjs` (desktop and mobile screenshots). Admin and editor scripts sign in through the UI and change content, so run them only against a disposable copy.
  4. Query budgets: `cd /home/user/realestate && /opt/php84/bin/php artisan test --filter=QueryBudget` (`phpunit.xml` sets `RESPONSE_CACHE_DRIVER=array`, `SUNRICE_PAGE_CACHE=false`).
  5. After the Unsplash import, re-test the gallery dialog, image weights/`srcset`/lazy loading/LCP and `og:image`.
