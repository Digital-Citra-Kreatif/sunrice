# Re-test: frontend (site, caching, forms, accessibility)

Re-tested on 2026-10-21 against http://127.0.0.1:8200 (updated packages, symlinked), as a guest (site), as Ayu (admin items) and with in-process scripts for query counts. Scripts: `qa/scripts/retest-frontend-*`. Screenshots: `qa/retest/screenshots/`.

| id | status | evidence (screenshot / what you saw) | note |
| --- | --- | --- | --- |
| SITE-1 | fixed | `SITE-1-location-bali.jpg`. `/properties?location=bali` offers every location with Bali selected. It shows 7 properties (everything under Badung and Gianyar) out of 15. | Parents are kept, and their counts include the terms under them. |
| SITE-2 | fixed | Detail pages: `/properties/*`, `/journal/*`, `/team/*` and `/neighbourhoods/*` put their listing's nav item on the trail (`is-trail`). On `/properties` only "Properties" is `aria-current`. With `?listing_type=for-sale`, only "For sale" is current and Properties is on the trail. Parent pages were checked in-process, with About and Services added to `main` inside a rolled-back transaction (`retest-frontend-trail.php`): `/our-story` puts About on the trail and `/buy` puts Services on it. | The live `main` menu has no About, Services or Contact links at the moment (see the note under the NEW findings), so parent pages can't be seen on the live site. |
| SITE-3 | partly fixed | Fixed: the 404 is `noindex` with no canonical. Page 2 titles end "(page 2)", and "previous" links to `/properties` without `?page=1`. `/terms` (noindex) and search are `noindex` only. WebSite `@id` is `/id#website` in Indonesian. `/about/`, `/index.php` and `/index.php/about` 301 to the clean URL. Sitemap term and listing URLs have hreflang alternates. App side: `knowsLanguage` uses schema.org Language names, rental Offers have a monthly `UnitPriceSpecification`, and `robots.txt` has a `Sitemap:` line. | **Left: the BlogPosting `author` is still `{"@id": "…#publisher"}`** (the Organization) on `/journal/living-in-ubud-a-year-in-the-hills`, although the post has an author agent. The CMS added an `author` attribute to `<x-sunrice-cms::seo>`, but the Lumina template (`cms/layout` / `journal.blade.php`) doesn't pass it, so it's still wrong on this site. `og:image` is still empty because there are no photos (content, as expected). |
| SITE-4 | fixed | Cold load of `/`: 7 woff2 files, 124 KB in total, no `.woff`. All 3 preloaded files are the ones used. | Was 257 KB. |
| SITE-5 | partly fixed | `SITE-5-404.jpg`: the numerals clear the heading and the text no longer says "moved". `SITE-5-menteng-…jpg`: a rented home shows "Last asking rent" and no viewing form. `SITE-5-property-cards.jpg`: cards show "IDR 2.5 billion" and "IDR 75 million/ month". | **Left on the live site:** the footer still prints "© 2026 PT Lumina Estates Indonesia" twice, and the newsletter still says "Quarterly market notes" above "One considered email a month". The fix only changed `database/content.json`, which seeds fresh installs, and the live `footer` global was never updated. Re-saving the footer global, or a content import, would finish it. |
| CACHE-1 | fixed | `CACHE-1-reply-on-kept-page.jpg`. On a kept post, Reply gives `?reply_to=1`, with no `X-Cache-Status`, `parent_id=1` and "Replying to Sophie Laurent · Cancel". After `responsecache:clear`, opening `?reply_to=1` first and then the plain URL gives MISS, then HIT, both without `parent_id`. | `?q=` and `?reply_to=` are never kept. |
| CACHE-2 | fixed | Measured in-process, site cache warm, page MISS, using a copy of the templates with the app's workarounds removed (`Hydrate::together` → `collect`, listings and the topic page read the CMS's `$entries`). Journal listing: **30** queries (was 61). Topic term: **32** (was 69). Search `?q=villa`: **26** (was 107/45). | It's the same count with or without the app's workarounds, so the CMS's own `$entries`/`$results` hydrate together. |
| CACHE-3 | fixed | Same method. Home: **21** queries (was 121, 38 with the app helper). Journal post: **33** (was 91; 41 *with* the app helper, which now costs extra). Search: **26** (was 107). | `App\Support\Hydrate::together()` can be removed from the templates. |
| CACHE-4 | fixed | `retest-frontend-cache4.php` simulates a new build by pointing Vite at a manifest with one changed file name. The suffix changes (`v63-1057827cc770` → `v63-862ead2b1b13`) and the kept page is re-rendered: MISS, then HIT. caching.md and the installation docs say to run `responsecache:clear` after template changes. | I didn't run a real `npm run build` because other testers share the server. |
| CACHE-5 | partly fixed | Fixed: `?q=` is never kept, `?reply_to=` likewise, and `/about?page=7771` is MISS on every visit (not kept). `?page=abc` is not kept. `?bedrooms_min=abc` and `?listing_type=nonsense` are not kept. `utm_source` and `PAGE=2` share `/about`'s key. | **Left (store flooding):** `/properties?page=123456` and `/journal?page=55555` (past the last page, a 200 with no results) are kept: MISS, then HIT, a new copy for every number. Any valid number for a `cache.query` filter is also kept (`?price_min=1234567`, `?bedrooms_min=9137`), and so is a `cache.query` parameter on a page with no filter (`/about?bedrooms_min=5`, `/about?sort=zzz`). The fix agent left the last of these on purpose. Page numbers past the last page could be refused (404) or left unkept. |
| CACHE-6 | fixed | `/properties/seminyak-beachside-villa`: **32** queries (was 56). Terms are loaded in one batch across fields (`terms id in (2,7,31,8,9,16,12)`), followed by one query for the parent term and one for entries. There are no per-field repeats. | `$agent->values()` adds nothing. |
| CACHE-7 | fixed | `/properties` runs one count and one select (the filter's query). The CMS's own `$entries` is never queried. `/location/bali` has one `aggregate`. | |
| CACHE-8 | won't fix (by design), agree | 10 cookieless HITs added 10 session rows (242 → 252). Responses still set two cookies and `Cache-Control: no-cache, private`. HIT TTFB is ~140–160 ms against 125–180 ms for `/up`. caching.md now documents all of this and suggests the redis, file or cookie session driver. | Docs-only fix, which matches the finding's "document it". |
| CACHE-9 | won't fix (by design), agree | Playwright to network idle: `/admin` 1.7–2.4 s, entries lists 1.0–1.2 s, Home editor 2.2 s, media 1.8–2.4 s, Settings → Site 2.2–3.5 s. But curl TTFB with Ayu's session is 165–250 ms, the same as `/up` (~140–180 ms) plus 0–40 ms. The panel still requests `fonts.bunny.net`, which is blocked here (ADM-3). | The time is framework boot (no OPcache under `artisan serve`), client JS and the blocked font. It isn't server work in Sunrice. |
| CACHE-10 | fixed | `/contact`: **9** queries, with no globals, blueprint, fieldset or form queries. `/about`: 12. `/sell-with-us`: 9. | Was ~8 extra on every page. |
| FORM-1 | fixed | `retest-frontend-form1.py`: two visitors of a kept `/contact` (HIT) get different `my_name_*` names and `valid_from` values. An instant POST gets a blank 200 (spatie's spam response) and nothing is stored. A POST after 2.5 s gets a 302 to `#form-contact` with "Thank you…" and is stored. | |
| FORM-2 | partly fixed | `FORM-2-newsletter-landing.jpg`: the newsletter lands on `/about#form-newsletter` with focus on the thank-you `<p>`, in view. Errors also land on `#form-x` (`FORM-2-contact-error-summary.jpg`, `FORM-2-inquiry-errors.jpg`). | **Focus does not move after errors:** `document.activeElement` is `<body>` on contact, inquiry and valuation. On valuation, the CMS's summary (`#form-valuation-errors`, `autofocus`, with links to the fields) is rendered, but browsers skip `autofocus` when the URL has a fragment, and the fragment target (`<form id="form-valuation">`) isn't focusable (see NEW-frontend-2). Contact and inquiry use the app's own slot markup ("Please check the fields marked below."), with no summary links and no focus. |
| FORM-3 | partly fixed | 7 failed contact sends in a row all got 302 (no 429). On the 4th valid valuation send in a minute (limit 3), the page goes back to the form with "You've sent this form several times in a short while. Please wait 52 seconds…; what you typed is kept", and the name, address and notes are kept (`FORM-3-valuation-limit.jpg`). | Still per IP (an office NAT shares the limit); the fix agent kept this on purpose. Chosen files are lost, which is unavoidable. |
| FORM-4 | partly fixed (regressed for the valuation form) | The file field now says "Accepted: JPG, JPEG, PNG, WEBP, up to 2 MB." (the lower of the field's 5 MB and PHP's 2 MB). | **A 3.4 MB JPG now gives "The * failed to upload."**, twice (in the summary and under the field), which is worse than the audit's "The Photos failed to upload." The valuation `photos` field is now *Several files* (`multiple`, `data[photos][]`), and the over-PHP-limit message and attribute name aren't applied to multi-file fields (NEW-frontend-1). `FORM-4-upload-too-big.jpg`. Single-file fields weren't tested (I didn't change the shared structure). |
| FORM-5 | partly fixed | GET on the signed link shows "Stop emails about replies?" with a **Stop these emails** button, and `notify_replies` stays 1. The button (POST) sets it to 0 ("You're unsubscribed"). A tampered signature gives 403. `FORM-5-unsubscribe-confirm.jpg`, `FORM-5-unsubscribed.jpg`. | The CMS part is fixed. The page is still the CMS's plain fallback card (site name only, no site layout), because Lumina has no `cms/comment-unsubscribe.blade.php`, although the starter templates now ship one. App-side. |
| FORM-6 | partly fixed | Fixed: the email leaves out empty fields (phone omitted), names the language ("in English"), names the page when there's a referrer ("from …/sell-with-us") and links to the submissions in the admin. A past preferred date (2020-01-01) is refused: "The Preferred date field must be a date after or equal to today." (`FORM-6-inquiry-past-date.jpg`). A post with no comments is headed "Leave a comment" (`FORM-6-empty-thread.jpg`). 3 held comments in a burst sent **1** moderator email. | Left: Lumina's inquiry date input has no `min` (the app's `cms/partials/form-field.blade.php` doesn't read `future`), so the browser still offers past days. In the email, multi-file uploads show only the file name, with no size or link (`poly-short.jpg`). The error says "Preferred date", but the label reads "Preferred viewing date". |
| A11Y-1 | fixed | Two ArrowDowns on the listing-type select: 0 reloads, and focus stays on the select. **Search** submits `?listing_type=for-rent&sort=newest`, with no empty parameters. A programmatic change no longer submits on its own (`A11Y-1-filter-after-keyboard.jpg`). | |
| A11Y-2 | fixed | Exactly one `<main>` on `/admin`, the entries lists, the entry editor, media and settings. | |
| A11Y-3 | fixed | Mobile (390×844), menu open: Tab cycles inside the header (menu links, phone, skip link, logo, EN/ID, Menu) and never reaches the page. `main` and `footer` are `inert`. Esc closes the menu and returns focus to **Menu**. `A11Y-3-mobile-menu-focus.jpg`. | |
| A11Y-4 | fixed | `/id/properties/signature-apartment-kebayoran-baru`: the page is `<html lang="id">`, with the title, summary, description and features in `lang="en"` wrappers, and an Indonesian notice says it's shown in the original language (`A11Y-4-id-fallback-property.jpg`). | `og:locale` is `en_US` on that page, which is arguably right for English content. The term chips still read "FOR SALE / APARTMENT" in English (an i18n content gap, not this finding). |
| A11Y-5 | fixed | No heading jumps on `/properties`, `/journal`, `/team`, `/neighbourhoods`, `/location/bali`, `/journal/topic/market-reports`, `/property-type/villa` or `/search` (all H1 → H2 → H3). The post's author avatar is `aria-hidden`, and the logo links are named by the SVG `<title>`. | |
| A11Y-6 | fixed | Agents → Reorder with the keyboard (cancelled with Esc, nothing moved): "Anindya Wirawan moved below Sekar Ayuningtyas, position 2 of 4." and "Moving Anindya Wirawan was cancelled; it's back in its place." The handle is labelled "Move Anindya Wirawan". Property editor link inputs are labelled "Map: address", "Map: link text", "Video: address" and "Video: link text" (`A11Y-6-drag-announcement.jpg`). | |
| extra: branding/settings media counted as used | partly fixed | Before: the site logo `lumina-logo.svg` (Settings → Site → logo = media 2) had `usages: []`. Re-saving Settings → Site **unchanged** didn't help either (`BRAND-media-after-resave.jpg`). After changing the X handle and changing it back (two real saves), the media details say "Used by Settings: Site" (`BRAND-media-site-logo-used.jpg`, row in `sunrice_media_usages`). | `SaveSettings` records usages only when something changed (`if ($changed !== [])`), and nothing backfills them: neither `sunrice:references` nor the backfill migration covers settings. So on an existing site the logo stays "unused" (and can be trashed without a warning) until someone *changes* that section. The commit message's advice, "shows as used once Branding is saved again", doesn't work. The Branding logo itself is empty right now (another tester's area), so I tested the same path through Settings → Site. |

