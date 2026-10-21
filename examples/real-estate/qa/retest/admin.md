# Re-test: admin area (INS-1..4, ADM-1..10, STR-1..14, user extras)

Tested 2026-10-21 against http://127.0.0.1:8200 as Ayu (super admin), Playwright 1440×900, plus artisan and a PHP script for INS-2 and STR-1. Scripts: `qa/scripts/retest-admin-*.mjs` and `qa/scripts/retest-admin-ins2.php`. Screenshots: `qa/retest/screenshots/`.
Throwaway records, all deleted afterwards: users `retest-one/two/three@lumina-estates.test`; collections `retest_seo` and `retest_hyphen` (purged with `sunrice:orphans --purge`); form `retest_form`; global set `retest_global(_renamed)`; menus `retest_menu(_renamed)` and `retest_imp`; blueprint `retest_bp(_renamed)`; media `retest-wide-logo.svg` and `retest-owned.png`. Settings I changed for tests are back to what they were: comments close-after 365 and reply notifications on, branding logo empty.

| id | status | evidence (screenshot / what you saw) | note |
| --- | --- | --- | --- |
| INS-1 | fixed | Guide → Structure now has "Setting up a new site, in order" (fieldsets/blueprints of plain fields → taxonomies → collections → Entries/Terms fields → tick collections → globals/menus/forms), a "Handles" section (no hyphens, with examples), the full reserved list, and how a Fieldset field works. | Step 5 ("tick the Collections") contradicts the Taxonomies paragraph, which says a Terms field is now enough (STR-8). Small wording fix. |
| INS-2 | fixed | `retest-admin-ins2.php`, in a transaction that is rolled back: `SiteSettings->homepage = 36; save()` returns `["homepage"]`, fires `SettingsSaved {section: site, changed: [homepage]}`, and `/` in the same process then serves "About". The database still holds 47 afterwards. | docs/settings.md describes this. |
| INS-3 | fixed | Collections: "Reorder above the entries list, or the Move up and Move down arrows". Languages: "Until you save it, the box shows the languages your site was set up with", and the panel really does refuse an empty list. Welcome: "Your account" section plus invitation text; the "no profile page" line is gone. Editor role: menus are listed as content. Security: the artisan command sits under "For developers". | |
| INS-4 | fixed | site.md "Views get" lists `$richText`, `$draft`, and listing `$values` ("only those of the collection's listing blueprint, and empty without one … `?? null`"). fields.md: entries → "one `EntryTranslation` (or null)", terms → "one `Term` (or null)". Hydration of listing/term `$entries` is documented. | |
| ADM-1 | fixed | `ADM-1-comments-366-after-save.jpg`, `ADM-1-comments-switch-after-save.jpg`. Close-after 365→366: the field shows 366 after save. Cleared: the field stays empty after save, and typing gives "12", not "36512". Reply-notifications switch stays off after save. | Couldn't repeat the 2FA-off case: 2FA can't be switched on while the log mailer is in use (SEC-6, `ADM-1-security-2fa-refused.jpg`). The same form reset covers it. |
| ADM-2 | partly fixed | New upload `ADM-2-branding-wide-logo.jpg`, `ADM-2-signin-wide-logo.jpg`: the 208×56 SVG is stored with width/height, replaces the name in the sidebar and on the sign-in page, the preview isn't cropped, and the favicon stays the generated letter. **But** the site's existing `lumina-logo.svg` (uploaded before the fix; width/height NULL) still shows the old bug: `ADM-2-branding-old-logo.jpg` shows it squeezed into the 32 px tile next to the name, and `<link rel=icon>` points to the wide SVG. | No backfill: no command (`sunrice:media-sizes` doesn't) and no upgrade note. Every install that already uploaded a wordmark keeps the bug until the file is uploaded again. |
| ADM-3 | fixed (docs, as proposed) | `ADM-3-branding.jpg`: the Font help says every font but System loads from fonts.bunny.net and to choose System for an intranet or for privacy. The guide (Appearance) says the same. | Fonts aren't bundled. The finding offered this option, and I agree. |
| ADM-4 | fixed | `ADM-4-activity.jpg`. Who filter (Anyone / each user). Settings rows show before→after ("Close after days: 365 → 366", "Reply notifications: on → off", "Logo: (empty) → 11"). Sentences read "Ayu Pratiwi updated settings Comments settings", "Someone not signed in login failed for x@… no account with this email". Security noise is off the dashboard. | Polish left: the logo diff shows media **ids** (11, 2), not names. The type filter has "Collection (collection)" next to "Collections" and "Menu (menu)" next to "Menu settings". Some actions are lowercase internal-looking words ("lock", "unlock", "sign-out") next to sentences ("account locked by admin"). Blueprint updates say only "Fields". |
| ADM-5 | partly fixed | `ADM-5-languages.jpg`, `ADM-5-languages-validation.jpg`. Box prefilled. Validation works: "en is listed twice.", "Line 2: "Bahasa" isn't a language code…", an empty list is refused, and a reordered main language is refused. A "Main language" card has "Make Bahasa Indonesia the main language". | Still a free-text textarea (left on purpose; acceptable). The main-language error still says "run php artisan sunrice:switch-main-language id" although the card below now does it in the panel. The URL is still `/settings/locales`. |
| ADM-6 | fixed | `ADM-6-site-structured-data.jpg`: Name (empty: site name), Kind of organization (LocalBusiness, RealEstateAgent…), Phone, Address, Profiles elsewhere. Homepage JSON-LD has the publisher `Organization` with name and url. | I didn't fill the fields (shared settings). |
| ADM-7 | fixed | `ADM-7-mail-warning.jpg`: "Mail is sent from hello@example.com, an example address: replies go nowhere… Set MAIL_FROM_ADDRESS…", next to the log-mailer warning. | |
| ADM-8 | fixed | `ADM-8-dashboard.jpg`: a "Getting the site ready" card ("Add links to the Legal menu: It has no links yet"), "Recently edited entries" (renamed), and Recent activity without alert/refused/token lines. | The Legal menu really is empty now; see NEW-admin-1. |
| ADM-9 | fixed | `ADM-9-new-user-form.jpg`, `ADM-9-invite-choose-password.jpg`, `ADM-9-your-account.jpg`, `ADM-9-row-menu.jpg`. Leaving the password empty with "Email an invitation" on creates the user and logs "You're invited to Lumina Estates" with a 60-minute "Choose your password" link. Following it sets the password and login works. Row menu "Email a password link" (with a confirm) sends a fresh link. User menu → "Your account": name changes without a password; a new password without the current one is refused ("Type your current password…"), and with it is accepted. | No forced change at first sign-in, which is fine with invitations. See NEW-admin-3 and NEW-admin-4 for copy issues. |
| ADM-10 | partly fixed | `ADM-10-brand-colour-error.jpg`: "must be a colour written as # and six hex digits, like #1e66f5", and the input is no longer cut (typed `#0F4C4C99` kept). Sign-in title "Log in to Lumina Estates". Title preview "About · Lumina Estates". Security page has alert roles and addresses and points to the Guide, not artisan. Users and Roles lists show "Super admin", "Editor"… Collection/form help put dev details after "For developers:". | Left: Branding save still answers 409 with a console error (by design; I agree). Role slugs still show in Settings → Comments "Also email roles" and Forms "Also email roles" (`super-admin`). Roles list shows "Super admin 0" permissions. Comments captcha help still says "(SUNRICE_CAPTCHA_*)" with no "For developers". "Menus" vs "Menu settings" and `/admin/cms/…` vs `/admin/…` URLs kept on purpose. Translator per collection: docs only. |
| STR-1 | fixed | `sunrice:export-structure` writes `seo_title_field/seo_description_field/seo_image_field` for collections and taxonomies and `notify_roles` for forms. Importing a file with collection `retest_seo` (description←intro, image←hero) and form `retest_form` (notify_roles [editor]) stored exactly those values. | Import of a hyphenated handle gives the plain "The handle field format is invalid." (no suggestion). Fine for a developer file. |
| STR-2 | fixed | `STR-2-fieldset-hyphen-typed.jpg`: typing `retest-fields` (fieldset) and `retest-hyphen` (collection) turns into `retest_fields` / `retest_hyphen` as you type, and the collection was created. | |
| STR-3 | fixed | `STR-3-reserved-handle-warning.jpg`: a Text field labelled "Status" shows inline `"status" is used by the content itself; choose another handle.` while typing. The reserved list is in the Guide and fields docs. | |
| STR-4 | fixed (flattening kept by design, as the finding allowed) | `STR-4-fieldset-include-card.jpg`: the handle box is gone; the card says "Its fields are added here, at this level, with their own handles. To keep them under one name (primary_contact.phone), put this in a Group." `STR-4-clash-message.jpg`: "There is already a field "phone" here (the fieldset "contact_person" adds its fields at this level: put it in a Group…)". | I agree with keeping the flattening (moving stored data). The card still shows a "Label … Only shown here" box and the handle `fieldset` in its header. |
| STR-5 | fixed | `STR-5-seo-from-fields-filtered.jpg`: on a Neighbourhood-blueprint collection, Description offers only "Intro (intro)" and Share image only "Hero (hero)". Title offers only "—". | |
| STR-6 | partly fixed | Saving an Entries field with no collection ticked: toast `The "From collections" field is required.` (readable now). Empty-choice texts exist in code ("There are no collections yet: create the one to link to first (Structure → Collections)…"); I couldn't see them because collections exist. | Still no inline error on the field: the toast is the only signal, and with a long blueprint you have to find the card yourself (the fix agent says it couldn't reproduce this). |
| STR-7 | fixed | `STR-7-global-set-picker.jpg`: Global set offers Company, Footer, Social and the unused Retest BP. Form offers only the four form blueprints (`STR-7-form-picker.jpg`). Collection blueprint pickers no longer list form/global blueprints. | Entry, listing and term blueprints are still one kind: "Properties listing" is offered as a collection's entry blueprint, and "Agent"/"Property" as listing or term fields. Acceptable, but narrower than the suggested fix. |
| STR-8 | fixed | Entries list filters come from the blueprint's Terms fields (commit 559a28e). Taxonomy form and Guide say ticking isn't needed for filters. | Collection form still doesn't show its taxonomies; no longer needed. |
| STR-9 | fixed (fieldsets fixed by design) | Blueprint handle renamed on the builder (`retest_bp` → `retest_bp_renamed`, "Changing it updates the collections, taxonomies, global sets, forms and entries that use it"). Global set (`STR-9-global-handle-renamed.jpg`) and menu handles editable with a "templates… need the new handle" warning. Fieldset says "It can't change later: flexible blocks keep it with what they hold." | I agree with fieldsets staying fixed. |
| STR-10 | fixed | `STR-10-property-blueprint-folded.jpg`: the property blueprint page is 1,855 px (was ~16,600); cards fold to a summary; "Close all / Open all"; a new field opens alone. Extra rules has no placeholder: "Optional checks, one per line, such as email or max:100 (for developers: Laravel validation rules)". | |
| STR-11 | fixed (builder) | `STR-11-file-several.jpg`: File (forms) has "Several files" and "At most (files) — Empty: 10; at most 20"; saved as `{"multiple": true}`. | Not tried on the frontend: the valuation form's `photos` isn't switched to several, and I didn't change the site's form. |
| STR-12 | fixed | Structured data type now also offers "Real estate listing", "Person (a profile page)", "Place", "Product or offer". | |
| STR-13 | fixed | `STR-13-menu-entry-search-empty.jpg`: "No entries match. A menu links to entries that already exist: create the page first (Content → Entries), then add it here, or add a web address instead." Guide → Menus has the same note. | |
| STR-14 | fixed | `STR-14-add-block-order.jpg`: blocks ticked Team, Hero, Call to action are stored as `["team","hero","cta"]` and "Add block" lists them in that order. Handles come from the title for collections, taxonomies, forms, global sets and menus ("Retest Ünïcode Thing-2" → `retest_unicode_thing_2`). | The page blueprint's existing blocks stay alphabetical until someone re-ticks them (no migration; fine). |
| Extra: password twice | fixed | `EXTRA-password-confirm-mismatch.jpg`, `EXTRA-edit-password-confirm.jpg`: New user and Edit user have Confirm password; a mismatch gives "The Password field confirmation does not match."; on edit, an empty password keeps the current one. | |
| Extra: Security card shows what the user owns | fixed | `EXTRA-security-card-owns.jpg`: "Owns: 1 upload" after the throwaway user uploaded a file, plus last login, tokens and security events (invitation sent, password reset, roles changed). | |
| Extra: delete from edit page / bulk asks keep / reassign | fixed | `EXTRA-delete-from-edit-page-dialog.jpg`, `EXTRA-bulk-delete-dialog.jpg`: the bin button on the edit page and bulk "Delete…" ask "Their content: Give it to another user / Keep it, without an owner / Delete it…" plus "Give it to". Reassign worked: "Retest Temp Uno deleted. Their 1 upload went to Ayu Pratiwi." (DB `uploaded_by` 8→1). Bulk keep: "2 users deleted." | See NEW-admin-5. |

