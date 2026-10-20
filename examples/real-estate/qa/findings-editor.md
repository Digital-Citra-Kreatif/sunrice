# Findings: editor

Tester hat: **editor**. Signed in as **Budi Santoso** (editor role) for everything, except: Settings → Site → Homepage (Budi gets 403, done as **Ayu**), the lock tests (Budi + Ayu in two browser contexts), and the author test (**Citra**). Everything was done through the admin UI with Playwright (`qa/scripts/editor-*.mjs`, helpers in `editor-lib.mjs` / `editor-content.mjs`); screenshots are `qa/screenshots/editor-NN-*.png`. The database was only read (to confirm what a screen did), never written.

## Content that now exists

| What | Count | Notes |
| --- | --- | --- |
| Agents | 4 | Anindya Wirawan, Sekar Ayuningtyas, Rafael Hartono, Maarten de Vries (in that order, set by drag-and-drop) |
| Neighbourhoods | 5 | Kemang, Menteng, Pondok Indah, Canggu, Ubud; **Kemang and Ubud translated** |
| Properties | 16 | 15 live + 1 draft (Villa Tirta). Houses, villas, apartments, land and commercial, for sale and for rent, Jakarta and Bali. One **Sold**, one **Under offer**, two **Rented**. 4 featured. **6 translated** (Arunika, Kemang Garden House, Menteng Heritage, Sayan Ridge, Pondok Indah Golf-view, Kemang Townhouse). Villa Arunika Dua made with **Duplicate**. Pondok Indah Garden Penthouse was **scheduled** and went live by itself at 17:19. Arunika's price was changed after publishing, an old revision restored and the restore undone. Berawa retail was trashed and restored |
| Testimonials | 4 | |
| Journal posts | 6 | Dated 1 Jun – 7 Oct 2026, each with author agent, topics and related properties. One written by **Citra** (she could not publish; Budi completed and published it). **2 translated** |
| Pages | 12 | Home (hero, stats, featured properties, image-text, team, testimonials, CTA), About › Our story, Services › Buy / Sell / Rent / Property management, Sell with us, Contact, Privacy policy, Terms. Home set as homepage. **Home, About, Contact translated**. Terms hidden from search engines |
| Listing pages | 2 × 2 languages | Properties and Journal intro + SEO, EN and ID |
| Globals | 3 | Company, Social (Instagram, LinkedIn, Facebook, YouTube), Footer; translatable fields in ID |
| Menus | 3 | `main` (11 items, nested, ID labels), `footer` (8), `legal` (2) |
| Terms | all | Indonesian titles for every term (needed before term SEO can be edited); SEO for Villa and Canggu in both languages |
| SEO cards | 5 entries + Terms noindex | Arunika (EN+ID), Kemang Garden House (EN+ID), About, Contact, Bali buying guide |
| Comments | 2 | One approved + an editor reply, one marked spam |
| Submissions | 1 | Contact form, viewed in the admin |
| Media | unchanged | 5 test images uploaded, used, then deleted for good; test folder deleted. Wanted photos are listed in `qa/unsplash-wanted.md` ("From the editor") |

---

## Critical

