# Round 3 — security

Tested 2026-10-21 against http://127.0.0.1:8200 (app `/home/user/realestate`, packages via symlink). Scripts and session files: ` (`login.mjs`, `lib3.py`, `mcp.py`, `ai-ui.mjs`, `redirect-browser.mjs`, `php/trusthosts.php`). No screenshots saved: nothing needed one except the open-redirect finding (see below).

Test content created and removed afterwards: 3 wildcard redirects with the `QA3` prefix (deleted), 1 no-access user `qa3-noaccess@example.test` (deleted), 2 QA3 AI tokens (revoked), Dewi's reset-token row (deleted). Entries 10, 30, 37, 38 were not changed (checked in the database). No media or form submission was stored by my uploads (all refused).

| ID | Result | Evidence |
|---|---|---|
| SEC-1 | Still fixed | `POST /admin/forgot-password` for Dewi with `Host: evil.example` (same answer), then `laravel.log`: the new reset link is `http://127.0.0.1:8200/admin/reset-password/<token>?email=…`. |
| SEC-2 | Still fixed | QA3 no-panel-access user with the right password, with a wrong one, and an unknown email: all three give "These credentials do not match our records." |
| SEC-3 | Still fixed | As Dewi (translator): `PUT entries/30/settings` (comments_closed), `PUT entries/37/settings` (parent_id), `POST entries/38/move` and reorder all → 403. DB unchanged. |
| SEC-4 | Still fixed | `/_inertia/devtools` and `/_inertia/devtools/entries` (plain and `/%5Finertia/…`) → 404. `cms_render_page` refuses `/_inertia/devtools/entries` and `/%5Finertia/devtools/entries`. |
| SEC-5 | Still fixed (config) | Booted the kernel with `APP_ENV=production`, `APP_DEBUG=false` (no server): real host `/admin/login` → 200, `Host: evil.example` → 400 for `/admin/login` and `/`. The local dev server still answers a forged Host (APP_ENV=local); expected. |
| SEC-6 | Still fixed | Ayu saves Security with two-factor on while the mailer is `log` → 303 back with the error "The app can't send email yet (the log mailer only writes to the log)…". `sunrice_settings` still `twoFactor: false`. |
| SEC-7 | By design | Citra (author): `cms.entries.create` yes, `cms.entries.view` yes, `cms.entries.publish` no. Documented default; admin scopes it in Roles. |
| SEC-8 | Still fixed | Budi uploads a 14-byte text file named `.jpg` to Media → 422 "This isn't a valid image: the file's content doesn't match .jpg…". Nothing stored. |
| SEC-9 | Still fixed | Forgot-password, 8 requests from one IP: 302 ×5, then 429 with Retry-After 55–56 s. |
| SEC-10 | Still fixed | Budi: `cms.menus.create` and `cms.menus.delete` are both false. `/admin/cms/menus/create` → 403; the Menus page has `can.create: false` and no New menu. |
| SEC-11 | Still fixed | `LoginCode` (rendered without sending, since 2FA can't be turned on with the log mailer): subject "Your Lumina Estates login code", code only in the body. Not a live login (2FA is off). |
| SEC-12 | Still fixed | Valuation form (`/sell-with-us` → `/forms/valuation`): JPEG + `<?= … ?>` → 302 back with the "This isn't a valid image." error; JPEG + high-byte `<?= … ?>` → 302 with errors; nothing stored (`sunrice_form_submissions` form 3 has no new row). The JPEG + `<?php` variant also redirected back with errors (its message not captured). |
| AI-1 | Still fixed | `cms_render_page /contact` and `/id/kontak`: canonical `http://127.0.0.1:8200/…`, no `localhost`. `/qa3-missing-page` → 404, `error: null`, no vendor paths. |
| AI-2 | Still fixed (partial) | New-token dialog for Ayu (opened, not submitted): "a full token can do everything in the panel, delete included… View the activity log, Delete old activity log lines, Edit branding settings, Edit security settings, See mail settings…, Let AI agents read the error log, and 16 more". `cms_write_template` absent; read-only token has 21 tools, full token 43. Riskiest abilities (users, roles, run commands, Site code) are still not among the six shown. |
| AI-3 | Still fixed | AI access page text: "Read-only tokens can look but not change anything; they still read everything you can see, personal data included…". |
| AI-4 | Still fixed | `cms_seo_audit`: Home `meta_title` "Lumina Estates" (14 chars), the same as its rendered `<title>`. Contact judged on its real head title (69 chars). |
| AI-5 | Still fixed (partly tested) | Full token: `cms_reorder` with the current agents order → `{"changed":false}`. `cms_save_entry` with agent `"x"` → "Not saved: The Agent field must be an entry id (a number), or null to clear it." `[]` and `null` not re-tested, since they would write to entry 10. |
| NEW-security-1 | Still fixed | Logged Ayu in on `Host: evil.example` (the session is only valid on that host), then `POST /admin/users/4/actions/invite` (Dewi). Mail link: `http://127.0.0.1:8200/admin/reset-password/…?email=…&invited=1`. |
| NEW-security-2 | Still fixed | MCP read-only token: `/%61dmin`, `/%5Finertia/devtools/entries` → "Only the site's public pages can be rendered…". `/about` still renders. |
| NEW-security-3 | Still fixed | JPEG + `<?= … ?>` (plain, no-space, `<?=\`id\`?>`) refused in Media ("This image contains PHP code, so it can't be uploaded.", 422) and on the valuation form (302 with errors). |
| NEW2-security-1 | Still fixed | Probe JPEGs with `<?= \`id\` /*é*/ ?>`, `é` in a string, `é` right after `<?=`, a Latin-1 byte in a comment, and no closing `?>`: all six refused in Media (422 "This image contains PHP code"). |

Settings → Cache (budi, editor): forbidden. `GET /admin/settings/cache` → 403; `POST /admin/cms/cache/clear-pages`, `/clear` and `/preload` → 403; the Settings payload for Budi has no Cache entry. Ayu gets 200 on the same page.

Redirect open-redirect spot check: a wildcard `/QA3-old/*` → `/QA3-new/*` stays on the site for every variant tried: `/QA3-old//evil.example` → `/QA3-new/evil.example`; `/QA3-old/\evil.example` → `/QA3-new/evil.example`; `%2F%2F` and `%5C%5C` stay encoded; `/QA3-old/..//evil.example` → `/evil.example` (same host). The `/x/*` → `/y/*` form is fine; the problem is the bare `*` target below.

Host-header on password reset: fixed for reset, invitation and "Email a password link" (SEC-1, NEW-security-1). The signed comment-unsubscribe link still uses the request host, by design (see retest/security.md).

Counts: still fixed 20 (SEC-1, 2, 4, 5, 6, 8, 9, 10, 11, 12; AI-1 to 5; NEW-security-1 to 3; NEW2-security-1), by design 1 (SEC-7), regressed 0, not tested 0 (AI-5 `[]`/`null` and the live SEC-11 login are the only untested parts).

## New findings

- R3-security-1 (medium): A wildcard redirect whose target is a bare `*` is an open redirect to another host. Steps: 1. In Structure → Redirects (or `POST /admin/cms/redirects` as Ayu, which accepts it) create From `/QA3-ui/*`, To `*`, 301. 2. `curl -i --path-as-is 'http://127.0.0.1:8200/QA3-ui/https:evil.example'`. 3. Open the same URL in Chromium. Expected: the visitor stays on the site (as `/QA3-old//evil.example` does). Actual: `Location: https:evil.example` (301); Chromium navigates to `https://evil.example/`. Cause: `Redirect::targetFor()` guards only `//` and `/\` at the start of the target, but the `*` is the first character of the target, so the visitor's text can make it a scheme URL. `RedirectRule` and `SafeUrl::rule()` accept `*` as a To value. Fix: after substitution, require the target to start with `/` (or refuse a To that does not start with `/` when it contains `*`). No screenshot: the failure is a navigation to the external host, so the browser's navigation log is the evidence. The test redirect was deleted afterwards.
