# Lumina Estates re-test (21 October 2026)

After the audit fixes landed, the 103 findings in [../REPORT.md](../REPORT.md) were re-run on the live Lumina site by five testers (security, admin, editor, frontend, i18n), each finding by its own steps and as the role it names.

| Status | Findings |
| --- | --- |
| Fixed | 83 |
| Partly fixed | 18 |
| Won't fix (by design, agreed) | 2 (CACHE-8, CACHE-9) |
| Not fixed / regressed | 0 |

| Area | Fixed | Partly | Won't fix | File |
| --- | --- | --- | --- | --- |
| Security and AI (SEC, AI) | 14 | 3 (SEC-7, SEC-10, SEC-12) | | [security.md](security.md) |
| Install, admin, structure (INS, ADM, STR) | 24 | 4 (ADM-2, ADM-5, ADM-10, STR-6) | | [admin.md](admin.md) |
| Editor (ED) | 15 | 3 (ED-15, ED-16, ED-17) | | [editor.md](editor.md) |
| Site, cache, forms, a11y (SITE, CACHE, FORM, A11Y) | 17 | 8 (SITE-3, SITE-5, CACHE-5, FORM-2..6) | 2 | [frontend.md](frontend.md) |
| Multilingual (I18N) | 13 | | | [i18n.md](i18n.md) |

The re-test also recorded 25 new findings (`NEW-<area>-<n>` in each file), among them two high: `sunrice:import-structure` replaces menu links (NEW-admin-1) and the review-request mail prints the requester's record (NEW-editor-1).

Front-end query counts (page cache miss), before → after: home 121 → 21, journal post 91 → 33, journal listing 61 → 30, topic 69 → 32, search 107 → 26, property 56 → 32; a kept page answers in 3 queries.

Caveat: some UI results in admin.md and frontend.md were taken before the site's published admin assets were refreshed (`php artisan sunrice:assets`, see NEW-editor-2); partly-fixed UI items there are to be re-checked.