## NEW findings

### NEW-admin-1: Importing the structure replaces menus' links and wiped the live Legal and Main menus
- Severity: high
- Area: structure
- Where: `php artisan sunrice:import-structure`; `cms/src/Structure/StructureImporter.php` `menu()` (calls `SaveMenu` with the file's items whenever they differ)
- Steps: 1. Import a file with menu `retest_imp` holding a URL link and a link to `pages/about` (2 links). 2. Import a second file with the same menu holding only the URL link. 3. Look at the menu.
- Expected: As structure.md says, "content is only added". Links editors added in the panel stay, or at least the report warns that links will be removed (and `--dry-run` shows which).
- Actual: The About link is deleted. The report (dry run too) just says "updated" with no note. On this site it already happened: the redeploy's import of `database/structure.json` at 01:50:07 (activity: "System updated Legal — 0 links", "System updated Main — 7 links") removed the Legal menu's links and Main's About and Contact links. The dashboard now says "Add links to the Legal menu", and the site header/footer has no About, Contact, Privacy or Terms links. Menus are content (Content → Menus, edited by editors), but the structure file overwrites them on every deploy.
- Screenshot: `qa/retest/screenshots/ADM-8-dashboard.jpg`
- Note: Treat menu links like entries (create the menu, never remove links that exist), or add a `--menus` opt-in and list removed links in the report. The Lumina menus need re-adding by an editor (I didn't touch them).

### NEW-admin-2: "Delete" on a deleted collection answers 404; deleting says "can't be undone" but it can
- Severity: low
- Area: admin UI
- Where: Structure → Collections → filter Deleted → row menu → Delete
- Steps: 1. Delete a collection (the dialog says "Delete Retest SEO? This can't be undone."). 2. Filter Deleted: the row offers Edit, Restore, Delete. 3. Delete → Continue.
- Expected: The first delete says it can be restored from Deleted. The second delete purges it, or isn't offered.
- Actual: `DELETE /admin/collections/8` → 404 with an empty toast; nothing happens. Purging is only possible with `sunrice:orphans --purge` (which also needs `--force` or a "yes" prompt; `--no-interaction` silently does "Nothing was deleted").
- Screenshot: `qa/retest/screenshots/NEW-collection-deleted-list.jpg`

### NEW-admin-3: Raw validation message on New user without password or invitation
- Severity: low
- Area: admin UI
- Where: Users → New user
- Steps: Fill name and email, leave Password empty, leave "Email an invitation" off, then Create user.
- Expected: "Type a password, or turn on Email an invitation."
- Actual: "The Password field is required unless Email an invitation is in true, 1."
- Screenshot: `qa/retest/screenshots/ADM-9-no-password-no-invite.jpg`

### NEW-admin-4: The invitation lands on "Choose a new password" and ends with "Your password has been reset"
- Severity: polish
- Area: admin UI
- Where: invitation email link → `/admin/reset-password/…`
- Actual: A brand-new user who never had a password reads "Choose a new password" and then "Your password has been reset. You can log in now." The email and the Guide say "Choose your password".
- Screenshot: `qa/retest/screenshots/ADM-9-invite-choose-password.jpg`

### NEW-admin-5: Delete-user dialog offers the users being deleted as "Give it to", and drops your choices on error
- Severity: polish
- Area: admin UI
- Where: Users → Delete… (row, edit page, bulk)
- Steps: Tick two users, Delete…, Give it to another user, choose one of the two ticked users, then Continue.
- Expected: The users being deleted aren't offered. The dialog says what they own (the Security card knows: "1 upload").
- Actual: They are offered. Picking one closes the dialog with the toast "Choose another user to give their content to.", and you start over.
- Screenshot: `qa/retest/screenshots/EXTRA-bulk-delete-self-target.jpg`

### NEW-admin-6: Builder offers a Fieldset include as a "Show when" condition
- Severity: polish
- Area: structure
- Where: Blueprint builder → any field → Show
- Actual: The choices include "When Fieldset" (the include, which has no value of its own) next to real toggles and selects.

## What works well now
- Building structure no longer fails by trial and error: handles are made and corrected as you type, reserved handles are flagged inline, the Guide gives the build order, and the fieldset clash message says what to do.
- Settings forms show what was saved. The activity log shows before→after with a Who filter. The dashboard has a real setup checklist.
- Users: invitations, "Your account", confirm-password fields, the "Owns" line, and one keep/reassign/delete dialog everywhere all work end to end. Reassigning moved the upload to Ayu.
- STR-1 round-trip works through the real importer. The blueprint builder is about 9× shorter on the property blueprint, and flexible blocks keep their order.
