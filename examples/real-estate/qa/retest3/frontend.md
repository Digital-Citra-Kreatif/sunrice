# Round 3 — frontend (site, cache, forms, accessibility)

Tested 2026-10-21 against http://127.0.0.1:8200: as a guest with curl (headers, HTML, cache keys), in-process query counts (`qa/scripts/retest2-front-and-languages-queries.php`, isolated array caches), Playwright (Chromium, desktop and 390 px mobile) and as Ayu for Settings → Cache and the admin checks. No source code or git changes. Screenshots: none saved, since nothing regressed; the one new finding is shown by markup.

## Settings → Cache (step 8)

- **Kept pages are served.** `/about`, `/properties`, `/journal`, `/team`, `/contact` answer MISS then HIT (`X-Cache-Status`). A HIT costs 3 queries in-process (session, cache lookup, session update). Over HTTP a cookieless HIT still writes one session row (CACHE-8).
- **Excluded address (`/contact`) and Skip for other parameters, saved through the UI:** `/contact` is not served from or kept in the cache (no `X-Cache-Status`). `/about?foo=1` is not kept (no header). `/about?utm_source=x` still gets the kept `/about` (tracking parameters are ignored even when others skip). `/properties?sort=newest` is still kept.
- **Clear page cache** (confirm dialog, "Continue"): toast says the page cache is cleared. Activity logs "cleared / the page cache". Next requests: `/about` MISS then HIT, `/properties` MISS then HIT.
- **Clear all site caches**: toast and activity log ("all site caches") confirmed. `/about` MISS then HIT.
- **Preload cache** (confirm dialog, "Continue"): run finished with 106 of 106 pages warmed, 0 failed (`PreloadCache::status()`); activity logs "preloaded / the page cache". The queue worker is running and the jobs table was empty afterwards. `/journal` was a HIT on the first request after the run. Other testers published content during the run, which flushed some pages, so this is only a partial check.
- **Restored:** exclusion removed, "Any other parameter" back to Ignore. The `cache` settings row did not exist before; it now holds the default values (`exclude:""`, `otherQuery:"ignore"`, the tracking list, the rest default). `/contact` is kept again.
- **Not tested:** the "Don't keep whole pages" switch (it would have changed the site-wide setting; the exclusion and skip paths were tested instead).

## Kept form pages and CSRF

- `/about` (kept) is served to two cookie jars with different `_token`, honeypot field names and `valid_from`, so each visitor gets their own.
- A POST with a visitor's own token, honeypot fields and cookie, sent 3 s after the page was served (empty email, so nothing stored): 302 to `#form-newsletter-errors` (token and honeypot accepted).
- The same POST at once (within the same second): blank 200 (honeypot refused, as designed).
- A token from another visitor, with the wrong cookie: 419 Page Expired (both directions).

## Results

