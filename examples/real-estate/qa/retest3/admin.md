# Round 3 — admin (install, admin, structure)

Tested 2026-10-21 against http://127.0.0.1:8200 as Ayu (super admin), Playwright 1440×900 plus artisan/tinker. QA3 records were created for the structure checks (blueprint `qa3_blueprint` → renamed `qa3_bp_renamed`, fieldset `qa3_contact`, taxonomy `qa3_topics` with a term, collection `qa3_stories` with an entry, users `QA3 Invite One/Two/Three`, role `QA3 Matrix Role`, media `qa3-wide.svg`). All were deleted at the end; the database has no `qa3` rows left. Settings changed for tests were restored (comments per-minute 3 and close-after 365; branding logo empty). Only one screenshot is kept (failure).

| ID | Result | Evidence |
|---|---|---|
| INS-1 | Still fixed | Guide → Structure has "Setting up a new site, in order", a "Handles" section (no hyphens, the panel makes handles as you type), the reserved list and the Fieldset-flattening paragraph. |
| INS-2 | Still fixed | Tinker, in a transaction rolled back: `SiteSettings->homepage = <other id>; save()` returns `["homepage"]` and fires `SettingsSaved` (section site); a second save returns `[]` and fires nothing. (Did not re-request `/` in the same process; round 1 did.) |
| INS-3 | Still fixed | Collections: "Reorder above the list, or the Move up and Move down arrows". Users: self-service "Your account" paragraph, no "no profile page" line. Security: artisan command under "For developers". Editor role lists menus as content. |
| INS-4 | Still fixed | Docs (`cms/docs/site.md`, `fields.md`): `$richText`, `$draft`, listing `$values` with `?? null`; entries field gives "one EntryTranslation (or null)", terms "one Term (or null)". |
| ADM-1 | Still fixed | Comments: close-after 366 → field shows 366 after save; cleared → field stays empty (no "36512"); 365 restored. Per-minute field also shows the saved value. The 2FA-off stale switch was not repeated: 2FA cannot be turned on with the log mailer (SEC-6), so Security shows the DB value (off). |
| ADM-2 | Still fixed (new uploads) | Uploaded a 208×56 SVG as Logo via the media picker and saved: the sidebar shows the wide logo at its proportions in place of the name; favicon stays the generated `icon.svg`. Backfill of the old `lumina-logo.svg` not re-tested (logo is empty now). Logo restored to empty. |
| ADM-3 | Still fixed | Branding font help: "Every font but System is loaded from Bunny Fonts (fonts.bunny.net)… choose System for an intranet…". |
| ADM-4 | Still fixed | Activity log: Who filter ("Anyone"), before→after rows ("Logo: (empty) → 16"), sentences ("Someone not signed in … password reset for …"). Polish still open as in round 2: the logo diff shows media ids, not names. |
| ADM-5 | Still fixed (partly tested) | Languages (URL is `/admin/settings/locales`, as before): Content languages with Main/id, "Main language" card with "Make Bahasa Indonesia the main language". Validation messages not re-run. |
| ADM-6 | Still fixed | Settings → Site → Structured data: Published by (An organization), Name (empty = site name), Organization type, Phone, Address, Profiles elsewhere. |
| ADM-7 | Still fixed | Settings → Mail: "Mail is sent from hello@example.com, an example address…" and the log-mailer warning. |
| ADM-8 | Still fixed | Recent activity has no alert/refused/token lines. "Getting the site ready" is hidden because every step is done (entries exist, homepage set, all menus have links; Legal now has 2 links). Its visibility rule is in `DashboardWidgets::setup()`. |
| ADM-9 | Still fixed | New user with no password and no invitation → "Type a password, or turn on Email an invitation." Confirm password field present. Invitation: user created (list shows it), mail link opens "Choose your password", setting a password works. Row menu "Email a password link" and "Your account" not re-tested. |
| ADM-10 | Regressed (partial) | Brand colour error: "must be a colour written as # and six hex digits…" ✓. Roles list: Super admin "All" ✓. Comments "Also email roles": display names ✓. BUT Settings → Security → "Also send alerts to these roles" lists raw slugs: `administrator`, `author`, `editor`, `translator` (Listings manager shown by name). Round 2 fixed Comments and Forms only, so this screen was not covered. Screenshot: `retest3/shots/admin-ADM-10.jpg`. |
| STR-1 | Still fixed | `sunrice:export-structure` writes `seo_title_field`, `seo_description_field`, `seo_image_field` (neighbourhoods: description `intro`, image `hero`); forms carry `notify_roles`. Dry-run import of the export: "52 unchanged". |
| STR-2 | Still fixed | Typed `qa3-contact-x` in fieldset handle → `qa3_contact_x`; `qa3-hyphen` in taxonomy handle → `qa3_hyphen`; `qa3-stories` in collection handle → `qa3_stories`; hyphen in a field handle → underscore as typed. |
| STR-3 | Still fixed | Text field labelled "Status": handle `status` shows inline `"status" is used by the content itself…` warning. |
| STR-4 | Still fixed | Fieldset include card: "Its fields are added here, at this level… put this in a Group." Saving a blueprint with its own `phone` next to the included `qa3_contact` fieldset refused with the clash message. |
| STR-5 | Still fixed | Settings → SEO (the picker moved there): for `qa3_stories` the Meta title/description/image pickers offer only its own blueprint's fields (Lead Name), not the number field or other blueprints' fields. |
| STR-6 | Still fixed (improved) | Entries field with no collection ticked: the message `The "From collections" field is required.` now appears inside the field card as well as in the toast. |
| STR-7 | Still fixed | Global set picker: "—, Company, Footer, Social" only. Collection blueprint picker lists entry blueprints only (no forms/global sets). |
| STR-8 | Still fixed | Entries list of `qa3_stories` shows a "QA3 Topics: all" filter that came from the Terms field (the taxonomy wasn't ticked on the collection). |
| STR-9 | Still fixed | Renaming the blueprint handle on the builder (`qa3_blueprint` → `qa3_bp_renamed`) saved and the linked collection followed; hint "Changing it updates the collections, taxonomies, global sets, forms and entries that use it." shown. See R3-admin-2 for the URL after save. |
| STR-10 | Still fixed | Property blueprint page: scrollHeight 1,801 px (was ~16,600), "Open all" button, folded cards. |
| STR-11 | Still fixed | File (forms) "Several files" saved as `{"multiple": true}`. |
| STR-12 | Still fixed | Collection "Structured data type" offers Real estate listing, Person (a profile page), Place, Product or offer. |
| STR-13 | Still fixed | Menu → "Link to an entry", search `zzqqxx`: "No entries match. A menu links to entries that already exist: create the page first (Content → Entries)…". |
| STR-14 | Still fixed | Flexible content, ticked Team, Hero, Call to action: saved as `["team","hero","cta"]` in that order. Existing alphabetical block list on the page blueprint unchanged until re-ticked (known). |
| NEW-admin-1 | Still fixed | Dry-run import: with one Legal link removed from the file, default import reports `legal … unchanged`; `--replace-menus --dry-run` reports `updated: Removes the link to pages/terms.`. Dry runs only; live Legal menu still has 2 links. |
| NEW-admin-2 | Still fixed | Delete collection: "Move QA3 Stories to the trash? You can restore it." Trash row offers only Restore (no 404-prone Delete). Taxonomy trash works the same way. |
| NEW-admin-3 | Still fixed | New user without password or invitation → "Type a password, or turn on Email an invitation." |
| NEW-admin-4 | Still fixed | Invitation page reads "Choose your password"; after saving: "Your password is set. You can log in now." |
| NEW-admin-5 | Still fixed | Bulk Delete… → "Transfer to" options (visible) are Ayu, Budi, Citra, Dewi, Eko: the two users being deleted are not offered. |
| NEW-admin-6 | Still fixed | Field "Show when" list: Lead Name, Phone, QA3 Files, QA3 Topic. No Fieldset include entry. |

Regression checks on the areas changed in steps 5–6:
- Blueprint builder: label → handle auto-fill, typed handle normalised, duplicate ("(copy)", `lead_name_copy`), remove, condition repeater (saved as `{"field":"lead_name","value":"Lo","operator":"equals"}`), translatable switch (saved false). No regressions.
- Taxonomy/term settings: create taxonomy, term create/list, "Terms have pages" and term page URL fields. No regressions.
- List header actions: "New collection / Trash / Columns", "New entry", "New taxonomy", "New role". Present and working.
- Roles permission matrix: group and per-action checkboxes saved; counts persisted (Entries: QA3 Stories 2 of 6, Terms: QA3 Topics 1 of 4). No regressions in save.
- Trash pages (collections, taxonomies, media): trash lists load; restore-only rows; media "Permanently delete" works.

Notes (not findings): the user-create and term-create forms take 2–3 s before they redirect; a shorter wait looks like nothing happened. Another tester's activity appeared in the shared database during the run (a QA3 collection was restored once by my own mis-click, then re-trashed and purged by me). No other testers' data was touched.

## New findings
- R3-admin-1 (low): Security screen lists alert roles by raw slug — "Also send alerts to these roles" shows `administrator`, `author`, `editor`, `translator` while Comments/Forms/Users show "Administrator", "Author", "Editor", "Translator". Steps: Settings → Security. Expected: display names, as on Comments. Actual: slugs. Screenshot: `screenshots/admin-ADM-10.jpg`
- R3-admin-2 (low): After renaming a blueprint handle on the builder and saving, the page stays on the old address (`/admin/cms/blueprints/<old>/edit`); reloading that address gives "Page not found". Steps: open a blueprint, change Handle, Save, reload the page. Expected: the browser moves to `/admin/cms/blueprints/<new>/edit`. Actual: old URL kept, 404 on reload. Screenshot: not taken (behaviour is in the URL only).