## Query counts and timings (after the fixes)

In-process (`retest-frontend-queries.php`, isolated array caches so the shared site wasn't touched). The columns are: site cache cold with page MISS, site cache warm with page MISS, and HIT. Counts include 2–3 session and cache-table queries. HTTP TTFB comes from `retest-frontend-ttfb.sh`: the first visit after `responsecache:clear` (MISS), then the median of 5 (HIT). Times are on `php artisan serve` without OPcache, with other testers loading the server.

| Page | Before (live / no workaround) | Cold | Warm MISS | HIT | MISS TTFB | HIT TTFB | HTML KB |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Home `/` | 38 / 121 | 46 | **21** | 3 | 411 ms (cold boot) | 159 ms | 70 |
| Home `/id` | – | 45 | 21 | 3 | 255 ms | 146 ms | 70 |
| About | 17 | 35 | 12 | 3 | 202 ms | 150 ms | 42 |
| Our story | – | 37 | 14 | 3 | – | – | – |
| Contact | 15 | 34 | 9 | 3 | 276 ms | 139 ms | 52 |
| Sell with us | – | 34 | 9 | 3 | 261 ms | 155 ms | 46 |
| Properties listing | 37 | 57 | 36 | 3 | 243 ms | 161 ms | 71 |
| Properties, filtered | 43 | 60 | 39 | 3 | 246 ms | 152 ms | 69 |
| Property (en) | 52–56 | 56 | **32** | 3 | 220 ms | ~145 ms | 60 |
| Property (id, fallback) | 45 | 57 | 32 | 3 | – | – | – |
| Team | 18 | 32 | 11 | 3 | 216 ms | 141 ms | 43 |
| Neighbourhoods | – | 32 | 11 | 3 | 210 ms | 156 ms | 46 |
| Journal listing | 36 / 61 | 51 | **30** | 3 | 217 ms | 158 ms | 50 |
| Journal post | 46 / 91 | 67 (58 w/o helper) | **41 (33 w/o helper)** | 3 | 225 ms | ~137 ms | 58 |
| Topic term | 39 / 69 | 55 | **32** | 3 | 225 ms | 147 ms | 43 |
| Location term (Bali) | 36 | 57 | 34 | 3 | 218 ms | 137 ms | 57 |
| Property type (Villa) | – | 45 | 22 | 3 | 195 ms | 143 ms | 52 |
| Search `?q=villa` | 28 / 107 | 60 (50 w/o helper) | **26** | never kept (26) | 194 ms | 203 ms | 47 |
| 404 | – | 28 | 6 | never kept (6) | 193 ms | 207 ms | 39 |
| `/up` (baseline) | | | | | 177 ms | 123–138 ms | |

Warm-MISS render time in-process is 18–60 ms, and a HIT is 3–7 ms in-process. Over HTTP, a HIT is the framework boot (≈ `/up`).

## NEW findings

### NEW-frontend-1: Multi-file upload over PHP's limit says "The * failed to upload."
- Severity: medium
- Area: site
- Where: `/sell-with-us` (valuation `photos`, now *Several files*), `POST /forms/valuation`
- Steps: 1. As a guest, fill in the valuation form. 2. Choose a 3.4 MB JPG (PHP `upload_max_filesize` is 2M, the field allows 5 MB). 3. Send.
- Expected: "Photos: each file must be at most 2 MB" (as the FORM-4 fix does for single-file fields), naming the field.
- Actual: "The * failed to upload." appears in the summary and under the field. The attribute for `data.photos.*` isn't mapped to the label, and the PHP-limit message isn't used for multiple files.
- Screenshot: qa/retest/screenshots/FORM-4-upload-too-big.jpg
- Note: Worse than the audit's message. Notification emails also show multi-file uploads as a bare name, without the size or link that single files get.

### NEW-frontend-2: Focus never reaches the error summary after a failed send
- Severity: medium
- Area: a11y
- Where: every `<x-sunrice-cms::form>` sent back with errors (valuation shown); `packages/cms/resources/views/components/form.blade.php`, the `FormController` redirect
- Steps: 1. On `/sell-with-us`, send the form empty (server-side validation). 2. Check `document.activeElement` after the redirect to `/sell-with-us#form-valuation`.
- Expected: Focus is on `#form-valuation-errors` (the summary has `autofocus` and `tabindex="-1"`).
- Actual: Focus is on `<body>`. Browsers skip `autofocus` when the URL has a fragment, and the fragment target is the `<form>`, which isn't focusable. It works for the thank-you message only because that `<p>` carries the form's id itself.
- Note: Redirect errors to `#form-{handle}-errors` (or give the summary the fragment id), or focus it with a line of JS. Screen-reader users still land at the top of the form without hearing the errors.

### NEW-frontend-3: The error summary breaks the two-column layout of auto-rendered forms (app CSS)
- Severity: polish
- Area: site (app, not Sunrice)
- Where: `/sell-with-us` (`<x-sunrice-cms::form class="form-auto">`)
- Steps: Send the valuation form with an error or over the send limit.
- Actual: The new summary `<div>` becomes the first cell of the `.form-auto` grid. "Name" is pushed to the right column and every field below shifts by one cell. A rate-limit notice is headed "Please correct the following and send the form again:", which reads oddly for "please wait".
- Screenshot: qa/retest/screenshots/FORM-3-valuation-limit.jpg
- Note: Make `.form-auto > [role=alert]` span both columns (`grid-column: 1 / -1`). The CMS could use a neutral heading when the only error is `_limit`.

### NEW-frontend-4: `<x-sunrice-cms::terms>` costs 2 queries per term when the template links them
- Severity: low
- Area: performance
- Where: `/journal`, `/journal/topic/*` (`cms/partials/topics-nav.blade.php`: `$topic->url()` per term)
- Steps: Count queries on `/journal`: after the terms component's own two queries, there are 4 × (`select * from sunrice_terms where id = ? limit 1` + `select * from sunrice_taxonomies where id = ? limit 1`).
- Expected: The component's terms can be linked without extra queries (the translations' inverse `term` relation set, and the taxonomy loaded once).
- Actual: `Term::url()` → `TermTranslation::url()` → `Site::termUrl()` reloads the term and its taxonomy for each translation. That's 8 extra queries for 4 topics, and 2 per term on a location tree.
- Note: This is the "`Term::url()` +2" part of CACHE-6. The entry page itself is batched now; the component isn't.