### Saving a menu that has nested items duplicates the menu
- Severity: critical
- Area: admin UI
- Where: `/admin/cms/menus/main` (also happened with the admin's earlier `main`)
- Steps: 1. Build a menu with at least one child item (e.g. Properties › For sale) and press Save. 2. Reload. 3. Press Save again without changing anything. 4. Reload.
- Expected: The same 11 items.
- Actual: 11 items after the first save, **18 after the second unchanged save**. The PUT request is correct (it sends 11 items), but the old rows are only partly deleted: in `sunrice_menu_items` the old first top-level item and its children are gone, while the other old top-level items (and children with `sort_order` 0 elsewhere) stay next to the new copies. My first real attempt left the live main menu with Neighbourhoods, Team, Journal, About, Our story, Services and Contact twice each (the Indonesian label on one copy only). A flat menu (footer, admin's 5 items) cleared in one save, so nesting seems to trigger it. `SaveMenu` does `$menu->items()->delete()` on a relation that has `orderBy('sort_order')->orderBy('id')`, with `parent_id` cascading on delete. Ordered deletes plus self-cascades are a plausible cause.
- Screenshot: qa/screenshots/editor-81-menu-main-saved.jpg (duplicates), editor-83-menu-main-built.jpg (clean rebuild)
- Note: Workaround: remove every item, save, reload, and repeat until the menu is empty (two rounds for `main`). Then build the whole menu and save **once**. The menus are clean now, but the next time an editor saves `main` (to rename a label, say) it will duplicate again.

## High

### Page parent is saved only by a separate "Save settings" button and is lost without warning
- Severity: high
- Area: editor UX
- Where: any page → right column "Page settings" → Parent
- Steps: 1. Open "Our story". 2. Pick "About" as Parent. 3. Press Save draft, Ctrl+S or Publish changes, or leave the page.
- Expected: The parent is saved with the page, or the editor is warned before leaving.
- Actual: A "Save settings" button appears inside the card, and only that button saves the parent. Save draft, Ctrl+S and Publish save everything else and quietly drop the parent. Leaving the page shows no unsaved-changes warning for it, although the main form does warn. Parent also can't be set while creating a page (the card only exists after Create draft), so every child page needs create → save → pick parent → Save settings. All six of my child pages had lost their parent before I noticed.
- Screenshot: qa/screenshots/editor-57-parent-picked.jpg, editor-55-pages-tree.jpg
- Note: The header button reads "✓ Published" (disabled) while the settings change is still unsaved, which suggests nothing is pending.

### The author role can't fill the Author or Related properties fields ("No entries match.")
- Severity: high
- Area: editor UX / structure
- Where: Journal → new post as Citra (author role, journal only)
- Steps: 1. Sign in as Citra. 2. New journal post. 3. Type "Rafael" in Author or "Seminyak" in Related properties.
- Expected: The agents and properties to link to, or a clear note that she can't link them.
- Actual: The picker says **"No entries match."** (`/admin/cms/entry-picker?search=Rafael&collections[]=agents` returns `{"items":[]}`) because the role can't view agents or properties. An author can't fill the fields the blueprint expects, and the message suggests the agent doesn't exist. Budi had to finish the post.
- Screenshot: qa/screenshots/editor-45-citra-author-picker-empty.jpg
- Note: Pickers should list any entry the user may *link to*, or the role editor should explain it. There is also no "ready for review" or notification step (see "No review step for authors").

### Reordering a hierarchical collection scrambles children among unrelated pages
- Severity: high
- Area: editor UX
- Where: Pages → Reorder
- Steps: 1. Pages with About › Our story and Services › Buy, Sell, Rent, Property management. 2. Reorder → drag Home to the top → Done.
- Expected: A tree: top-level pages ordered, children under their parent, and ideally drag to nest.
- Actual: A flat list sorted by `sort_order` across all levels. After the drag it reads Home, About, Our story, Services, Buy, **Sell with us**, Sell, **Contact**, Rent, **Privacy policy**, Property management, **Terms**. Children are interleaved with top-level pages because siblings are numbered per level, while the list mixes levels. Nesting can't be changed by dragging. Only the small "under Services" hint shows the hierarchy.
- Screenshot: qa/screenshots/editor-62-pages-after-drag.jpg
- Note: The order on the site is still right per level. It's the editor's view that is misleading, so you can't see what you are ordering.

## Medium

### Command palette finds entries only on CMS pages, not on the Dashboard
- Severity: medium
- Area: editor UX
- Where: Ctrl+K on `/admin` and `/admin/media`, compared with `/admin/cms/entries`
- Steps: 1. On the Dashboard press Ctrl+K and type "Menteng".
- Expected: Menteng Heritage Residence, Menteng Park Apartment, the neighbourhood…
- Actual: **"Nothing found."** No search request is sent. The same keystrokes on any `/admin/cms/...` page call `entry-picker?search=Menteng` and list 4 entries. The Dashboard is where an editor most naturally presses Ctrl+K. Searching by an Indonesian title ("Rumah Taman") found nothing either.
- Screenshot: qa/screenshots/editor-107-palette-search.jpg
- Note: Probably the CMS's palette source is loaded only on CMS pages ("plugin JS only where used"). The palette needs it everywhere.

### Indonesian globals start empty; translatable group fields must be retyped
- Severity: medium
- Area: i18n
- Where: Globals → Company → language Indonesian
- Steps: 1. Fill Company in English. 2. Switch to Indonesian.
- Expected: Like entries ("copied from the main language. Translate it, then publish"): a copy of the English values, or a visible note saying the English value is used.
- Actual: Translatable fields are empty: tagline, address, office hours, and the whole Primary contact group (name, phone, email). Nothing says whether the site falls back to English. A phone number and an email are not things you translate, yet they had to be retyped in Indonesian.
- Screenshot: qa/screenshots/editor-73-company-id.jpg
- Note: Partly a modelling choice (the `contact_person` fields were made translatable), but the empty state gives the editor no guidance.

### Scheduling: a scheduled entry shows a "✓ Published" button, and the date picker remembers the wrong month
- Severity: medium
- Area: editor UX
- Where: entry → Publish ▾ → Publish on a date…
- Steps: 1. Schedule an entry for later. 2. Look at the header. 3. Open the dialog on an entry that had an earlier date.
- Expected: A clear "Scheduled for 17:19" state and an action to unschedule or change it. The picker opens on today.
- Actual: The status chip says Scheduled, but the main button is a disabled **"✓ Published"**. The picker opened on September (the entry's old date), and I picked 20 Sep instead of 20 Oct. Because "a date in the past publishes it now", the entry went live at once with a September date. I had to Unpublish and try again. The time input is too narrow ("05:12 PI") and uses a 12-hour clock while the field above shows 24-hour "17:12". The scheduled entry did go live on its own one minute after the scheduled time, as promised.
- Screenshot: qa/screenshots/editor-38-scheduled-state.jpg, editor-33-schedule-picker.jpg, editor-34-schedule-set.jpg
- Note: Picking a past date by mistake publishes immediately, with no confirmation.

### Journal posts have no visible date field
- Severity: medium
- Area: editor UX
- Where: Journal → New entry (dated collection)
- Steps: 1. Try to give a post a date in the past.
- Expected: A date field on the form for a dated collection.
- Actual: No date anywhere on the form. The only way is Publish ▾ → "Publish on a date…" with a past date. That works, but I only found it because the dialog's help text mentions it. Changing a post's date later means Unpublish, then publish on a date again.
- Screenshot: qa/screenshots/editor-40-journal-create.jpg

### Revisions show only title and time: no author, no summary of changes, no compare
- Severity: medium
- Area: editor UX
- Where: entry → Revisions card
- Steps: 1. Change the price of Villa Arunika and publish. 2. Look for the revision with the old price.
- Expected: Who published, what changed (at least which fields), and a compare view before restoring.
- Actual: Each row is "Villa Arunika, Pererenan · Oct 20, 2026, 05:10 PM · Restore". You can only find the right one by restoring and looking. Restoring is safe, though: it goes into the draft, and **"Undo the restore"** brings the previous draft back. That worked well.
- Screenshot: qa/screenshots/editor-28-revisions-card.jpg, editor-26-after-restore.jpg

### Long forms: blocks and repeater rows can't be collapsed and have generic names
- Severity: medium
- Area: editor UX
- Where: Home page (7 blocks, about 5,000 px of form), property Features, neighbourhood Highlights, Home stats
- Steps: 1. Build the Home page.
- Expected: Collapsible blocks/rows titled by their content ("Hero: Homes of quiet distinction", "Feature: 15 m saltwater pool").
- Actual: Every block and row is always expanded and named "Item 1", "Feature 2", "Highlight 3". With 4 stats items, 4 features and 7 blocks there is a lot of scrolling, and you can't scan the order. Each row has a **"Show"** switch, which actually means "show on the site" (`_hidden`). It reads like "expand" and sits where a collapse control usually is.
- Screenshot: qa/screenshots/editor-60-home-saved.jpg, editor-13-neighbourhood-ubud.jpg

### Menu "Out a level" adopts the following siblings as children
- Severity: medium
- Area: editor UX
- Where: Menus → main → For rent → Out a level, then In a level
- Steps: 1. Under Properties: For sale, For rent, Villas. 2. Press "Out a level" on For rent, then "In a level".
- Expected: For rent goes back to where it was.
- Actual: Outdenting For rent made Villas its child (outliner style), and indenting it back kept Villas under For rent. The menu was now Properties › For rent › Villas. Nothing on screen explains this.
- Screenshot: qa/screenshots/editor-80-menu-outdented.jpg

### Validation: stale server errors remain next to native browser bubbles; no length counter
- Severity: medium
- Area: editor UX
- Where: Testimonials → new entry; property summary
- Steps: 1. Create draft with an empty title. 2. Type a title, set Rating 6, Create draft again. 3. On a property paste a 371-character summary and save.
- Expected: One style of validation, errors cleared once fixed, and a counter for limited fields (summary 300, excerpt 240).
- Actual: Step 2 shows "The title field is required." under a filled-in title, plus a native Chrome bubble "Value must be less than or equal to 5." The form never reaches the server, so the old errors stay. Step 3 is only refused on save ("must not be greater than 300 characters", shown twice in the toast), with no `maxlength` and no counter. Negative price and 2.5 bedrooms are blocked by native bubbles only. The inline red messages themselves are clear.
- Screenshot: qa/screenshots/editor-144-validation-rating.jpg, editor-145-validation-summary.jpg, editor-146-validation-numbers.jpg

### Media library accepts a text file named .jpg
- Severity: medium
- Area: security / admin UI
- Where: Media → Upload
- Steps: 1. Upload `qa-test-fake.jpg` (12 bytes of text).
- Expected: Refused: "This isn't a valid image".
- Actual: Uploaded without a word. It shows as a generic document tile called "qa-test-fake" (12 B). Image pickers correctly leave it out. The upload `accept` list also includes `.svg`, `.json`, `.zip` and Office files for everyone with media access.
- Screenshot: qa/screenshots/editor-120-media-huge-upload.jpg
- Note: For the security hat to check that the content type is validated server-side.

### Image alt text and caption exist in one language only
- Severity: medium
- Area: i18n / a11y
- Where: Media → open an image → Title / Alternative text / Caption
- Expected: Alt text per language on a bilingual site (or per use).
- Actual: One alt text and caption, with no language switch. Indonesian pages will get English alt text.
- Screenshot: qa/screenshots/editor-121-media-detail.jpg

### Homepage setting: editors can't set it, and the picker lists every entry of every collection
- Severity: medium
- Area: editor UX
- Where: Settings → Site → Homepage
- Steps: 1. As Budi open /admin/settings: 403. 2. As Ayu open the Homepage select.
- Expected: A content editor can choose the homepage. The picker offers pages, with search.
- Actual: Budi can't. For Ayu it is an unsearchable list of 44 options mixing Pages, Properties, Agents, Journal and Neighbourhoods, drafts included ("Properties: Villa Tirta, Penestanan").
- Screenshot: qa/screenshots/editor-63-budi-settings.jpg, editor-65-homepage-set.jpg

### Trash: "Delete" button for a reversible trash, trashed rows say "Published", restore is hidden in the bulk bar
- Severity: medium
- Area: editor UX
- Where: entry → trash icon; Properties → Status: Trash
- Steps: 1. Trash Berawa Boutique Retail Space. 2. Find it and restore it.
- Expected: A "Move to trash" button and an Undo in the toast. A trash view with "Trashed" status and a Restore action on each row.
- Actual: The confirm text is good (it says the entry is linked from a journal post and can be restored), but the button says **Delete**. The toast has no Undo. In the Trash filter the row still shows **Published**. Restore only appears after ticking the checkbox (bulk bar "Restore · Delete for good"). The Trash filter isn't in the URL (`?status=trash` is ignored), unlike the term filters.
- Screenshot: qa/screenshots/editor-95-trash-confirm.jpg, editor-97-properties-trash.jpg, editor-98-trash-selected.jpg

### Term pages: no description field, and SEO is blocked until the term has a title in that language
- Severity: medium
- Area: structure / i18n
- Where: Terms → Property types → Villa (edit page)
- Steps: 1. Open the Villa term page to write an intro. 2. Switch to Indonesian.
- Expected: An intro/description for the term page (the brief asks for term descriptions), editable per language.
- Actual: Only SEO fields. Nothing to write an intro into unless a developer adds fields. In Indonesian the page says "This term has no title in Indonesian yet: give it one in the list first" and hides the form, so I had to rename 37 terms one by one in the list (pencil → type → Save) before term SEO could be written. All terms started "not translated".
- Screenshot: qa/screenshots/editor-86-term-villa-edit.jpg, editor-88-terms-indonesian-list.jpg

### Entry pickers show nothing until you type
- Severity: medium
- Area: editor UX
- Where: property Agent / Neighbourhood, block Team / Testimonials / Featured properties, page Parent, menu Entry
- Steps: 1. Click into "Agent".
- Expected: The (4) agents to choose from, or recent entries.
- Actual: An empty search box. You have to know the name. With 4 agents and 5 neighbourhoods, a list on focus would be quicker.
- Screenshot: qa/screenshots/editor-14-entry-picker-search.jpg

### No review step for authors
- Severity: medium
- Area: editor UX (missing feature)
- Where: Citra's draft
- Expected: "Submit for review", or editors notified about drafts by authors.
- Actual: Citra sees only Save draft and Duplicate. Nothing tells Budi a draft is waiting. The dashboard shows "Drafts" counts per collection but not who is waiting for whom.
- Screenshot: qa/screenshots/editor-44-citra-post-created.jpg

### CSV export: link fields as raw JSON, two "Status" columns
- Severity: medium
- Area: editor UX
- Where: Properties → Export CSV
- Actual: `Map` is `{"url":"https://maps.google.com/?q=…","type":"url","label":null,"new_tab":false}`, rich text is raw HTML, and the header has `Status` twice (entry status and the `property_status` field). In the Columns menu the field is called "Status (field)", but the CSV uses plain "Status". Otherwise the export is quick and complete (18 lines, every field).

## Low

### Entries list shows "/slug" instead of the real address
- Severity: low
- Area: editor UX
- Where: Agents list ("/anindya-wirawan"), Properties list
- Expected: `/team/anindya-wirawan`, `/properties/villa-arunika-pererenan`.
- Actual: Only the slug, with a leading slash, so it looks like a full path.
- Screenshot: qa/screenshots/editor-06-agents-list.jpg

### Language badges in lists are hard to read
- Severity: low
- Area: editor UX / a11y
- Where: every entries list ("ID" chip)
- Actual: Translated = grey filled "ID", not translated = outlined "ID", outdated = "ID ·!" with an orange half-border that renders like brackets. The meaning is only in a native `title` tooltip ("Indonesian: Published, outdated: the main language changed since…"). In the entry header the "Outdated" chip is clear, and saving the translation clears it.
- Screenshot: qa/screenshots/editor-92-list-outdated-badges.jpg

### Prices are raw numbers without separators
- Severity: low
- Area: editor UX
- Where: Price field, list Price column
- Actual: `18500000000` and `95000000` are hard to check by eye (1.85 or 18.5 billion?). The help text "In IDR" is good. A formatted echo ("IDR 18,500,000,000") under the field would prevent costly typos.

### Select fields offer "None" twice, or "None" next to a default
- Severity: low
- Area: editor UX
- Where: Property → Price suffix (None, None, per month, per year); Status (None, Available, …) although the default is Available
- Note: The second "None" is the admin's `none` option. The select adds its own empty option on top.

### Nested term select shows dashes in the chosen value
- Severity: polish
- Area: editor UX
- Where: Property → Location
- Actual: The closed select reads "— — Kemang". The dropdown's dash indentation is fine, but the chosen value should read "Kemang" or "Jakarta › South Jakarta › Kemang".

### "Used by" shows raw keys
- Severity: low
- Area: editor UX
- Where: entry → Used by card
- Actual: "Pages: Home (EN) — sections.2.values.properties". The card is very useful (it also appears in trash confirmations and media details), but the key is developer language. "Featured properties block" would do.
- Screenshot: qa/screenshots/editor-67-translation-form.jpg

### Language called "Bahasa Indonesia" in some places and "Indonesian" in others
- Severity: low
- Area: i18n
- Where: entry language bar says "Bahasa Indonesia"; globals, listing pages, terms and menus say "Indonesian".

### Preview is offered for testimonials (no URL) and shows no "draft preview" banner
- Severity: low
- Area: editor UX
- Where: testimonial → Preview; draft property → Preview
- Actual: Testimonials (route: none) get Preview and open a page that doesn't exist on the site. Previews of drafts look exactly like the live site, with no "Preview of a draft" ribbon. The signed preview links themselves work well, also for a never-published draft.
- Screenshot: qa/screenshots/editor-116-preview-23.jpg, editor-116-preview-26.jpg

### View-only lock mode has no "Take over" button
- Severity: low
- Area: editor UX
- Where: entry locked by another user → View only
- Actual: The banner says "Budi Santoso is editing this. You're viewing it; saving is off." To take over after all you have to reload and pick from the dialog again. A lock also stayed after Citra's browser was simply closed (Budi had to take over a minute later).
- Screenshot: qa/screenshots/editor-111-lock-view-only.jpg

### Internal links in rich text get rel="nofollow"; the link dialog can't pick an entry
- Severity: low
- Area: editor UX / SEO
- Where: rich text → Link → "/sell-with-us"
- Actual: Stored as `<a rel="noopener noreferrer nofollow" href="/sell-with-us">`. nofollow on links within your own site is bad for SEO. The dialog takes a typed address only, while the Link *field* has an "Entry" mode. An entry link would survive slug changes.

### Media search needs Enter, entry search doesn't
- Severity: low
- Area: editor UX
- Where: Media → "Search everywhere…"
- Actual: Nothing happens while typing; Enter runs `?search=portrait`. The entries list and the picker dialog search as you type.

### Columns menu closes after each toggle
- Severity: polish
- Area: editor UX
- Where: entries list → Columns
- Actual: Choosing 3 columns takes 3 openings. The choice is remembered after a reload (good), and so is the sort.

### Truncated filter label
- Severity: polish
- Area: admin UI
- Where: Properties list → "Property types: a…" filter button is cut off at 1440 px
- Screenshot: qa/screenshots/editor-19-properties-list.jpg

### Duplicate keeps the "-copy" slug when you rename the copy
- Severity: polish
- Area: editor UX
- Actual: "Villa Arunika, Pererenan (copy)" / `villa-arunika-pererenan-copy`. After renaming the title the slug stays `-copy` unless retyped. Otherwise Duplicate is excellent: everything, including features, agent and terms, is copied into a new draft.

### Comment replies are posted under the editor's own name only
- Severity: low
- Area: editor UX
- Where: Comments → Reply
- Actual: The reply shows "Budi Santoso · Signed in". There is no option to answer as "Lumina Estates" or as the post's author agent, so I had to sign it in the text.

### Submissions have no handled/unread state
- Severity: low
- Area: editor UX (missing feature)
- Where: Submissions → Contact
- Actual: The list (Sent, Name, Email, Phone) and the detail drawer (all fields, IP, browser, page) are clear, but there is no way to mark an enquiry handled or assign it to an agent. The list doesn't show the topic or the start of the message.

### Listing pages and globals go live on Save, entries go through draft/publish
- Severity: low
- Area: editor UX
- Actual: "Saved. The site shows it now." on globals, listing pages and term SEO. These have no draft, no preview and no revisions, while entries have all three. The toast is honest, but the two models sit side by side.

### Machine translation: no hint at all without a driver
- Severity: low
- Area: i18n
- Actual: As documented, the "Translate from English" button simply doesn't exist without `SUNRICE_TRANSLATE_DRIVER`. An editor can't tell that the feature exists, so a disabled button with "Ask your developer to set up machine translation" would help. All Indonesian text was written by hand.

### Admin screens take about 1.1–1.5 s; the Home page editor 2.2 s
- Severity: low
- Area: performance
- Where: `/admin` 1.5 s, entries list 1.2 s, properties list 1.2 s, Home edit 2.2 s, menus 1.1 s, media 1.1 s (Playwright, to network idle, `php artisan serve`)
- Note: Part of this is the blocked fonts.bunny.net request (already in the admin findings). Create draft takes about 1.5 s per entry. Nothing felt broken, but the editor doesn't feel instant.

### Small things
- Severity: polish
- Area: a11y / admin UI
- Drag-and-drop announcements are dnd-kit's defaults with ids: "Draggable item 47 was dropped over droppable area 36". Titles would help ("Home moved above About").
- The Map link field's input is labelled "Address" for screen readers (the link sub-label), right after the property's own "Address" field.
- After cancelling the native "You have unsaved changes. Leave without saving?" confirm, the sidebar Dashboard item stays highlighted as if active (editor-22-after-ctrl-s.jpg). The warning is a browser `confirm()`, not an in-app dialog.
- Media picker selection is a thin border only, with no tick or number (editor-129-media-picker-selected.jpg).
- Repeated "Shared by all languages: edit it in English." under every read-only field of a translation makes the form long. One note at the top, plus greyed fields, would be enough.

---

## Worst editor friction (summary)

1. **Menus duplicate themselves on save** (nested menus). Recovering took several clear-and-save rounds.
2. **Page hierarchy:** parent is saved separately and silently lost, can't be set at creation, and the reorder view mixes levels.
3. **Authors can't link agents or properties**, and nothing routes their drafts to an editor.
4. **Translating the rest of the site:** Indonesian globals start empty, term SEO is blocked until each term is renamed in the list (37 one-by-one renames), alt text is single-language, and there is no machine translation hint.
5. **Long, uncollapsible forms** with "Item 1/2/3" rows. Hard to scan the Home page.
6. **Scheduling and dating posts:** dates only through a dialog with a misleading picker month, past dates publish immediately, and a "✓ Published" button on scheduled entries.
7. **Ctrl+K doesn't find entries from the Dashboard.**

## What worked well

- **Calm, consistent editor.** Clear toasts ("Added as a draft, copied from the main language. Translate it, then publish."), good empty states, an "Unsaved changes" marker next to the title, and Ctrl+S saves the draft.
- **Drafts on top of published content** with a clear status line ("The draft has changes the site doesn't show yet."). Restoring a revision goes into the draft, and **"Undo the restore"** works.
- **Edit locks are excellent:** a dialog naming who is editing and since when, View only / Take over with confirmation, a notice to the person taken over within about 9 s, and their unsaved edits kept with **"Load into the form"**.
- **Scheduled publishing works**: the penthouse went live by itself at the scheduled minute.
- **Translations:** adding a language copies the content, shared fields are read-only with an explanation, the "Outdated" chip appears when English changes and clears when the translation is saved, and Indonesian slugs and URLs (`/id/properties/rumah-taman-kemang`) work at once.
- **Rich text editor:** Markdown shortcuts (`## `, `- `), Ctrl+B, toolbar with labelled buttons, clean pasted HTML, and a proper link dialog.
- **Drag-and-drop with keyboard support** in agents, pages, menus, repeaters and blocks (Space, arrows, Space works). "Order saved." comes straight away.
- **"Used by"** everywhere: entry sidebar, trash confirmation ("It's used by Journal: … those links will show nothing") and media details, including a warning before trashing an image in use.
- **Duplicate** copies everything into a draft. **Bulk actions** (publish, unpublish, trash, restore, delete for good) and **filters by taxonomy** are reflected in the URL. Column and sort choices are remembered. CSV export has every field.
- **Media library:** multi-upload, folders with move, search, alt text, and a picker limited to images. Trash and delete for good both ask first, and the files were really removed from disk.
- **Comments moderation** (approve, spam, reply, edit) and **submission details** are straightforward, and the dashboard shows waiting comments.
- **SEO cards** on every entry, listing and term page with plain-language help. "Hide from search engines" really outputs `noindex, nofollow`, and the SEO title appears in `<title>`.
