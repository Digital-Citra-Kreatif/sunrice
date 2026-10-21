# Re-test round 2: front and languages (site, caching, forms, multilingual)

Re-tested 2026-10-21 against http://127.0.0.1:8200 (packages at steps 1–3, PRs #69–#71). Roles: a visitor (site, forms, comments), Ayu (super admin: banner, media, settings, menus) and Dewi (translator: adding a language, the new Content → Translations screen). Scripts: `qa/scripts/retest2-front-and-languages-*`. Screenshots: `qa/retest2/screenshots/`. The admin assets were current: `app-hErJGADm.js`, and the published CMS `admin.js` has the same md5 as `packages/cms/dist/admin.js`. I saw no old UI.

The Lumina app already has the example's step-3 template changes: `$seoAuthor`, `cms/partials/form-errors`, `cms/comment-unsubscribe`, `min` on the date field, `altIn()` in the lightbox, the range-filter `options`, and no `Hydrate::together()` in the views.

| id | round-1 status | now | evidence | note |
| --- | --- | --- | --- | --- |
| SITE-3 | partly fixed | **fixed** | JSON-LD on `/journal/living-in-ubud-a-year-in-the-hills`: `"author": {"@type":"Person","name":"Maarten de Vries","url":".../team/maarten-de-vries","jobTitle":"Client Advisor, International Buyers"}`, and `publisher` is still `#publisher`. | `og:image` is still empty because the site has no photos yet (content). |
| SITE-5 | partly fixed | **fixed** | `SITE-5-footer.jpg`: "© 2026 PT Lumina Estates Indonesia" appears once (one `<p>&copy; …`). The newsletter heading is "Monthly market notes, in your inbox" above "One considered email a month". The Indonesian footer is consistent too ("Catatan pasar setiap bulan…"). | The live footer global has been updated. |
| CACHE-5 | partly fixed | **fixed** | `retest2-front-and-languages-cache5.sh`, two visits each. `/properties?page=123456`, `/journal?page=55555`, `/properties?page=3`, `/location/bali?page=99`, `/journal/topic/market-reports?page=99`, `/id/properties?page=999` and `/search?q=villa&page=99` are **404, MISS both times**. `?price_min=1234567`, `?bedrooms_min=9137`, `/about?bedrooms_min=5`, `/about?sort=zzz`, `/properties?sort=zzz`, `?listing_type=nonsense` and `/about?page=7771`: 200, **MISS both times**. Offered values are still kept (MISS → HIT): `?page=2`, `?price_min=2500000000`, `?bedrooms_min=3`, `?sort=price-asc`, `?listing_type=for-sale`. `?bedrooms_min=` (empty) shares `/properties`' copy, and `?q=` is never kept. `?page=abc`, `?page=0` and `?page=-1` aren't kept. | Store flooding is closed. One SEO detail on the new 404 is in NEW2-front-1. |
| FORM-2 | partly fixed | **fixed** | Contact errors land on `/contact#form-contact-errors` with focus on `DIV#form-contact-errors` (in view, `FORM-2-contact-errors-focused.jpg`). Inquiry, newsletter and valuation behave the same (`FORM-2-inquiry-errors-focused.jpg`, `FORM-2-valuation-errors-focused.jpg`, `FORM-2-newsletter-error.jpg`). On valuation, Tab from the summary goes to its first link ("The Name field is required." → `#form-valuation-name`). Thank-you messages land focused on `#form-…-status` (`FORM-2-newsletter-thanks.jpg`). | Contact and inquiry use the site's own notice ("Please check the fields marked below.") without links to the fields. That is the app's choice, and the messages sit under each field. |
| FORM-3 | partly fixed | **fixed** (per-IP: won't fix, agree) | 7 failed contact sends in a row all got 302 (none counted). Valuation: 3 valid sends, then the 4th is refused with "**This form wasn't sent:** You've sent this form several times in a short while. Please wait 47 seconds…; what you typed is kept". Name and address are kept (`FORM-3-limit-notice.jpg`). | The heading no longer says "Please correct the following". forms.md explains why the limit counts per IP. Chosen files are lost, which can't be avoided. |
| FORM-4 | partly fixed (regressed for valuation) | **fixed** | 2 files (8 KB + 3.4 MB) in the multi-file `photos` field give "**One of the Photos files is larger than 2 MB, the most this site accepts for each file.**", in the summary and under the field, with focus on the summary (`FORM-4-multi-file-too-big.jpg`). The hint still says "Accepted: JPG, JPEG, PNG, WEBP, up to 2 MB." | A `.php` file gives "Files of this type can't be sent." This is clear but doesn't name the field (polish, NEW2-front-3). |
| FORM-5 | partly fixed | **fixed** | `FORM-5-unsubscribe-branded.jpg`: the signed link opens "Stop emails about replies?" in the site's layout (header, footer, serif heading, "Back to the page"). `notify_replies` stays 1 after the GET and is 0 after the button. The result page "You're unsubscribed" is also in the site layout (`FORM-5-unsubscribed-branded.jpg`). A tampered signature gives 403. | |
| FORM-6 | partly fixed | **partly fixed** | The inquiry date input now has `min="2026-10-21"`. The email lists each file of a multi-file field with its own admin link and size: "ok.jpg (8 KB) / ok2.jpg (8 KB)" → `/admin/cms/submissions/26/files/photos?index=0` and `index=1`. A past date is still refused server-side. | **Left (app/content):** on the English page the error says "The **Preferred date** field…" under the label "Preferred viewing date". The template takes its label from `lang/en/site.php`, and validation uses the blueprint label. Renaming the blueprint field's label would fix it. Not a Sunrice bug. |
| extra: branding/settings media counted as used | partly fixed | **fixed** | `retest2-front-and-languages-settings-media.php` (rolled back): with the settings usages removed, the upgrade's backfill (`RecordSettingsMedia::all()`, what migration 000013 runs) recreates "2:site:Settings: Site". After removing them again, an **unchanged** Settings → Site save recreates it, and a Branding save with logo = 2 adds "Settings: Branding". The live media details read "Used by Settings: Site" (`extra-settings-media-logo-used.jpg`). | Migration `2026_01_01_000013_backfill_sunrice_settings_media_usages` has run on the live site. |
| NEW-frontend-1 | new | **fixed** | See FORM-4: the field is named, the size is given, and there is no "The * failed to upload." | |
| NEW-frontend-2 | new | **fixed** | See FORM-2: errors redirect to `#form-{handle}-errors`, and that element gets focus. | |
| NEW-frontend-3 | new | **fixed** | The valuation summary is 685 px wide, the same as the form, and "Name" starts at the left edge (`FORM-4-multi-file-too-big.jpg`, `FORM-3-limit-notice.jpg`). The heading for the send limit is "This form wasn't sent:". | |
| NEW-frontend-4 | new | **fixed** | Warm `/journal`: after the terms query, term translations load in one batch (`term_id in (18,19,20,21)`) and taxonomies in one query. There are no per-term `terms where id = ?` or `taxonomies where id = ?` queries. The journal listing went from 30 to **22** queries, and the topic page from 32 to **24**. | The term translations batch runs twice (2 queries, not per term), which costs nothing worth fixing. |
| NEW-editor-2 | new | **fixed** | Live: no banner for Ayu on `/admin`, media, the entries list or settings (`NEW-editor-2-no-banner-ayu.jpg`). **Simulated on a copy of `public/`** (`retest2-front-and-languages-stale.php`, which sets `usePublicPath()` to a scratch copy; the live files are untouched and their md5 was checked afterwards). Identical copy: no banner. CMS `admin.js` changed: "The admin assets published for **cms** are older than the installed packages… run php artisan sunrice:assets and php artisan vendor:publish --tag=laravel-assets --force." Admin manifest changed: "…for **Sunrice**…". Both changed: "Sunrice, cms". The same stale copy shows no banner to Budi (editor). | Polish: the plugin appears by its id ("cms") next to "Sunrice". A title such as "Sunrice CMS" would read better. The check runs on full page loads for super admins only. |
| NEW-i18n-1 | new | **fixed** | As Dewi, property 19 (no Indonesian yet) shows **"+ ID"** (title "Add Bahasa Indonesia"; `NEW-i18n-1-dewi-language-bar.jpg`). Clicking it toasts "Added as a draft, copied from the main language. Translate it, then publish." Dewi translated the title and saved ("Draft saved."). She gets "Submit for review", not Publish (`NEW-i18n-1-dewi-id-draft-saved.jpg`). | The test translation was deleted afterwards. |
| NEW-i18n-2 | new | **fixed** | Dewi's sidebar has **Translations** (`/admin/cms/translations`): site description, listing titles of 6 collections and 5 taxonomies, the 4 forms (thank-you messages, field labels, choices) and the 3 menus, each with the English text beside the input (`NEW-i18n-2-dewi-translations.jpg`). Dewi changed the main menu's Agents label (Tim → "Tim QA") and the inquiry's "Preferred date" label (empty → "Tanggal kunjungan QA"), and the toast read "Translations saved.". `/id` showed "Tim QA" at once, and the `/id` inquiry error used "Tanggal kunjungan QA…". After I put both back, a dump of menu labels, collection/taxonomy translations, forms, form blueprints and site settings was **byte-identical** to the one taken before. | Polish in NEW2-i18n-3. The `/id` inquiry errors also show the English "Name"/"Email": nobody has translated those labels yet, and a translator can do it now. |
| NEW-i18n-3 | new | **fixed** | Rolled back (`retest2-front-and-languages-menu-labels.php`): with the Indonesian label removed, "Team" shows **Tim**, footer "Our team" shows **Tim**, the term item "Villas" shows **Vila**, and "Privacy" (page not translated) stays "Privacy". English is unchanged. In the editor, every listing item's Filters placeholder is "Filters (optional): filter=value", and the Indonesian label placeholder reads "Bahasa Indonesia: its title there, else as English" (`NEW-i18n-3-menu-editor.jpg`). | A side effect for filtered listing links is in NEW2-i18n-2. |
| NEW-i18n-4 | new | **fixed** | Settings → Site → "Description in other languages" now has a labelled "Bahasa Indonesia" input holding the stored Indonesian text (`NEW-i18n-4-site-description-inputs.jpg`). | Polish: the main description is a textarea, but the Indonesian one is a single-line input. |
| NEW-i18n-5 | new | **fixed** | `packages/cms/docs/site.md:15` now reads `app(Sunrice\Cms\Site\Site::class)->urlIn($translation, $locale)`. | |
| NEW-i18n-6 | new | **fixed** | `resources/views/cms/properties.blade.php:94` uses `$m->altIn()`. Rolled back (`retest2-front-and-languages-lightbox-alt.php`): the logo was put in a property's gallery and the page rendered. The lightbox's `data-items` alt is "Lumina Estates logo" on `/properties/…` and "**Logo Lumina Estates**" on `/id/properties/…`, the same as the `<img>`. | |

**Counts:** 20 fixed, 1 partly fixed (FORM-6: an app/content label mismatch), 0 not fixed, 0 regressed. FORM-3's per-IP limit: won't fix (by design), agree.

## Query counts and timings (round 2 vs round 1)

These come from `retest2-front-and-languages-queries.php` (in-process, isolated array caches; a copy of round 1's script). There were two runs, and the counts were the same in both. Counts include 2–3 session and cache queries. TTFB comes from `retest2-front-and-languages-ttfb.sh`: the first visit after `responsecache:clear` (MISS), then the median of 5 HITs. Times are on `artisan serve` without OPcache, with another tester using the server.

| Page | Cold MISS r1 → r2 | Warm MISS r1 → r2 | HIT r1 → r2 | MISS TTFB r1 → r2 | HIT TTFB r1 → r2 | HTML KB r2 |
| --- | --- | --- | --- | --- | --- | --- |
| Home `/` | 46 → 72–74 | 21 → **21** | 3 → 3 | 411 → 256 ms | 159 → 188 ms | 73 |
| Home `/id` | 45 → 77 | 21 → 21 | 3 → 3 | – | – | – |
| Journal post | 67 (58 w/o helper) → 86 | 33 (w/o helper) → **34** | 3 → 3 | 225 → 305 ms | 137 → 148 ms | 59 |
| Journal listing | 51 → 70 | 30 → **22** | 3 → 3 | 217 → 272 ms | 158 → 158 ms | 54 |
| Topic term | 55 → 74 | 32 → **24** | 3 → 3 | 225 → 232 ms | 147 → 142 ms | 47 |
| Search `?q=villa` | 50 (w/o helper) → 77–81 | 26 → **26** | never kept (26) | 194 → 208 ms | 203 → 207 ms | 51 |
| Property (en) | 56 → 83 | 32 → **32** | 3 → 3 | 220 → 250 ms | ~145 → 143 ms | 63 |
| Properties listing | 57 → 84 | 36 → 36 | 3 → 3 | – | – | – |
| Contact | 34 → 61 | 9 → 9 | 3 → 3 | – | – | – |
| `/up` (baseline) | | | | 177 → 139 ms | 123–138 → (noisy, 140–295) ms | |

- Warm MISS, the normal case for a visitor's first request: **equal or better everywhere**. The journal listing (−8) and topic page (−8) are lower thanks to the NEW-frontend-4 fix. The journal post's 34 equals round 1's "without helper" figure, now with no app helper at all. In-process render time is 33–83 ms warm MISS and 4–10 ms HIT.
- Cold site cache: **+19 to +27 queries on every page**. Most of this is because the menus were restored after round 1: the `main`, `footer` and `legal` menus have 11 entry links again (round 1 measured them without About, Services, Contact, Privacy or Terms). Each entry link costs 2 extra queries on a cold build (NEW2-front-2). Menus are kept in the site cache, so this cost is paid only after a content save or cache clear.
- HTTP timings are the same within noise: a HIT is about framework-boot time, and a MISS is 210–305 ms.

## NEW2 findings

### NEW2-front-1: A page past the last keeps the listing's canonical, hreflang and CollectionPage on its 404
- Severity: low
- Area: site / SEO
- Where: `/properties?page=123456` (any paginated listing or term page past its last page)
- Steps: 1. `curl -s 'http://127.0.0.1:8200/properties?page=123456'` and compare its `<head>` with `curl -s http://127.0.0.1:8200/nope-xyz`.
- Expected: The same head as the normal 404: `<meta name="robots" content="noindex">`, no canonical, no hreflang alternates, only the WebSite and Organization graph.
- Actual: Status 404 with the "We can't find that page" body, but the head has `rel=canonical` to `…/properties?page=123456`, hreflang en/id/x-default alternates to `?page=123456` (and `/id/properties?page=123456`), `og:url` to the same, and JSON-LD with a `CollectionPage` and `BreadcrumbList` for `?page=123456`. There's no `noindex`.
- Screenshot: qa/retest2/screenshots/CACHE-5-past-last-page-404.jpg
- Note: Search engines drop a 404 anyway, so the harm is small. But the page tells them a missing page is canonical and has an Indonesian alternate. It comes from the new "past the last page → 404" in `PageNumberWatch`, which aborts after the listing's SEO was already set up.

### NEW2-front-2: On a cold site cache, each menu link to an entry reloads its entry and collection
- Severity: low
- Area: performance
- Where: `Sunrice\Cms\Menus::link()` → `Site::urlIn()` / `isFallbackFor()` (`$translation->entry`, then `->collection`)
- Steps: 1. Count queries on `/contact` with the site cache cold (`retest2-front-and-languages-queries.php --dump=…`). 2. After the batched `entries in (36,37,38,44)` and their translations, look for repeated lookups.
- Expected: The menu's entries load in one batch (they already are), with the translations' inverse `entry` relation set, so building the menu adds no queries per item. This is the same fix NEW-frontend-4 made for the terms component.
- Actual: There's one `select * from sunrice_entries where id = ? limit 1` plus one `select * from sunrice_collections where id = ? limit 1` per entry link: 4 for `main`, 3 for `footer` and 2 for `legal`, so 18 queries, plus the same again for `/id`. A cold `/contact` costs 61 queries.
- Note: The cost is paid only on a cold cache, because menus are kept in the site cache. Every content save flushes that cache, though, so the first visitor after each save pays it on every menu.

### NEW2-front-3: Small wording gaps in the new form messages
- Severity: polish
- Area: site
- Where: `/sell-with-us` (valuation `photos`); the admin's stale-assets banner
- Actual: A file of the wrong type in a multi-file field says "Files of this type can't be sent." without the field's name, unlike the size error ("One of the Photos files is larger than 2 MB…"). It appears in the summary, away from the field. The stale-assets banner names the CMS by its plugin id: "published for Sunrice, cms".
- Note: "Photos: files of this type can't be sent (accepted: JPG, JPEG, PNG, WEBP)." would match the size message.

### NEW2-i18n-1: Indonesian date errors end in the English word "today"
- Severity: low
- Area: i18n
- Where: `/id/properties/kemang-townhouse-for-lease`, inquiry form (`preferred_date`, future only)
- Steps: 1. On `/id`, choose a past preferred date (or bypass `min`) and send.
- Expected: "Tanggal kunjungan harus berupa tanggal hari ini atau setelahnya." (or similar).
- Actual: "Tanggal kunjungan QA harus berupa tanggal setelah atau sama dengan **today**." The rule `after_or_equal:today` puts the raw parameter into Laravel's Indonesian line. English reads naturally ("after or equal to today") only by luck.
- Note: The CMS adds this rule for date fields that are future only, so it should give the rule its own message (in `site.php`, en and id) or translate the `:date` value.

### NEW2-i18n-2: A filtered listing link without an Indonesian label now shows the bare collection title
- Severity: polish
- Area: i18n / menus
- Where: Menus → Main, "For sale" (listing Properties, filter `listing_type=for-sale`)
- Steps: In a rolled-back transaction, remove the Indonesian label "Dijual" from "For sale" and read the `/id` main menu (`retest2-front-and-languages-menu-labels.php`).
- Expected: Something that keeps the item's meaning ("For sale" or a prompt to translate it).
- Actual: The item reads "**Properti**", the same as its parent "Properti". The placeholder ("its title there, else as English") describes this behaviour, but for a link with filters the collection's title isn't the item's meaning. "For sale" and "For rent" would both read "Properti".
- Note: Prefer the main-language label over the target's title when the item has Filters, or flag such items on the Translations screen.

### NEW2-i18n-3: Content → Translations: menu links not in menu order
- Severity: polish
- Area: admin UI
- Where: `/admin/cms/translations` → Menu: Main
- Actual: The links are listed in database order rather than as the menu shows them: Properties, For sale, **Our story**, For rent, Neighbourhoods, **Villas**, Team, Journal, About, Services, Contact. Children aren't indented, and each says only "Link to Properties". So "For sale" and "For rent" show as two "Link to Properties" rows told apart only by their English text.
- Screenshot: qa/retest2/screenshots/NEW-i18n-2-dewi-translations.jpg
- Note: Follow the menu tree (parent › child) and mention the filters ("Link to Properties · listing_type=for-sale").

## Works well now
- All the round-1 leftovers in my area are closed. The post names its real author in structured data, the footer is right, past-last pages and unread parameters are no longer kept, and every form failure lands focused on a summary in view that names the field.
- The page cache keeps exactly the pages a visitor can reach through the UI (offered filter steps, sorts, terms, real page numbers) and nothing else.
- Forms are pleasant on both languages: the send-limit notice says the form wasn't sent and keeps the input, multi-file uploads explain the size limit, emails list every file with a link, and unsubscribing happens in the site's own layout and takes a click.
- Translators are self-sufficient now. Dewi can add a language to an entry and translate menus, form labels, choices, thank-you messages, listing titles and the site description on one screen. Saves change only what she touched: the before/after dump matched byte for byte.
- Menus prefer the target's translated title over an English-only label.
- The stale-assets banner works as described. It shows nothing on the live site, it shows on a stale copy for super admins only, and it gives the exact commands.
- Warm query counts are flat or lower than round 1 with no app helpers, and a kept page costs 3 queries.

## Untested / notes
- The stale-assets banner was checked in-process on a copy of `public/`, not in a browser. I didn't break the live assets, so there's no screenshot of the banner; its exact text is quoted above.
- The Branding logo path was tested only in a rolled-back transaction. The live Branding logo is empty and belongs to another tester's area.
- The lightbox alt was tested by rendering in a rolled-back transaction, because the site has no gallery photos yet (Unsplash is blocked).
- Cleanup (`retest2-front-and-languages-cleanup.php`): 6 submissions (#24–#29, with their uploaded files) and comment #12 (trashed, then deleted) were removed. Dewi's Indonesian draft on property 19 was deleted (property 19 is back to English only). The two Translations-screen edits were put back and verified identical. I changed no settings. `responsecache:clear` was run twice for the TTFB runs.
