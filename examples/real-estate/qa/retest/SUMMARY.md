# Lumina Estates QA: overall status

The audit ([../REPORT.md](../REPORT.md), 103 findings) was followed by two re-test rounds on the live Lumina site, each finding re-run by its own steps and as the role it names:

- **Round 1** (21 October 2026, after the audit fixes): five testers, one file per area in this folder. It also recorded 25 new findings (`NEW-<area>-<n>`).
- **Round 2** (21 October 2026, after steps 1–3, PRs #69–#71): the round-1 leftovers and the 25 new findings, in [../retest2/back-office.md](../retest2/back-office.md) and [../retest2/front-and-languages.md](../retest2/front-and-languages.md). It recorded 7 new findings (`NEW2-…`).
- **Step 4** (the pull request after #71) fixes the round-2 findings and the leftovers listed below.

## The 103 audit findings

| Final status | Findings |
| --- | --- |
| Fixed | 99 |
| By design (agreed) | 3: CACHE-8, CACHE-9, SEC-7 |
| Out of scope (rest of the finding) | 1: ED-17 |
| Not fixed / regressed | 0 |

How they got there:

| | Round 1 | Round 2 | After step 4 |
| --- | --- | --- | --- |
| Fixed | 83 | 97 | **99** |
| Partly fixed | 18 | 4 (SEC-7, STR-6, ED-17, FORM-6) | 0 |
| By design | 2 | 2 (and SEC-7, agreed as partly fixed by design) | 3 |
| Out of scope | 0 | 0 | 1 |

- **Fixed in round 2** (14 of the 18 round-1 leftovers): SEC-10, SEC-12 (`<?=`), ADM-2, ADM-5, ADM-10, ED-15, ED-16, SITE-3, SITE-5, CACHE-5, FORM-2, FORM-3 (its per-IP limit stays by design), FORM-4, FORM-5.
- **Fixed in step 4:** SEC-12's last variant (PHP after `<?=` with non-ASCII bytes, NEW2-security-1); STR-6 (the inline message under a refused setting was there, as both round-2 screenshots show; the group of choices is now marked invalid and described by it, so it's announced and found by tools); FORM-6 (the inquiry form's field is "Preferred viewing date" in the blueprint as in the template, with the Indonesian "Tanggal kunjungan", in the example and on the live site).
- **By design:** CACHE-8 and CACHE-9 (agreed in round 1); SEC-7: the default *author* role keeps global `cms.entries.create`/`view`, a documented default the admin scopes in Roles.
- **Out of scope:** ED-17's "Used by" labels are fixed; its other parts (entry links in the rich-text link dialog, replying to comments as the site, unread/assignee columns for submissions) are new features, left for later.

| Area | Fixed | By design | Out of scope | Round 1 file |
| --- | --- | --- | --- | --- |
| Security and AI (SEC, AI) | 16 | 1 (SEC-7) | | [security.md](security.md) |
| Install, admin, structure (INS, ADM, STR) | 28 | | | [admin.md](admin.md) |
| Editor (ED) | 17 | | 1 (ED-17) | [editor.md](editor.md) |
| Site, cache, forms, a11y (SITE, CACHE, FORM, A11Y) | 25 | 2 (CACHE-8, CACHE-9) | | [frontend.md](frontend.md) |
| Multilingual (I18N) | 13 | | | [i18n.md](i18n.md) |

## The 25 round-1 new findings

All 25 are fixed (round 2): NEW-security-1 to 3, NEW-admin-1 to 6, NEW-editor-1 to 6, NEW-frontend-1 to 4, NEW-i18n-1 to 6, among them the two high ones (structure imports replacing menu links, NEW-admin-1; the review mail printing the requester's record, NEW-editor-1). NEW-security-3's residual became NEW2-security-1, fixed in step 4. NEW-admin-5's error still shows as a toast, as noted in round 2.

## The 7 round-2 new findings: all fixed in step 4

| id | Severity | Fix |
| --- | --- | --- |
| NEW2-security-1 | low | `FileContent::containsPhp()` reads the code after `<?=` with PHP's tokenizer: it counts when there's no character code can't hold (outside comments and strings) up to `?>`, so non-ASCII bytes in a comment no longer hide it, while `<?=` in a noisy photo's data still passes (tested on both). |
| NEW2-front-1 | low | A listing page past its last page has a plain not-found head: `noindex`, no canonical, hreflang or `og:url`, no `CollectionPage`. |
| NEW2-front-2 | low | Menus don't reload each entry link's entry and collection: 2 queries fewer per entry link on a cold site cache (18 on `/contact`). |
| NEW2-front-3 | polish | "Photos: files of this type can't be sent (accepted: JPG, JPEG, PNG, WEBP)."; the stale-assets banner names "Sunrice CMS" and is part of the page (it no longer sits under the sidebar). |
| NEW2-i18n-1 | low | "Today or later" has its own translated line ("… harus berupa tanggal hari ini atau setelahnya."). |
| NEW2-i18n-2 | polish | A filtered menu link without a label in a language shows the main language's label, not the listing's title. |
| NEW2-i18n-3 | polish | Content → Translations lists menu links in menu order, as "Parent › Link to Properties · listing_type=for-sale". |

Step 4 also fixes what the user reported while it was under way: duplicate error toasts, the template help in the editor's sidebar, the builder's field rows (with **Duplicate** and a **Translatable** switch), the renamed-handle warning, lists shown stale after a save (prefetched pages), Revisions → Compare showing JSON, and the Indonesian SEO labels. See the root `CHANGELOG.md` and the screenshots `BANNER-*`, `TOAST-*`, `TEMPLATE-HELP-*`, `BUILDER-ROW-*` and `BUILDER-RENAME-*` in [../retest2/screenshots](../retest2/screenshots).

## Query counts

Front-end pages, page cache miss. Audit → round 1 (warm site cache):

| Page | Audit | Round 1 |
| --- | --- | --- |
| Home | 121 | 21 |
| Journal post | 91 | 33 |
| Journal listing | 61 | 30 |
| Topic | 69 | 32 |
| Search | 107 | 26 |
| Property | 56 | 32 |

Round 1 → round 2 ([../retest2/front-and-languages.md](../retest2/front-and-languages.md)):

| Page | Warm MISS r1 → r2 | Cold MISS r1 → r2 | Kept page (HIT) |
| --- | --- | --- | --- |
| Home `/` | 21 → 21 | 46 → 72–74 | 3 |
| Journal post | 33 → 34 (no app helper now) | 67 → 86 | 3 |
| Journal listing | 30 → 22 | 51 → 70 | 3 |
| Topic | 32 → 24 | 55 → 74 | 3 |
| Search `?q=villa` | 26 → 26 | 50 → 77–81 | never kept |
| Property | 32 → 32 | 56 → 83 | 3 |
| Contact | 9 → 9 | 34 → 61 | 3 |

The warm case, a visitor's first request, is equal or lower everywhere. The cold-cache rise in round 2 came from the menus restored after round 1 (11 entry links), each costing 2 queries; NEW2-front-2 removes those (18 on `/contact`, likewise on every page), and a cold cache only follows a content save or a cache clear.

## Notes

- ED-7: round 1 tested a date-only "Change the date" control. It was later removed: the publish date now follows main, where the Status card's **Publish date** field is used when publishing (a future date schedules). Notes say so in [editor.md](editor.md), [i18n.md](i18n.md) and the audit report.
- Round 1's caveat about stale admin assets (some UI results taken before `php artisan sunrice:assets`) was cleared in round 2, which checked the published assets against the packages' builds first.
- `database/content.json` keeps field values as stored (term and media ids, `StructureExporter`), so importing it into an empty database refuses entries whose term ids differ there ("The selected Location is invalid"); this was so before step 4 too. The example's seeders and tests use `database/structure.json`, which now matches the live site.
