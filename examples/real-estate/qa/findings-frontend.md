# Findings: frontend (visitor) hat

Tester: a visitor of the public Lumina Estates site (http://127.0.0.1:8200, EN and /id), plus the AI access (MCP) with Ayu's tokens. Date: 2026-10-20. Scripts: `qa/scripts/frontend-*.mjs` (`frontend-lib.mjs` shared, `frontend-01-crawl.mjs` crawls every sitemap URL for meta/JSON-LD/a11y/timings, `frontend-mcp.mjs` is a tiny MCP client). Screenshots: `qa/screenshots/frontend-*.png`. Desktop 1440×900, mobile 390×844.

Known items from the other hats are not repeated; where I confirm or add detail to one, it says so.

## Top 10

1. **high** With the page cache on, a comment's **Reply** link (`?reply_to=`) gets the kept page without the reply form, and the first visitor who opens `?reply_to=N` while a page isn't kept **poisons** the kept page: everyone then sees "Replying to Sophie Laurent" and every new comment is filed as a reply to that comment. (Caching / comments)
2. **high** Indonesian listing, term and search pages only show entries that have an Indonesian translation (6 of 15 properties on `/id/properties`, 2 villas, Seminyak empty), although every untranslated entry *is* served at `/id/...` through the fallback. Most of the catalogue is invisible to Indonesian visitors. (i18n)
3. **high** The main menu's "Dijual"/"Disewakan" links send Indonesian visitors to the English site (`/properties?listing_type=for-sale`), and on `/id/properties` the English filter values are silently ignored because the Indonesian term slugs differ (`dijual`): the menu and shared filter URLs can't work across languages. (i18n; adds to admin's "no listing-with-filter link type")
4. **medium** The page cache keys on `page`, `q` and every `cache.query` parameter **with any value, on every page**: `/about?page=7771`, `/?q=x`, `/properties?bedrooms_min=<random>` each render and are kept. The docs say "nobody can fill the store with copies of one page". (Caching / security)
5. **medium** Kept pages carry the honeypot's `valid_from` from the moment they were rendered, so the "too fast" check never fires on cached pages: a script that GETs a kept page and POSTs within milliseconds is accepted. (Forms / security)
6. **medium** Indonesian visitors get English validation messages with English field names on otherwise Indonesian forms ("The Name field is required." under "NAMA ANDA"); thank-you messages are English too (confirms admin's single-language thank-you). (i18n)
7. **medium** Form round trips land the visitor at the top of the page: after the footer newsletter (every page) the success/error message is far below the fold; focus goes to `<body>`. The 6th submit in a minute, *including failed validation*, gets Laravel's bare white "429 Too Many Requests" page and loses the message. (Forms UX / a11y)
8. **medium** Translations of dated entries get their own published date: on `/id/journal` every post reads "20 Oktober 2026" (the day the translation was published) while the English originals are 7 Oct, 28 Sep, 14 Sep...; order and `datePublished` differ per language. (i18n)
9. **medium** Menu active states: entries under a listing (a property, a post, an agent, a neighbourhood) get no active trail at all, and because matching ignores the query string, "For sale" and "For rent" are *both* `aria-current="page"` on `/properties` (and on either filtered URL). (Menus; documented behaviour, but wrong for visitors and screen readers)
10. **medium** Site-level performance: the font set downloads both the preloaded `.woff2` files (88 KB, unused) and `.woff` duplicates (169 KB) because each weight is declared twice with the woff rule last; and every request, even a kept page, creates a database session row (10 cookieless hits = 10 rows). (Performance)

## Caching

### Reply links don't work with the page cache, and a reply form can be kept for everyone
- Severity: high
- Area: site / performance (page cache + comments)
- Where: any journal post, e.g. `/journal/living-in-ubud-a-year-in-the-hills?reply_to=1`
- Steps: 1. As a guest click **Reply** under a comment (`?reply_to=1#comment-form`). 2. Note `X-Cache-Status: HIT` and no "Replying to …" in the form. 3. Poisoning: when a post isn't kept yet (after any content change starts a new version), open it with `?reply_to=1` first (`curl '/journal/x?listings=Z&reply_to=1'` simulates it), then open it without the parameter.
- Expected: `reply_to` makes the page different (or such requests skip the cache), as `?draft` and previews do.
- Actual: Step 2: the guest gets the kept page; Reply silently does nothing. Step 3: the second request is a HIT that contains `<input type="hidden" name="parent_id" value="1">` and "Replying to Sophie Laurent · Cancel", served to every visitor until the next content change or the TTL: every new comment becomes a reply to comment 1.
- Note: `reply_to` is the CMS's own parameter (comments.md), but it isn't among the parameters the page cache varies on (`page`, `q`, `cache.query`), and the CMS doesn't skip requests that carry it. Workaround for the site: add `reply_to` to `sunrice-cms.cache.query`. The CMS should do this itself.

### Any value of `page`, `q` or a `cache.query` parameter makes a new kept page, on every page
- Severity: medium
- Area: performance / security
- Where: `/about?page=7771`, `/?q=x`, `/?page=1`, `/properties?bedrooms_min=<random>`
- Steps: 1. `curl -I '/about?page=7771'` twice: MISS then HIT. 2. Repeat with any number.
- Expected: caching.md: "`?utm_source=…` or `?x=<anything>` gets the same kept page, so nobody can fill the store with copies of one page." `utm_*`, `fbclid` and `?PAGE=2` are indeed ignored (good).
- Actual: `page` and `q` vary the key on pages that don't paginate or search (the About page answers 200 for `?page=7771` and keeps it), and the listing's filter parameters accept any value (`bedrooms_min=abc` is ignored by the filter but still makes a new kept copy). Each is a full render (~250 ms) plus a stored copy.
- Note: Only pages whose page number exists (a 404 isn't kept, good) or whose filter values are valid should be kept; or `page`/`q` should only count where the page reads them.

### Cached pages still cost a framework boot and a session row
- Severity: low
- Area: performance
- Where: every page, guests
- Steps: 1. `curl` a kept page 10 times with no cookies. 2. Count `sessions` rows (594 → 604).
- Actual: Each hit starts a session and writes it (DB session driver), sets two cookies (`XSRF-TOKEN`, session) and answers `Cache-Control: no-cache, private`, so no browser or CDN can keep anything. TTFB of a HIT ≈ 150 ms here vs 130 ms for Laravel's `/up` (no OPcache/config cache on `php artisan serve`, so the boot dominates; a MISS is ~250 ms).
- Note: Expected given per-visitor CSRF tokens, but caching.md's "repeat visits cost 0 queries" (developer findings) isn't quite true: one session write per cookieless hit (crawlers, link previews). Worth a line in the docs.

### Publishing shows at once; signed-in editors never get a kept page; utm is ignored (works)
- Severity: polish (what worked)
- Area: performance
- Actual: `/journal` HIT → publish a post through MCP → next guest request MISS with the new post, then HIT. Trashing it removed it from `/journal`, the post URL (404) and `sitemap.xml` immediately. Ayu's requests carry no `X-Cache-Status` header at all. Kept pages' forms submit fine (per-visitor CSRF). An approved comment shows for guests at once.

## i18n

### Indonesian listing, term and search pages hide every untranslated entry, although the fallback serves them
- Severity: high
- Area: i18n
- Where: `/id/properties` (6 properties vs 15 on `/properties`), `/id/property-type/vila` (2 vs 4), `/id/location/seminyak` (empty), `/id/search?q=villa` (1 result vs 4 villas), `/id` term pages
- Steps: 1. Compare `/properties` and `/id/properties`. 2. Open `/id/properties/signature-apartment-kebayoran-baru`: it works (fallback note "Halaman ini belum tersedia dalam Bahasa Indonesia…").
- Expected: With `site.fallback` on, listing pages, term pages, search and filter counts in a language include the main-language fallback entries (as the detail pages do), or at least an option to.
- Actual: Only translated entries are listed, searched and counted ("Dijual (4)", "Rumah (4)"), so an Indonesian visitor sees a 6-property agency.
- Screenshot: qa/screenshots/frontend-57-id-properties-only-translated.jpg
- Note: Translating every listing is the only workaround; for an agency adding listings weekly that's unrealistic.

### Menu filter links and filter URLs don't work across languages
- Severity: high
- Area: i18n
- Where: main menu on `/id` ("Dijual" → `/properties?listing_type=for-sale`), `/id/properties?listing_type=for-rent`
- Steps: 1. On `/id`, open Properti › Dijual. 2. Open `/id/properties?listing_type=for-rent`.
- Expected: The Indonesian menu stays in Indonesian and the filter applies.
- Actual: 1. Lands on the English site. 2. The filter uses term slugs per language (`dijual`, `disewakan`, `rumah`, `vila`, `jakarta-selatan`), so the English value is silently ignored and all 6 are shown; no chip, no message. The language switcher on a filtered listing drops the filters (`/id/properties`), understandable given the slugs.
- Note: Confirms and extends admin's "No listing-with-filter link type". A menu item "listing page + filter" (language-aware), or filters that accept the term id or the main slug in every language, would fix both.

### Validation messages and thank-you messages are English on Indonesian pages
- Severity: medium
- Area: i18n
- Where: `/id/kontak`, `/id/properties/rumah-taman-kemang` (inquiry), footer newsletter on `/id`
- Steps: 1. On `/id/kontak` submit with an empty name and an invalid email (browser validation off). 2. Submit valid.
- Expected: Indonesian messages.
- Actual: The summary is Indonesian ("Mohon periksa kolom yang ditandai di bawah.", the template's) but each field says "The Name field is required.", "The Email field must be a valid email address." The attribute names come from the blueprint labels (English) and the app has no `lang/id/validation.php`; even with one, "Name" would stay English. Thank-you texts ("Thank you for getting in touch…") are English (admin's finding, confirmed on the site).
- Screenshot: qa/screenshots/frontend-20-contact-invalid-id-form.jpg
- Note: Related to developer's "auto-rendered forms show English labels": custom markup with translated labels doesn't help, because messages use the blueprint's labels.

### Translations of dated entries are dated the day the translation was published
- Severity: medium
- Area: i18n
- Where: `/id/journal`, `/id/journal/pasar-hunian-premium-jakarta-kuartal-iii-2026`
- Steps: Compare post dates on `/journal` and `/id/journal`.
- Expected: A translation shares the article's date (or one can choose).
- Actual: Every Indonesian post shows "20 Oktober 2026" (`datetime="2026-10-20T17:29…"`), the English ones 7 Oct, 28 Sep, 14 Sep…; the Indonesian listing's order and the BlogPosting `datePublished` follow the translation's publish time.

### Links from Indonesian pages to untranslated entries go to the English site
- Severity: low
- Area: i18n
- Where: agent cards on `/id`, `/id/properties/*` (`/team/anindya-wirawan`), RealEstateListing `offeredBy.url`
- Steps: On `/id/properties/rumah-taman-kemang` follow the agent's name.
- Expected: `/id/team/anindya-wirawan` (the fallback page exists and works), so the visitor stays in Indonesian.
- Actual: The English URL; the whole UI switches to English.

### Site description and term pages without their own description fall back to English text on /id
- Severity: low
- Area: i18n / SEO
- Where: `/id/location/jakarta-selatan`, `/id/team`, all term/agent/topic pages
- Actual: `<meta name="description">` and the WebSite node's description are "Premium homes, villas, apartments and land for sale and rent in Jakarta and Bali." on Indonesian pages: Settings → Site description has one language. The same generic description is used on ~70 pages in English too (agents, terms, topics: no "SEO from fields" for agents, no description field on terms).

### Fallback content isn't marked as English
- Severity: low
- Area: a11y / i18n
- Where: `/id/properties/signature-apartment-kebayoran-baru`
- Actual: `<html lang="id">` and the Indonesian UI around English body text, without `lang="en"` on the content; screen readers read the English text with Indonesian pronunciation. `og:locale` says `en` on the same page.

### Small things
- `/id/about` is a 404 (the Indonesian slug is `tentang-kami`); a translated entry could answer its main-language slug with a redirect. Low.
- Comment and submission times are UTC ("17:55") for an Indonesian company (WIB = UTC+7): the app's timezone, but neither the CMS docs nor Settings mention it. Low.
- Newsletter copy contradicts itself: "Quarterly market notes" / "Catatan pasar setiap kuartal" above "One considered email a month". Content polish.

## Forms

### The honeypot timing check is void on kept pages
- Severity: medium
- Area: security / forms
- Where: `/contact` (kept page), `POST /forms/contact`
- Steps: 1. `GET /contact` (HIT, rendered minutes ago). 2. POST the form at once with curl, sending the page's `_token`, `my_name_*` and `valid_from` (submission "QA Curl Instant").
- Expected: A submit faster than the honeypot's minimum time is refused, as on an uncached page.
- Actual: Accepted (302, thank-you, email sent): `valid_from` is the time the kept page was rendered, and the honeypot field name is the same for every visitor until the page is re-rendered. A bot only has to fetch the page once. Without the honeypot fields a POST is refused (blank 200), good.
- Note: The CSRF token is swapped per visitor; the honeypot fields should be too (or the timing check dropped when pages are kept, and the docs say so).

### After a form, the visitor lands at the top of the page; focus isn't moved to the message
- Severity: medium
- Area: forms UX / a11y
- Where: footer newsletter (every page), contact, inquiry, valuation forms
- Steps: 1. On the home page, subscribe in the footer. 2. Watch where you land.
- Expected: Back at the form (`#form-newsletter`), with the message announced; on errors, focus on the summary or the first invalid field.
- Actual: Redirect to the bare URL: `scrollY=0`, the message is ~4,000 px below; focus is `<body>`. For contact/inquiry the message is mid-page. Comments do this right: they redirect to `#comments` / `#comment-7`.
- Screenshot: qa/screenshots/frontend-26-newsletter-en-ok.jpg (scrolled to the form; the visitor actually sees the hero)

### Rate limit: 5 tries a minute including failed ones, then a bare white 429 page
- Severity: medium
- Area: forms UX
- Where: `POST /forms/contact`
- Steps: 1. Submit the contact form twice with errors, twice valid, once with the honeypot filled. 2. Submit again.
- Expected: Validation failures don't count (or count less); a 429 shown in the site's design ("Please wait a minute"), with the visitor's text kept.
- Actual: The 6th try within a minute is Laravel's unstyled "429 | Too Many Requests"; the typed message is lost. Comments the same at the 4th (3/min); signed-in Ayu was rate limited too, because the limit is per IP (an office behind one NAT shares it).
- Screenshot: qa/screenshots/frontend-23-rate-limit.jpg
- Note: The site has `errors/404`, `500`, `503` views but not `429`; the CMS could render its own friendly page or flash an error back to the form.

### Uploads: allowed size fails with "The Photos failed to upload."
- Severity: low
- Area: forms
- Where: `/sell-with-us` (valuation, photos max 5 MB)
- Steps: Upload a 3.4 MB JPG.
- Expected: Accepted (rule is 5 MB), or a message saying the server limit.
- Actual: "The Photos failed to upload." PHP's `upload_max_filesize` here is 2 MB (environment), and the message gives the visitor nothing to act on. The form doesn't state accepted types/size either. (8 MB: same message; `.php` renamed `.jpg`: "must be a file of type: jpg, jpeg, png, webp" (content sniffed, good); real `.php`: "Files of this type can't be sent." (good); a file with a JPEG signature followed by PHP code is accepted (stored on the private disk, so low risk; for the security hat).)

### Inquiry, contact, newsletter and comment forms otherwise work well
- Severity: polish (what worked)
- Area: forms
- Actual: Contact valid/invalid with per-field errors, `aria-invalid` + `aria-describedby`, old input kept; inquiry prefilled with the property's title in each language ("Kemang Garden House" / "Rumah Taman Kemang"); honeypot-filled posts silently dropped; all submissions in the admin (Forms → Contact (6) …, CSV export); notification emails escaped (`&lt;script&gt;`), with `Reply-To` the visitor. Minor: the notification email has no link to the submission in the admin, no page/language the visitor used, shows the uploaded file's name only, and lists empty fields; the inquiry's preferred date accepts a past date (2020-01-01).

## Comments

### Comment moderation round trip works; markup is escaped and links are safe
- Severity: polish (what worked)
- Area: site
- Actual: Guest's first comment held ("Thank you! Your comment will appear once it is approved."), redirect to `#comments`; HTML and `<script>` shown as text, `javascript:` not linked, `https://…` linked with `rel="nofollow ugc noopener"`, line break kept; Ayu signed in comments as herself, published at once; approve + reply in the admin; the reply email "Ayu Pratiwi replied to your comment" with a working, signed unsubscribe link (tampered signature → 403).
- Screenshots: qa/screenshots/frontend-30-comment-guest-held.jpg, frontend-31-comment-markup.jpg, frontend-33-comment-signed-in.jpg, frontend-34-admin-comments.jpg

### Comment details
- Low: The unsubscribe page is the CMS's own unbranded card (amber links, system font, "Unsubscribed | Lumina Estates" with a different separator than the site's "·"), and it unsubscribes on GET, so mail scanners that prefetch links unsubscribe people. Screenshot: frontend-37-unsubscribe.jpg
- Polish: A post with no comments has the heading "0 comments" / "0 komentar" above the form; "Leave a comment" reads better.
- Polish: Every held comment from the same guest sends its own "A comment to approve" email (3 mails in a minute).

## Menus, navigation, SEO

### Active trail missing for entries under a listing; both filter links "current"
- Severity: medium
- Area: site / a11y
- Where: `/properties/kemang-garden-house`, `/journal/*`, `/team/*`, `/neighbourhoods/*`, `/properties`
- Expected: "Properties" marked as the trail on a property page; only the matching filter link current.
- Actual: No `aria-current`/trail on any detail page; on `/properties` "Properties", "For sale" and "For rent" are all `aria-current="page"` (menus.md: active "without the query string"). Pages under Services (Buy, Sell, Rent…) don't light up "Services" either (the menu has no children there; the page tree isn't used for the trail).

### SEO tags and JSON-LD: solid, a few rough edges
- Severity: low
- Area: SEO
- Where: all page types (crawled 119 URLs: `frontend-01-crawl.mjs`)
- Actual (good): every page has a title, description, canonical, hreflang + x-default where translated, og/twitter tags, valid JSON-LD (all parse; WebSite + SearchAction, Organization, BreadcrumbList, BlogPosting, CollectionPage, SearchResultsPage, the template's RealEstateListing/Offer and Person). Terms is `noindex` and left out of the sitemap; search is `noindex` with no canonical; drafts/previews `noindex` + `no-store`.
- Actual (rough):
  - The 404 page has a canonical pointing at the missing URL and no `noindex`. `/journal?page=2` (beyond the last page) is a 404 with canonical `/journal`.
  - Paginated listings repeat page 1's `<title>` ("Properties for sale … · Lumina Estates" on `?page=2`); the "previous" link points to `?page=1` instead of the clean URL.
  - `noindex` pages get `noindex, nofollow` (the editor only asked to keep Terms out of results).
  - BlogPosting `author` is the Organization although the post has an author agent; `knowsLanguage` is raw select values (`"english","dutch"`); RealEstateListing Offer for rentals has no `priceSpecification`/unit (IDR 38,000,000 with no "per month"); rented/sold listings read "Asking price" on the page (site template).
  - WebSite node uses one `@id` (`/#website`) with a different `url` per language.
  - Trailing-slash and `/index.php` variants answer 200 (`/properties/`, `/about/`, `/index.php`) rather than redirecting (canonical tags limit the damage).
  - `robots.txt` (app's static file) has no `Sitemap:` line; term and listing URLs in `sitemap.xml` have no hreflang alternates although they have translated slugs (`/location/south-jakarta` ↔ `/id/location/jakarta-selatan` are listed as unrelated URLs).
  - `og:image` is empty everywhere (no photos yet; expected).

### `cms_seo_audit` judges the entry title, not the rendered title
- Severity: low
- Area: API
- Where: MCP `cms_seo_audit`
- Actual: "Home: The meta title is short (4 characters)" although the page's `<title>` is "Lumina Estates"; Contact (which has a form) is "thin content". Otherwise a useful list (missing descriptions, share images, untranslated pages).

## Accessibility

### Filter selects submit on every arrow key
- Severity: medium
- Area: a11y (site template)
- Where: `/properties` filter (`data-autosubmit`)
- Steps: Tab to "Buy or rent", press ArrowDown.
- Expected: Changing a select doesn't navigate (WCAG 3.2.2), or only on blur/Enter.
- Actual: The page reloads on the first ArrowDown (`?q=&listing_type=for-sale&property_type=&location=&…`), focus returns to the top; reaching "For rent" with the keyboard takes two reloads. The URL also fills with empty parameters.
- Screenshot: qa/screenshots/frontend-52-filter-keyboard.jpg

### Mobile menu doesn't keep focus inside
- Severity: low
- Area: a11y
- Where: mobile menu (390 px)
- Actual: Opens with Enter (`aria-expanded` true), Esc closes and returns focus to the toggle (good), but Tab past "+62 21 7199 4400" moves into the page hidden behind the full-screen menu ("View properties", property cards).

### Headings, landmarks, focus and contrast: mostly good
- Severity: low
- Area: a11y
- Actual: Skip link first, visible 2 px focus rings everywhere, dropdowns open on focus, one `<main>`, labelled navs, every input labelled, no missing alt (no images yet), no layout shift (CLS 0 on all 119 pages), no horizontal overflow at 390 px, reduced motion and no-JS show all content. Rough: listing and term pages jump H1 → H3 (cards) with no H2; the agent avatar link on property pages has no text (it's `tabindex="-1"` but not `aria-hidden`); dark-on-dark visually hidden heading "About Lumina Estates" in the footer is fine. Key text contrast passes (teal/ink on ivory; footer white at 50–80 % on #0b2f2d ≥ 4.6:1).

### Gallery viewer not testable
- Severity: polish (note)
- Area: site
- Actual: No property has photos yet (Unsplash blocked), so the `<dialog>` gallery (keyboard arrows, swipe, focus return in `resources/js/app.js`) and image weights couldn't be tested. Re-test after the photo import.

## Performance

### Page timings (guest, page cache on, `php artisan serve` without OPcache)
- Severity: low (for the record)
- Area: performance

MISS = first render of that page (site cache warm, `?listings=<random>` to force a new kept page); HIT = kept page (median of 3). Requests/weight from a cold browser load (no images exist). Query counts: developer's live numbers (page cache off), since the page cache serves HITs without CMS queries.

| Page | MISS TTFB | HIT TTFB | HTML KB | Requests | Transfer KB | Queries (dev) |
| --- | --- | --- | --- | --- | --- | --- |
| Home `/` | 274 ms | 180 ms | 82 | 17 | 390 | 38 |
| Home `/id` | 247 ms | 153 ms | 82 | 17 | 390 | – |
| About | 240 ms | 164 ms | 55 | 15 | 300 | 17 |
| Our story (child) | 236 ms | 178 ms | 54 | 15 | 299 | – |
| Contact | 251 ms | 163 ms | 64 | 15 | 309 | 15 |
| Sell with us | 263 ms | 182 ms | 59 | 15 | 304 | – |
| Properties | 719 ms | 383 ms | 83 | 16 | 358 | 37 |
| Properties, filtered | 303 ms | 156 ms | 73 | 16 | ~350 | 43 |
| Property | 294 ms | 147 ms | 79 | 16 | 355 | 52 |
| Property (id) | 261 ms | 153 ms | 77 | 16 | 353 | – |
| Team | 235 ms | 149 ms | 56 | 15 | 301 | 18 |
| Agent | 211 ms | 137 ms | 70 | 16 | 346 | 25 |
| Neighbourhoods | 213 ms | 166 ms | 58 | 15 | 303 | – |
| Neighbourhood | 252 ms | 150 ms | 64 | 16 | 340 | 22 |
| Journal | 251 ms | 184 ms | 63 | 15 | 308 | 36 |
| Post | 285 ms | 152 ms | 69 | 17 | 377 | 46 |
| Topic | 295 ms | 408 ms* | 57 | 15 | 302 | 39 |
| Location (Bali) | 414 ms | 171 ms | 70 | 16 | 345 | 36 |
| Property type (Villa) | 229 ms | 173 ms | 64 | 16 | 340 | – |
| Search `?q=villa` | 248 ms | never kept (~237 ms) | 60 | 15 | 305 | 28 |
| 404 | 221 ms | never kept (~200 ms) | 52 | 14 | 279 | – |

\* noisy: other agents were loading the server at the time. Baselines: Laravel `/up` 130 ms, a static file 1–2 ms. Full load (DOMContentLoaded/load) 200–600 ms on localhost; CLS 0 everywhere.

### Fonts are downloaded twice (woff2 preloaded, woff used)
- Severity: medium
- Area: performance (site build)
- Where: every page; `@fonts` in `cms/layout.blade.php`, `build/assets/fonts-*.css`
- Steps: Load `/` in Chromium and list font responses.
- Expected: Six woff2 files (~88 KB) for the weights used.
- Actual: The six preloaded `.woff2` files are fetched, then seven `.woff` files (169 KB) are used instead, because each weight/range has two `@font-face` rules (woff2, then woff) with the same descriptors and the later one wins. 257 KB of fonts on every first visit, ~2/3 of the page weight without images. Not a Sunrice issue (laravel-vite-plugin font support/@fontsource setup), but it's the site's biggest weight today.
- Note: Fix: one `src: url(woff2) format("woff2"), url(woff) format("woff")` per face, or woff2 only.

### Other page-weight notes
- Severity: low
- Area: performance
- Actual: One render-blocking stylesheet (54 KB, fine), one 2 KB deferred module; no third-party requests (good). Home HTML is 82 KB (inline SVG icons and the honeypot/newsletter markup on every page), acceptable. No images exist yet: image weights, `srcset`, lazy loading and LCP can't be judged until the photos are imported.

## AI access (MCP)

### MCP works end to end and respects read-only tokens
- Severity: polish (what worked)
- Area: API
- Where: `POST /mcp` with Ayu's tokens ("Claude Code (full)", "Claude (read-only)")
- Actual: `initialize` gives clear instructions; full token 44 tools, read-only 21 (write tools aren't listed and calling them answers "Tool [cms_save_entry] not found."). Exercised: get_panel_info, cms_list_entries, cms_get_entry, cms_list_terms, cms_save_entry (created journal entry 50 as a draft; `<script>`, `onerror` and `javascript:` stripped from rich text), cms_translate_entry + save with `locale: id`, cms_publish_entry (live for guests at once), cms_render_page, cms_get_menu, cms_reorder (agents reordered on /team, restored), read_docs, list_media, get_activity (every action listed as "AI agent · Ayu laptop – Claude Code (full)", also on the admin's Activity log: frontend-60-activity-ai-agent.jpg), cms_seo_audit, cms_trash_entry (cleanup). Calls take 220–410 ms.

### `cms_render_page` renders with host `localhost`
- Severity: low
- Area: API
- Where: MCP `cms_render_page {"path":"/id/kontak"}`
- Actual: The HTML's canonical and links are `http://localhost/id/kontak` although `APP_URL` is `http://127.0.0.1:8200`, so an agent checking canonical/hreflang/links gets wrong answers. For a 404 the `error` field is " in vendor/laravel/framework/src/Illuminate/Foundation/Application.php:1445" (noise, no message).

### Smaller MCP notes
- Low: `cms_reorder`'s arguments are `type`/`in`/`ids`; an agent guessing `collection` gets "The type field is required. The in field is required." (fine, it's in the schema). A partial `ids` list is accepted and logged as another "reordered" line even when nothing changed.
- Low: An empty single entries field (`"author_agent": []`) is refused with "The Author field must be an integer." instead of being cleared; `null` isn't documented.
- Low (privacy): the read-only token can read every form submission (`cms_list_submissions`: visitors' names, emails, phones, messages). "Read-only" reads as harmless; the AI access page could say that read-only still exposes personal data.

## Drafts and previews

### Draft banner, ?draft and preview links work as documented
- Severity: polish (what worked)
- Area: site
- Actual: A live post with a newer draft shows Ayu a sticky dark banner "DRAFT · This page has unpublished changes. View the draft · Edit" (frontend-40-draft-banner.jpg); `?draft` renders the draft with `noindex, nofollow` and `Cache-Control: no-store, private`; a guest asking `?draft` gets the live kept page. The editor's Preview opens `/cms-preview/64?expires=…&signature=…`: works for a guest for 30 minutes, `noindex` header and meta, `no-store`, no canonical; a tampered signature or extended `expires` → 403. No scheduled entries remain on the site, so the scheduled flow wasn't re-tested here.

## Visual review (desktop and mobile)

### The site looks professional and on brief
- Severity: polish (what worked)
- Area: site
- Actual: Calm warm-ivory palette with deep teal, Cormorant Garamond headings over Manrope, generous whitespace, consistent cards, badges (For sale / Under offer / Rented), a filter panel that floats over the header, friendly empty state ("No properties match … many of our homes are offered privately"), chips with "Clear all", a good 404 with search. Mobile: clean header with language switch and a full-screen serif menu with a call button. Screenshots: frontend-01-home.jpg, 02-properties.png, 03-property.png, 53-mobile-home.png, 54-mobile-menu-open.png.
- What holds it back today: no photography anywhere (empty gradient hero, placeholder house icons on every card), which is expected until the Unsplash import; and long full prices on cards ("IDR 95,000,000,000") where luxury sites usually write "IDR 95 bn" (the filter already uses "IDR 50 billion").

### Visual polish
- Polish: The footer bottom bar prints "© 2026 PT Lumina Estates Indonesia" twice (left, and again at the start of the legal line). frontend-01-home.jpg
- Polish: On the 404 the big "404" numerals overlap the heading's letters ("page", "moved"); the title "This page has moved on" suggests a move rather than a missing page. frontend-12-404.jpg
- Polish: Rented/sold properties still show "Asking price" and the viewing form. frontend-16-property-rented.jpg

## What I created (visitor content, left in place)

- Contact submissions: "QA Frontend Tester", "QA Penguji Frontend", "QA Too Fast", "QA Too Fast 2", "QA Curl Instant" (all `@example.com`). Inquiry: "QA Frontend Inquiry" ×2, "QA Past Date". Valuation: "QA Valuation valid jpg" (8 KB generated test image), "QA Valuation polyglot jpg" (26-byte test file, private disk). Newsletter: `qa.frontend+news@example.com`, `qa.frontend+berita@example.com`. Delete with Forms → submissions or `cms_delete_submissions` if unwanted.
- Comments on "Five things to check before renewing a villa lease in Bali": QA Guest Frontend ×3 (two approved: "QA guest first comment…", "QA markup test…"; "QA rate test 3" pending), Ayu's "QA signed-in comment from Ayu (frontend test).", Ayu's admin reply. The guest unsubscribed from replies to comment 4.
- MCP: journal entry 50 "QA frontend: AI-drafted market note" (EN published then trashed; ID draft) — in the trash, not deleted for good. Agents order changed and restored (Anindya, Sekar, Rafael, Maarten).

## What worked well

- Every page type renders, in both languages, with sensible titles, canonical/hreflang, og tags and valid structured data; the fallback for untranslated entries (with a clear note) and the language switcher that lands on the translated page everywhere.
- Redirects (`/listings`, `/blog`, with query string kept), `sitemap.xml` (104 URLs, Terms excluded, `/sitemap-1.xml` works), noindex for Terms, search and previews.
- Filters: every combination, parent locations including children (Bali → 7, Jakarta → 8), sort, pagination, 404 past the last page, bogus values ignored safely, chips.
- Search is safe and calm with odd input (script tags escaped, `%`, `_`, quotes, emoji, 3,000-character query capped at 100).
- Forms: CSRF on kept pages, honeypot, server-side validation with accessible errors, content-sniffed uploads, PHP files refused.
- Comments: escaping, safe linkify, moderation round trip, signed unsubscribe links.
- Page cache: fresh on publish/trash, never for editors, `utm_*` ignored.
- MCP: well-described tools, read-only enforced by omission, sanitised writes, everything in the activity log as "AI agent".
