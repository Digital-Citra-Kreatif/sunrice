# Re-test round 2 — Back office (security, admin, structure, editor)

Tested at http://127.0.0.1:8200 on code at steps 1–3. Roles housekeeping done as Ayu (see end). All test data cleaned up.

| id | round-1 status | now | evidence | note |
| --- | --- | --- | --- | --- |
| SEC-7 | partly fixed | partly fixed (by design) — agree | `retest2-back-office-06-sec7.php`, `-07-access.php`; `retest-security-fresh-roles.php` | Unchanged and intended: the fresh default `author` still carries global `cms.entries.create`/`view`, so on Lumina `Access::collections(citra,'create')` = all 6 collections. Documented default; admin scopes it in Roles. |
| SEC-10 | partly fixed | fixed | `SEC-10-budi-menus-no-new.jpg`, `SEC-10-budi-menu-edit-no-delete.jpg`; `-05-menus.mjs` | After the role housekeeping, Budi's Menus page has no "New menu", `/menus/create` → 403, and the `main` row menu offers only Open/Settings (no Delete). Fresh-install editor already gets only `cms.menus.view`/`update`. |
| SEC-12 | partly fixed | fixed (for `<?=`); new bypass → NEW2-security-1 | `SEC-12-short-echo-refused.jpg`; `-01-media.mjs`, `/tmp/val.py`, `retest2-back-office-php-check.php` | Plain and newline `<?= … ?>` now refused on the valuation form ("This isn't a valid image.", nothing stored) and in Media ("This image contains PHP code…"). `containsPhp()` catches `<?=`. A non-ASCII byte inside the appended PHP still evades it (NEW2-security-1). |
| ADM-2 | partly fixed | fixed | `ADM-2-branding.jpg`, `ADM-2-sidebar-logo.jpg`, `ADM-2-signin-logo.jpg`; `-10-svg.php`, `-11-adm2.mjs` | The `migrate` backfill set the pre-existing `lumina-logo.svg` to 208×56. Favicon now points to the generated letter icon (`/admin/branding/icon.svg`, an "L" tile), not the wide wordmark. |
| ADM-5 | partly fixed | fixed | `ADM-5-languages.jpg`; `-12-admin-ui.mjs`; commit 6f12940 | Languages box is code/name pairs (no textarea — the round-1 textarea was stale assets). A "Main language" card exists; the reorder error now points to that card, no `sunrice:switch-main-language`. |
| ADM-10 | partly fixed | fixed | `ADM-10-roles-list.jpg`, `ADM-10-comments.jpg`, `ADM-10-form-email-roles.jpg`; `-12-admin-ui.mjs`, `/tmp/forms2.mjs` | Roles list shows "All" for Super admin (not "0"); Comments & Forms "Also email roles" show display names (no `super-admin` slug); comments captcha help has "For developers:" with `SUNRICE_CAPTCHA_*`. (Remaining round-1 items were by-design.) |
| STR-6 | partly fixed | partly fixed (improved) | `STR-6-entries-required.jpg`, `STR-6-scroll-to-field.jpg`; `-13-builder.mjs`, `/tmp/str6.mjs` | Save now scrolls the refused Entries field into view (scrollY 1013→484). Still no inline message on the field itself — the toast "The 'From collections' field is required." remains the only text signal. |
| ED-15 | partly fixed | fixed | `ED-15-citra-submitted.jpg`, `ED-15-budi-dashboard-widget.jpg`, `ED-15-budi-opens-review.jpg`; `-08-review.mjs`, `-09-budi-review.mjs` | NEW-editor-1 fixed (see below). Subject "Waiting for your review: …" is clean. Submit→widget→review flow works. (A send-back-with-note feature was also added.) |
| ED-16 | partly fixed | fixed | `rt2bo/props-export.csv`; `-24-export.mjs` | Terms export by title (Property type=Commercial, Amenities=Parking, Location=Canggu, Listing type=For rent); repeaters as "Label: value" lines with no `_id/_key/_hidden`; rich text with a line per paragraph/heading/list item (no more run-together text). |
| ED-17 | partly fixed | partly fixed ("Used by" now fixed; rest deferred) | `ED-17-used-by-labels.jpg`, `ED-17-submissions-list.jpg`; `-25-ed17.mjs`, `/tmp/ub3.mjs` | "Used by" now names fields by label ("Agent", "Author"), not "Author Agent". The three items round-1 marked "not done, as stated" are unchanged: rich-text link dialog still can't pick an entry; submissions list still has only a "Sent" column (no unread/assignee/topic/message); replies still under own name. |
| NEW-security-1 | high→low (new) | fixed | `retest-security-invite-url.php`; `-17-invite.mjs` | Invitation / "Email a password link" now built from APP_URL: `http://127.0.0.1:8200/admin/reset-password/…?email=…&invited=1` even during a forged-Host request. (Signed comment-unsubscribe still uses request host — left on purpose.) |
| NEW-security-2 | low (new) | fixed | `retest2-back-office-mcp.py`, `/tmp/rt2bo/m2.py` | `cms_render_page` decodes/normalises the path before the prefix check: `/%61dmin`, `/%5Finertia/devtools`, `/%6Dcp`, `/admin/../about`, `/x/../admin` all refused ("Only the site's public pages can be rendered…"). Public pages (/about) still render. |
| NEW-security-3 | low (new) | fixed; residual → NEW2-security-1 | `SEC-12-short-echo-refused.jpg`, `NEW2-bo-short-echo-highbyte-accepted.jpg` | Plain `<?= … ?>` now refused (media + form). A polyglot with a non-ASCII byte inside the PHP still passes — NEW2-security-1. |
| NEW-admin-1 | high (new) | fixed | `-26-menu.php`, `-26b.php`; imports of `rt2bo/struct-A.json`/`struct-B.json` | Importing a file with fewer links on an existing menu reports "unchanged" and keeps all links (the About entry link retained). `--replace-menus --dry-run` reports "updated: Removes the link to pages/about." as the explicit opt-in. Tested on throwaway menu `retest2_imp` only. |
| NEW-admin-2 | low (new) | fixed | `NEW-admin-2-delete-dialog.jpg`, `NEW-admin-2-trash-row-menu.jpg`; `-27c.mjs`, `/tmp/trash3.mjs` | Delete dialog: "Move … to the trash? You can restore it." (no "can't be undone"). DELETE → 303 (not 404). Trash row offers only Restore — no 404-prone Delete. Purge-for-good is `sunrice:orphans --purge`. |
| NEW-admin-3 | low (new) | fixed | `NEW-admin-3-validation.jpg`; `-14-users.mjs` | New user with no password/invitation → "Type a password, or turn on Email an invitation." (field + toast), not Laravel's raw "…is in true, 1". |
| NEW-admin-4 | polish (new) | fixed | `NEW-admin-4-invite-page.jpg`, `NEW-admin-4-invite-done.jpg`; `-17-invite.mjs` | Invitation link carries `invited=1`; after setting the password the page ends "Your password is set." (not "has been reset."). |
| NEW-admin-5 | polish (new) | fixed (minor: error is a toast) | `NEW-admin-5-give-to-options.jpg`, `NEW-admin-5-stays-open-on-error.jpg`; `-15-deluser.mjs`, `/tmp/du5.mjs` | "Give it to" no longer offers the users being deleted. On an empty-heir error the dialog stays open and keeps the chosen options (round-1 dropped them). The error still shows as a toast ("Choose another user…"), not inline under the field. |
| NEW-admin-6 | polish (new) | fixed | `STR-6-entries-required.jpg`; `-13-builder.mjs` | A field's "Show when" list no longer offers the Fieldset include (Contact person) — only real fields ("When Phone"). |
| NEW-editor-1 | high (new) | fixed | `-08-review.mjs` mail excerpt | Review email now reads "<strong>Citra Lestari</strong> asks you to review <strong>…</strong> (Journal)…" — no JSON user record, no leaked email/roles/ids. |
| NEW-editor-3 | medium (new) | fixed | `NEW-editor-3-journal-no-homepage.jpg`, `NEW-editor-3-root-page-confirm.jpg`; `-18-editor.mjs`, `-19-homepage-confirm.mjs` | "Make this the homepage" gone from journal posts (and other non-page collections). On a root page it asks first, naming the page it replaces ("Make 'Contact' the homepage? Visitors to / will see it instead of 'Home'…"). |
| NEW-editor-4 | low (new) | fixed | `-22-lock-http.mjs`, `/tmp/lh5.mjs` | On a trashed entry: `renew` returns 403 (expected — can't edit) but `release` returns **200** (round-1: 403). Release no longer authorises edit (`item(..., checkEdit:false)`), so the lock doesn't outlive the trashed entry. |
| NEW-editor-5 | polish (new) | fixed | `NEW-editor-5-scheduled.jpg`, `NEW-editor-5-unscheduled.jpg`; `-23-schedule.mjs` | Schedule toast "Scheduled for Nov 10, 2026, 09:30." (24h, matches header). Unscheduling a never-live entry → "Unscheduled. It's a draft again." (was "Taken off the site…"). |
| NEW-editor-6 | polish (new) | fixed | `NEW-editor-6-menu-placeholder.jpg`; `-18-editor.mjs` | Menu listing-link filter placeholder is generic "Filters (optional): filter=value" (no `listing_type=for-sale`). Other-language label placeholder: "Bahasa Indonesia: its title there, else as English". |

## NEW2 findings

### NEW2-security-1: PHP-in-image check (`<?=` detection) is bypassed by a non-ASCII byte inside the appended code
- Severity: low
- Area: security
- Where: `packages/admin/src/Media/FileContent.php` `containsPhp()`; used by media `UploadMedia` and the CMS `FileField`
- Steps: 1. Make a valid 40×30 JPEG and append `<?=` + a backtick `id` command + a C-style comment containing a multibyte char (`é`) + `?>`. 2. Upload it as Budi in Media → accepted and stored on the public disk (`containsPhp` returns false). 3. `/opt/php84/bin/php <file>` runs the embedded command. The same file with a plain ASCII `<?= … ?>` is refused.
- Expected: refused like the plain `<?=` polyglot (the file is valid, executable PHP).
- Actual: accepted. The detector matches `<?=`/`<?\s`, then scans the following run with `[\t\n\r\x20-\x7E]*` (ASCII printable/whitespace only); a non-ASCII byte ends the run before the closing `?>`, so neither the `?>` test nor the run-to-EOF test fires.
- Note: Same real-world scope as SEC-12/NEW-security-3 — only exploitable on a misconfigured server that executes files under `storage/`/`public/`. But the "This image contains PHP code" promise is again defeated by a trivial variation. Fix: match the closing `?>` across any bytes after a `<?`/`<?=` tag (not just the ASCII run), or re-encode raster uploads. Test file & media deleted after the check.

## Admin housekeeping (as Ayu) — done
- Administration → Roles → **Editor**: removed `cms.menus.create`, `cms.menus.delete` (`HOUSEKEEP-editor-menus-unchecked.jpg`).
- Administration → Roles → **Administrator**: removed `sunrice.ai.error-log`, `sunrice.ai.run-commands` (`HOUSEKEEP-admin-ai-unchecked.jpg`).
- Left the author's `cms.entries.journal.create`/`view` (deliberate, per the brief).
- `sunrice:roles` afterward lists only the author's journal permissions as "beyond default"; editor/admin are clean.

## Cleanup
- Test users (QA Heir One/Two, QA Invite Temp, QA NA3) deleted; users list back to the 5 real accounts.
- Test journal entries 56/57/58 deleted for good; journal back to 6 entries.
- Test media (short-echo polyglots) trashed and deleted for good; media = only `lumina-logo.svg`.
- Throwaway menu `retest2_imp` deleted; menus back to main/footer/legal.
- Throwaway collection `retest2_del_coll` purged via `sunrice:orphans --purge`; collections back to the 6.
- Revoked the read-only AI token I created (`sunrice:ai-token --revoke`).
- One submission attempt (plain short-echo) was refused, so nothing stored.

## What works well now
- Every round-1 "partly fixed" security item is resolved in practice: menus locked down for editors, `<?=` polyglots refused, invitation/password links use APP_URL, and `cms_render_page` normalises the path before its public-only check.
- The admin surface reads much better: role display names everywhere, "All" for the super admin, plain validation and dialog copy ("You can restore it", "Type a password, or turn on Email an invitation"), and the delete-user dialog that keeps your choices.
- Structure imports are safe for menus now — editors' links survive a redeploy, and removal is an explicit `--replace-menus`.
- Editor flow: review emails are readable and leak nothing, the homepage switch is pages-only and asks first, scheduling messages match the 24h header, the CSV export reads like the editor, and edit locks are freed even after trashing.
