# Re-test: editor (ED-1 .. ED-18)

Tested 2026-10-21 against http://127.0.0.1:8200 as Budi (editor), Citra (author), Eko (Listings manager) and Ayu (only for Settings → Site and the lock take-over), Playwright 1440×900. Scripts: `qa/scripts/retest-editor-*.mjs`. Screenshots: `qa/retest/screenshots/`.

**Before testing: the site was serving the pre-fix admin JavaScript.** `public/vendor/sunrice-admin` and `public/vendor/sunrice-cms` were dated Oct 20 16:22 (`app-Dx8uQKha.js`, cms `admin.js` 184 KB), while the packages' `dist/` were rebuilt Oct 21 01:48 (`app-BKj3VjPD.js`, cms 220 KB). On my first run, ED-10 looked "not fixed" because of this. I ran the documented update steps in the site, `php artisan sunrice:assets` and `php artisan vendor:publish --tag=laravel-assets --force`. Nothing in /home/user/sunrice changed. Every result below is on the current assets. See NEW-editor-2.

| id | status | evidence (screenshot / what you saw) | note |
| --- | --- | --- | --- |
| ED-1 | fixed | `ED-1-menu-after-three-saves.jpg`. On `main` (nested: Properties › For sale/For rent/Villas), I renamed a child and saved, then reloaded and saved unchanged twice more. The DB had 7 rows after every save, and the site showed the renamed label without duplicates. | I put the label back and the rows matched the export taken beforehand (ids change on save; content was identical). This ran before the coordinator restored the real menus. |
| ED-2 | fixed | `ED-2-page-created-with-parent.jpg`, `ED-2-leave-warning.jpg`, `ED-2-parent-saved-with-ctrl-s.jpg`. Parent can be set on create (parent_id=About). Changing it marks "Unsaved changes", and leaving shows an in-app "Leave without saving?" dialog. Ctrl+S saved parent=Services, and Publish saved parent=About. | |
| ED-3 | fixed | `ED-3-citra-author-picker-focus.jpg`, `ED-3-citra-author-picker-rafael.jpg`, `ED-3-eko-agent-picker.jpg`. Citra's Author picker lists the 4 agents ("Rafael" finds Rafael Hartono), and Related finds "Seminyak Beachside Villa". Eko's Agent and Neighbourhood pickers list agents and neighbourhoods. The values were saved (`author_agent: 2, related: [20]`) and shown to both Citra and Budi. | |
| ED-4 | fixed | `ED-4-pages-reorder-tree.jpg`. Reorder shows the tree, indented, with a hint ("To put one under another, choose its Parent"). Dragging a child reordered it among its siblings only (DB checked). A drag toward a top-level row kept it inside its level. | No drag-to-nest, by design, and the hint explains it. I agree. |
| ED-5 | fixed | `ED-5-palette-dashboard.jpg`, `ED-5-palette-media-id-title.jpg`. Ctrl+K on `/admin`: "Menteng" lists 4 entries. On `/admin/media`, the Indonesian title "Cagar Budaya" finds "Rumah Cagar Budaya Menteng · Properties · ID". | |
| ED-6 | fixed | `ED-6-schedule-dialog.jpg`, `ED-6-schedule-picker.jpg`, `ED-6-past-date.jpg`, `ED-6-scheduled-header.jpg`. The picker opens on October 2026 with a 24h time field (80px, "Time (24h)"). A past date changes the button to "Publish now, back-dated" with an explanation. After scheduling, the header reads "Scheduled for Nov 10, 2026, 09:30", and ▾ offers Change the date… / Publish now / Unschedule (back to draft). Unschedule worked. | Toast wording: see NEW-editor-5. |
| ED-7 | fixed | `ED-7-status-card-date.jpg`, `ED-7-date-changed.jpg`. A published journal post has a Date field in Status with "Change the date". I set 1 Sep 2026: the toast said "Date changed.", the DB `published_at` became 2026-09-01, and /journal shows "1 Sep 2026". | Later note: this date-only control is gone; the publish date now follows main (the Status card's **Publish date** field: publishing uses that date, a future one schedules). |
| ED-8 | fixed | `ED-8-revisions-card.jpg`, `ED-8-revision-compare.jpg`. Each revision shows the date, "Budi Santoso" and "Changed: Intro". Compare shows a field-by-field diff with the draft ("Intro: — / Intro changed…"). | |
| ED-9 | fixed | `ED-9-home-blocks-collapsed.jpg`, `ED-9-property-features-rows.jpg`. Blocks and rows are titled by their kind and first text ("Hero · Homes of quiet distinction", "Feature 1 · Freehold title…"). They collapse one by one or with Collapse/Expand all (Home page: 5,080 → 1,668 px). The switch now reads "Visible on site". | |
| ED-10 | fixed | `ED-10-menu-out-a-level.jpg`. "Out a level" on For rent moved it after Villas, at top level. Villas stayed under Properties. | Not saved; I reloaded to discard. |
| ED-11 | fixed | `ED-11-validation-testimonial.jpg`. Inline server errors under fields ("The Quote field is required.", "must not be greater than 5"). The title error clears when you type. Counters show for max lengths (Summary "114 / 300", Excerpt "0 / 240"). | The toast stays as a summary ("(+1 more)"), which is fine. |
| ED-12 | fixed | `ED-12-budi-make-homepage-button.jpg`, `ED-12-settings-homepage-searchable.jpg`. Budi has "Make this the homepage" in a live page's Status card (Settings still 403 for him). Settings → Site is a searchable select of live entries grouped by collection ("Contact" → "Pages: Contact"), with no drafts. | Settings still lists all 44 live entries with a URL (agents, posts, properties), not only Pages. Acceptable, but see NEW-editor-3. |
| ED-13 | fixed | `ED-13-trash-confirm.jpg`, `ED-13-trash-toast-undo.jpg`, `ED-13-journal-trash-filter.jpg`. The button reads "Move to trash", and the toast "Moved to the trash. Undo" restores. `?status=trash` works from the URL. Rows show "Trashed" and have their own Restore. | Lock-release 403: NEW-editor-4. |
| ED-14 | fixed | `ED-14-parent-picker-on-focus.jpg`. Focusing a picker lists entries straight away: Parent (12 pages), Author (4 agents), Agent and Neighbourhood for Eko. | |
| ED-15 | partly fixed | `ED-15-citra-draft-header.jpg`, `ED-15-citra-submitted.jpg`, `ED-15-budi-dashboard-widget.jpg`, `ED-15-budi-opens-review.jpg`. Citra has "Submit for review". After sending, it shows "Waiting for review" and "Sent for review by Citra Lestari". Budi's dashboard widget lists it with a Review link, and Budi got an email. | **The email body is broken** (NEW-editor-1): instead of "Citra Lestari asks you to review…" it prints Citra's whole user record as JSON. "No send back" was left on purpose; I agree for now. |
| ED-16 | partly fixed | `retest-props.csv` (scratchpad). Map is now the URL, rich text is plain text, and the columns read "Status" / "Status (field)". | Still raw: **term fields export ids** (`Property type = 5`, `Amenities = 8, 9, 11, 12, 16`), and **repeaters export JSON with internals** (`[{"_id":"01M5…","_key":null,"label":"…","_hidden":false}]`). Rich text joins headings and list items without breaks ("…shared parking. The space 140 m² open floor with mezzanine Glass frontage…"). |
| ED-17 | partly fixed | `ED-17-agents-list-paths.jpg`, `ED-17-properties-list-badges.jpg`, `ED-17-price-echo.jpg`, `ED-17-used-by-labels.jpg`, `ED-17-draft-preview-ribbon.jpg`, `ED-17-lock-take-over.jpg`, `ED-17-lock-taken-over-other-side.jpg`, `ED-17-submissions-list.jpg`. **Fixed:** real paths in lists (`/team/…`, `/properties/…`); readable badges ("ID missing"); price echo "95,000,000,000"; a single "None" in selects (none where there's a default); testimonials have no Preview; a "DRAFT – Preview of the draft" ribbon; "Take over" with confirm, and the other editor is told; internal rich-text links get `rel="noopener noreferrer"`, no nofollow; media search runs without Enter. | **Not done, as stated:** the rich-text link dialog can't pick an entry; replies only under your own name; submissions have no unread state, assignee, topic or message columns. "Used by" shows humanized handles, not labels ("Author Agent" for the field labelled "Author"). Listing pages and globals go live on Save, and locks expire rather than closing with the browser: by design, and I agree. |
| ED-18 | fixed | `ED-18-term-select-value.jpg` (Location shows "Menteng", not "— — Menteng"), `ED-18-columns-menu-open.jpg` (stays open after a toggle), `ED-17-properties-list-badges.jpg` (filter labels not truncated at 1440), `ED-18-duplicate-slug.jpg` (renaming a copy remakes the slug: `retest-ed18-renamed-duplicate`), `ED-18-sidebar-after-stay.jpg` (in-app leave dialog; after Stay the sidebar keeps "Entries"), `ED-18-media-picker-selected.jpg` (thick ring and check mark), `ED-18-id-shared-fields.jpg` (one "Shared by all languages: the greyed-out fields are edited in English." note). | |

## NEW findings

### NEW-editor-1: The "Waiting for your review" email shows the requester's whole user record as JSON
- Severity: high
- Area: editor UX / security
- Where: mail sent by Submit for review; `packages/cms/src/Mail/ReviewRequested.php`, `resources/views/mail/review-requested.blade.php`
- Steps: 1. As Citra (author), create a journal draft and press Submit for review. 2. Read the mail to budi@ in `storage/logs/laravel.log` (log mailer).
- Expected: "**Citra Lestari** asks you to review **…** (Journal)…"
- Actual: `<strong>{"id":3,"name":"Citra Lestari","email":"citra@lumina-estates.test","email_verified_at":null,"created_at":…,"roles":[{"id":4,"name":"author",…,"pivot":{…}}]}</strong> asks you to review …`
- Note: `$requester` is a public constructor property of the Mailable. Laravel passes public properties to the view after `with`, so the Model overrides the `'requester' => $name` string. Every review email has this bug, and it exposes account data (email, roles, ids) to every reviewer. Fix: rename or privatise the property, and add a test that renders the mail.

### NEW-editor-2: After the package update, the site kept serving the old admin assets, with no warning
- Severity: medium
- Area: install / deploy
- Where: `public/vendor/sunrice-admin`, `public/vendor/sunrice-cms`
- Steps: 1. Update the path-symlinked packages and rebuild their `dist/`. 2. Run migrations, `sunrice:roles`, clear caches. 3. Open the admin.
- Expected: the new UI, or a notice that the published assets are older than the installed package.
- Actual: the panel kept loading `app-Dx8uQKha.js` and the old cms `admin.js` (Oct 20). ED-10 and other UI fixes looked "not fixed" until `php artisan sunrice:assets` and `vendor:publish --tag=laravel-assets --force` were run. With path or symlinked packages, `composer update` doesn't run, so the documented `post-update-cmd` hook never fires (the site's composer.json also lacks the `sunrice:assets` line the docs suggest).
- Note: a cheap guard: compare the published manifest hash with the package's and show a warning to admins (or in `sunrice:install`/`about`).

### NEW-editor-3: "Make this the homepage" sits on every live entry with a URL and switches the homepage in one click, with no confirm
- Severity: medium
- Area: editor UX
- Where: Status card of any published journal post, property, agent or neighbourhood (`Editor.tsx` line ~385; `can` = collection has a route + `cms.homepage`)
- Steps: 1. As Budi, open a published journal post. 2. Status card: "Make this the homepage".
- Expected: offered on Pages (or behind a confirm saying what changes).
- Actual: a plain button next to the Date field. One click POSTs `/homepage` and the site's `/` becomes a journal post. I checked this in the code and did not click it, so the live homepage stayed as it was.

### NEW-editor-4: Moving an entry to the trash leaves its edit lock (403 on release)
- Severity: low
- Area: editor UX
- Steps: open any entry and use Move to trash.
- Actual: `POST /admin/locks/cms.entry/{id}/release` → 403, and a console error. Seen for every entry trashed (51, 52, 54, 55). The lock likely stays until it expires, so after Restore or Undo the entry may open as locked.

### NEW-editor-5: Scheduling messages: Unschedule says "Taken off the site", and the toast uses a 12-hour clock
- Severity: polish
- Area: editor UX
- Actual: Unschedule on a never-live entry toasts "Taken off the site. The draft is kept." The Schedule toast reads "Scheduled for Tue, Nov 10, 2026 9:30 AM." while the header and picker say "09:30" (24h).

### NEW-editor-6: The menu editor's filter placeholder suggests a property filter on every listing link
- Severity: polish
- Area: editor UX
- Where: `/admin/cms/menus/main`, Listing items (`ED-10-menu-out-a-level.jpg`)
- Actual: Neighbourhoods, Agents and Journal listing links all show the placeholder "Filters (optional): listing_type=for-sale".

## Editorial task (footer global): done
I edited `footer` through the UI as Budi. `legal` (en and id) lost "© 2026 PT Lumina Estates Indonesia.", and `newsletter_heading` is "Monthly market notes, in your inbox" / "Catatan pasar setiap bulan, langsung ke kotak masuk Anda". The DB values now equal `database/content.json` exactly. The site shows them at `/` and `/id`: `TASK-footer-en-saved.jpg`, `TASK-footer-id-saved.jpg`, `TASK-footer-site-en.jpg`, `TASK-footer-site-id.jpg`. A first scripted attempt dropped too few characters, leaving "donesia. Licensed…" live for about a minute. That was my keyboard automation, not the app, and I corrected it right away.

## Cleanup
- I deleted the test entries for good, through the trash (51 page, 52 post, 54 Citra's draft, 55 duplicate). None are left in the DB or on /journal.
- Main menu: tested and put back as I found it (7 rows). The coordinator has since restored all three real menus from the pre-import export (11/8/2 rows).
- Side effect: "Our story" `sort_order` changed 4 → 1 after the sibling drag test. Its position among its siblings is the same.
- Republishing the admin assets (see the note at the top) is the only environment change.

## What works well now
The editor's day is much smoother. Parents save with the page, and the leave dialog is in-app. Pickers work for scoped roles and list entries on focus. Reordering shows a tree. Scheduling has a real "Scheduled for…" state with change/unschedule and back-dating. Dated posts have a Date field. Revisions name who changed which fields, with a compare. Long pages fold. Trash has Undo and a row-level Restore. The palette works on every page and in both languages. The lock take-over is clear on both sides. Nested menus finally save cleanly.