### Note (environment, not a product finding)
The live `main`, `footer` and `legal` menus have no links to entries. About, Services and Contact are missing from `main`, and Services, Sell with us, Contact, Privacy and Terms from the footer and legal menus. The structure re-import at 01:50 replaced the menus with `database/structure.json`'s items, and that file has no entry links (`content.json` has them). The importer did as told, but the site's About/Contact navigation is gone until the menus are restored (the editor re-tester holds a backup in `qa/retest/backup/`).

## What works well now
- Query counts are flat and low without any app workarounds: home 21, post 33, listing 30, search 26, property 32. A kept page costs 3 queries and returns in about framework-boot time.
- The page cache is much harder to poison or flood: `reply_to` and `q` are never kept, invalid filter values aren't kept, a page number counts only where it's read, kept pages are keyed on the front-end build, and each visitor gets their own honeypot fields.
- The forms flow is far friendlier: failed tries don't count against the limit, the limit message keeps what was typed, visitors land on the form, file limits are stated up front, past dates are refused server-side, unsubscribing needs a click, and moderators get one email per burst.
- Menu trails, the hierarchical location filter, SEO details (404, paged titles, hreflang in the sitemap, one URL per page) and the accessibility fixes (filters, mobile menu trap, fallback `lang`, headings, admin `<main>`, named drag announcements) all behave as described in the docs.
- Cleanup: my 5 submissions (contact, newsletter, 3 valuation) and 3 comments (trashed, then deleted) were removed with `retest-frontend-cleanup.php`. Settings → Site was changed and put back; its stored JSON now also lists the new empty keys (`descriptions`, `publisherName`, `publisherType`, `telephone`, `address`, `sameAs`), with the same effective values.