| ID | Result | Evidence |
|---|---|---|
| SITE-1 | Still fixed | `/properties?location=bali`: Bali selected (`<option selected>`), "7 properties" shown (was 15 total; the children of Bali are counted). |
| SITE-2 | Still fixed | `/properties?listing_type=for-sale`: main nav Properties is `is-trail`, "For sale" is `aria-current="page"`. Detail page: the listing item is `is-trail`. Footer "Properties" is also `aria-current` on the filtered URL: see R3-frontend-1. |
| SITE-3 | Still fixed | `/nope-xyz` 404 has `noindex`, no canonical. `/properties?page=2` title "(page 2)", prev link goes to `/properties` (no `?page=1`). Journal BlogPosting `author` is the Person (Maarten de Vries). `/about/` and `/index.php/about` 301 to clean URLs. Sitemap has 78 hreflang alternates. |
| SITE-4 | Still fixed | `public/build/assets` has 8 `.woff2` and 0 `.woff`; the fonts CSS only references `.woff2`. |
| SITE-5 | Still fixed | Footer "© 2026 PT Lumina Estates Indonesia" appears once. Newsletter reads "Monthly market notes, in your inbox" / "One considered email a month". Cards show "IDR 2.5 billion". Rented home shows "Last asking rent". |
| CACHE-1 | Still fixed | `?reply_to=1` on a journal post is not kept (no `X-Cache-Status`), shows "Replying to …" and `parent_id=1`. The plain URL right after is MISS then HIT with no `parent_id` or "Replying to". |
| CACHE-2 | Still fixed | In-process warm MISS: journal listing 22 queries, topic term 24, search `?q=villa` 26 (was 61, 69, 107/45). |
| CACHE-3 | Still fixed | In-process warm MISS: home 21 (was 121), journal post 34 (was 91), search 26. |
| CACHE-4 | Still fixed | Live `PageCacheProfile::useCacheNameSuffix()` = `v53-46cff88c5cfe`, which includes the Vite manifest hash (`46cff88c5cfe`). A real `npm run build` was not run on the shared server (round 1 simulated it). |
| CACHE-5 | Still fixed | `/about?page=7771`, `/about?page=7772`, `/?q=x1`, `/properties?page=123456` (404), `/properties?bedrooms_min=abc`, `/properties?price_min=1234567`, `/about?bedrooms_min=5`: not kept (MISS both times or no header). `/properties?page=2` kept (MISS then HIT). `utm_*` shares the key. |
| CACHE-6 | Still fixed | `/properties/seminyak-beachside-villa` warm MISS: 32 queries (was 56). |
| CACHE-7 | Still fixed | Warm `/properties` dump: one `count(*)` and one `select` on entry translations for the filter; no CMS `$entries` pagination query. |
| CACHE-8 | By design | Five cookieless kept hits added 5 `sessions` rows; responses still set `XSRF-TOKEN` and session cookies and `Cache-Control: no-cache, private`. Documented as by design in round 2. |
| CACHE-9 | By design | Admin timings not re-measured this round; the cause (framework boot, client JS, blocked font) is unchanged. |
| CACHE-10 | Still fixed | Warm `/contact`: 10 queries (was 9): sessions, cache, entries, collection, translations and one `sunrice_media` lookup for the page image. No globals, blueprint, fieldset or form queries. |
| FORM-1 | Still fixed | Kept `/about`: an instant POST gets a blank 200 (refused); 3 s later the same visitor's POST is accepted (302). Honeypot names and `valid_from` differ per visitor. |
| FORM-2 | Still fixed | Contact with empty required fields (noValidate): lands on `/contact#form-contact-errors`, focus on `form-contact-errors`, summary in view. Valid send (below): lands on `#form-contact-status`, focused, thank-you text. |
| FORM-3 | Still fixed | Seven invalid contact posts in a row (bad email): all 302, none 429. (The limit is still per IP, which round 2 accepted.) |
| FORM-4 | Still fixed | Valuation `photos` with a 3.4 MB file: "One of the Photos files is larger than 2 MB, the most this site accepts for each file." in the summary, focus on the summary. |
| FORM-5 | Still fixed | Signed unsubscribe link with a tampered signature: 403; unsigned: 403. The GET handler only renders (`CommentController::unsubscribe`); only the POST writes. Not reproduced live with "Stop these emails": all 3 comments already have `notify_replies=0`, and I created no comment. |
| FORM-6 | Still fixed | Mail for the QA3 contact message (laravel.log): "sent the Contact form in English, from …/contact", empty phone omitted, link to the Contact submissions in the admin. Inquiry with `2020-01-01`: refused, "The Preferred viewing date must be today or a later date." The date input has `min="2026-10-21"`. |
| A11Y-1 | Still fixed | `/properties`: focus on the listing-type select, two ArrowDowns: 0 page loads, focus stays on the select. |
| A11Y-2 | Still fixed | `/admin`, `/admin/settings/cache`, the Agents entries list and `/admin/media`: one `<main>` each. |
| A11Y-3 | Still fixed | Mobile 390 px, menu open: Tab cycles through the header links, phone, skip link, logo, EN/ID, Menu and then the browser's body. It never reaches `main` or `footer`. |
| A11Y-4 | Still fixed | `/id/properti/signature-apartment-kebayoran-baru` (the new path; `/id/properties/...` 301s): `<html lang="id">`, English text in `lang="en"` wrappers, `og:locale` en_US. |
| A11Y-5 | Still fixed | `/properties`, `/journal`, `/team`, `/neighbourhoods`, `/location/bali`, `/journal/topic/market-reports`, `/property-type/villa`, `/search?q=villa`: no heading skips (h1, h2, h3). |
| A11Y-6 | Still fixed | Agents reorder, keyboard: "Anindya Wirawan moved below Sekar Ayuningtyas, position 2 of 4." then Esc: "Moving Anindya Wirawan was cancelled; it's back in its place." Nothing saved (no activity record). Handle labelled "Move …". |
| NEW-frontend-1 | Still fixed | Same test as FORM-4: "One of the Photos files is larger than 2 MB…", the field is named, no "The * failed to upload.". |
| NEW-frontend-2 | Still fixed | Same as FORM-2: errors focus `#form-…-errors`. |
| NEW-frontend-3 | Still fixed | Valuation error summary width 685 px = form width; "Name" left edge = form left edge (579 px). |
| NEW-frontend-4 | Still fixed | Warm `/journal` 22 queries and topic 24: the term translations and taxonomies load in batches (no per-term lookups). |
| NEW2-front-1 | Still fixed | `/properties?page=123456` (404) head matches a normal 404: `robots noindex`, no canonical, no `hreflang` alternates, no CollectionPage. |
| NEW2-front-2 | Still fixed | Cold-site-cache `/contact`: 42 queries (was 61 in round 2); the per-entry-link `where id = ?` lookups are gone. |
| NEW2-front-3 | Still fixed | Valuation with a `.php` file: "Photos: files of this type can't be sent (accepted: JPG, JPEG, PNG, WEBP)." (names the field, same wording style as the size message). |

Counts: Still fixed 32, Regressed 0, By design 2 (CACHE-8, CACHE-9), Not tested 0 (FORM-5 live GET and the page-cache "off" switch are noted above).

## New findings

- R3-frontend-1 (low): Footer "Explore" menu marks "Properties" `aria-current="page"` on filtered listing URLs, e.g. `/properties?listing_type=for-sale`. The main menu, which SITE-2 fixed, only puts Properties on the trail there, so the two menus disagree. Steps: `curl -s 'http://127.0.0.1:8200/properties?listing_type=for-sale' | grep 'aria-current'`. Expected: as the main menu, only "For sale" is current and Properties is on the trail (or the footer item follows the same rule). Actual: footer `<a href="…/properties" aria-current="page">Properties</a>` as well as "For sale" in the main menu. Screenshot: none (markup evidence).

## Notes for cleanup

- One stored submission: contact message "QA3 Contact Check" (qa3-contact@example.test), sent from `/contact`. All other form posts this round were validation or spam refusals and stored nothing. Its email went to the log driver.
- Settings → Cache: saved twice (exclusion and skip, then restored). The `cache` settings row now exists with default values; before it was absent.
- Cache clears and a preload were run (activity log entries "cleared" and "preloaded").
- Uploaded test files (`QA3-test.php`, `big-QA3.jpg`, `note-QA3.txt`) were only attached to forms that refused them; nothing was stored.
