# Lumina Estates re-test, round 3 (21 October 2026)

After steps 5–8 (#74–#77) and the test-speed change (#78), the live Lumina site was redeployed from `main` and every finding was re-run once more: the 103 audit findings ([../REPORT.md](../REPORT.md)) and the 23 later ones (`NEW-…`, `NEW2-…`). Five testers covered security, admin, editor, frontend and multilingual, one file each in this folder. Each regression or new finding a tester reported was then checked in the code before being fixed or answered.

| Area | Still fixed | By design | Regressed | File |
| --- | --- | --- | --- | --- |
| Security and AI | 20 | 1 (SEC-7) | 0 | [security.md](security.md) |
| Install, admin, structure | 33 | | 1 partly (ADM-10) | [admin.md](admin.md) |
| Editor | 21 | | 2 partly (ED-6, ED-18) | [editor.md](editor.md) |
| Site, cache, forms, a11y | 32 | 2 (CACHE-8, CACHE-9) | 0 | [frontend.md](frontend.md) |
| Multilingual, with the translated URLs of step 7 | 13 | | 0 | [i18n.md](i18n.md) |

## What was reported, and what came of it

| ID | Severity | Verdict | Outcome |
| --- | --- | --- | --- |
| R3-security-1: a wildcard redirect whose To is a bare `*` is an open redirect | medium | Real | Fixed in #79: the rest of the path always goes after a `/`, and the form refuses such a To |
| R3-editor-1: the header **Publish** ignores the Publish date picked in the side card | medium | Real | Fixed: both buttons go by the picked date (schedule, back-date or now) |
| ED-6 (partly): a past date on a draft says only "Publish" | low | Real | Fixed with R3-editor-1: it says **Publish now, back-dated** |
| ED-18 (partly): a second copy's slug ("-copy-2") doesn't follow a new title | low | Real | Fixed: a numbered copy slug follows too |
| ADM-10 / R3-admin-1: Settings → Security lists role slugs | low | Real | Fixed: names as people read them |
| R3-admin-2: after renaming a blueprint's handle the page stays on the old address (404 on reload) | low | Real | Fixed: saving goes to the new address |
| R3-i18n-1: the Indonesian home page's hero button links to the English listing | low | Content | Fixed in the example and on the live site (`/id/properti`); the "Sell with us" page has no Indonesian translation, so its link stays |
| R3-frontend-1: the footer menu marks Properties as the current page on `/properties?listing_type=…` while the main menu only trails it | low | By design | Each menu marks its own best match: the main menu has a link for that filter, the footer doesn't |
| R3-i18n-2: an old address with a trailing slash takes two redirects | low | Accepted | Both are permanent and the result is right; not worth its code |
