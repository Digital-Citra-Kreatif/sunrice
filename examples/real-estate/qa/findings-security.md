# Lumina Estates: security findings

Authorized defensive assessment of Sunrice (sunrice/admin + sunrice/cms) through the Lumina Estates app. All work against 127.0.0.1. File/line references point at the packages under `/home/user/sunrice/packages`. Test content created during this pass was trashed/deleted (see "Test content created" at the end). Ayu is not locked.

## Summary by severity

- **Critical:** none found.
- **High:** (H1) Password-reset email link honours the request Host header (poisonable reset-link host); (H2) user enumeration on the login form via a distinct "can't use the admin panel" message.
- **Medium:** (M1) Translator role can change shared/structural entry settings (parent_id, comments_closed) and reorder entries via the locale escape hatch; (M2) `APP_DEBUG=true` in this install leaks full stack traces + file paths (dev-only, but note the production story); (M3) Inertia DevTools endpoint is open to any visitor in `local` and exposes every recorded request/response including other users' props.
- **Low:** (L1) no throttle/alert on password-reset requests beyond Laravel's per-user 60 s (enumeration-adjacent, mail bombing); (L2) MCP error-surfacing returns framework file paths in `cms_render_page` error field.
- **Info / held up well:** see the long list at the end — authorization, CSRF, uploads, SSRF, SQLi, XSS sanitising, token scope, sign-out/lock, cache isolation all behaved.

---

## HIGH

### H1 — Password-reset link uses the attacker-controlled Host header
- Severity: high
- Area: security / auth
- Where: `packages/admin/src/Notifications/ResetPanelPassword.php:36` (`url(route('sunrice.admin.password.reset', …, false))`); `PasswordResetController@store`
- Steps:
  1. `POST /admin/forgot-password` with `{"email":"dewi@lumina-estates.test"}` and header `Host: evil.example`.
  2. The queued mail (storage/logs/laravel.log) contains the reset URL `http://evil.example/admin/reset-password/<token>?email=…`.
- Expected: reset links are built from the app's configured URL (`APP_URL`), never the request Host.
- Actual: `url(route(…, false))` prepends `url()` which derives the scheme+host from the current request, so the whole reset link (the one the victim clicks) points at whatever Host the requester sent. A victim who clicks lands on the attacker's host, which receives the valid token in the path; classic password-reset poisoning / token theft.
- Repro captured: `grep reset-password storage/logs/laravel.log` → `http://evil.example/admin/reset-password/93562ee7…`.
- Why it matters: full account takeover if combined with a victim who follows the link, and Laravel's `TrustHosts`/`TrustProxies` are NOT configured in `bootstrap/app.php` (app relies on an upstream proxy). The app ships no Host allow-list.
- Suggested fix: build reset (and all notification) URLs from `config('app.url')`, e.g. `URL::forceRootUrl(config('app.url'))` around the notification, or use `route(..., true)` after pinning the root URL; and document that apps must configure `TrustHosts`. Note the 2FA login code email and security-alert links use `route()` too — same hardening.

### H2 — Login form reveals whether an email has a (non-panel) account
- Severity: high (practically medium; enables targeted attacks)
- Area: security / auth
- Where: `packages/admin/src/Actions/Auth/AttemptLogin.php:80-86`
- Steps (as the no-panel-access user "Fajar" created for this test):
  - Right password, account has no panel access → `"This account can't use the admin panel."`
  - Wrong password (any email) → `"These credentials do not match our records."`
- Expected: the login form gives one answer regardless of whether the email exists, so it can't be used to enumerate accounts or confirm a guessed password.
- Actual: the "no access to the panel" branch fires only after a *correct* password for an existing account, so an attacker who submits a candidate password learns (a) the email exists and (b) the password is correct, for any non-panel account (e.g. an app's customers who share the user table). It is a password oracle for every account that lacks `sunrice.access`.
- Severity note: the reset form and the "account locked" message are handled carefully (same answer for unknown emails); this one path breaks that model. CVSS ~5.3 (AV:N/AC:L/PR:N/UI:N/C:L).
- Suggested fix: when the password is right but the account can't access the panel, return the generic `auth.failed` message (still log the real reason server-side), matching the reset-flow design.

---

## MEDIUM

### M1 — Translator can change shared/structural entry settings and reorder entries
- Severity: medium
- Area: security / authorization
- Where: `packages/cms/src/Http/Controllers/EntryManageController.php:86-114` (settings, move); `packages/cms/src/Support/Access.php:39-59`; middleware `AuthorizeContent:entries,update`
- Steps (as translator Dewi, who should only translate non-main languages):
  1. `PUT /admin/cms/entries/30/settings` body `{"locale":"id","comments_closed":true}` → 303, and `sunrice_entries.comments_closed` for entry 30 flips to 1.
  2. `PUT /admin/cms/entries/37/settings` body `{"locale":"xx","parent_id":38}` → 303, entry 37's `parent_id` moves to 38 (changes the page tree).
  3. `POST /admin/cms/entries/38/move` body `{"locale":"id","direction":"up"}` → 302, sort_order changes.
- Expected: a translator edits only the translatable text of non-main languages. `parent_id`, `comments_closed` and ordering are shared, non-translatable, site-structure properties — a translator shouldn't change them.
- Actual: `Access::entries($user,'update',…)` returns true for a translator whenever `locale !== default`. The settings/move/reorder endpoints accept a `locale` from the request body and so pass the gate, even though the fields they then change are not per-language. (The blueprint and template sub-fields *are* separately re-checked with `Access::entries(update, collection)` and correctly 403 — only parent_id/comments_closed/move/reorder are not.) Confirmed: `template`→403, `blueprint`→403, but `parent_id`/`comments_closed`/`move` succeed.
- Why it matters: a translator can silently re-parent/re-order pages (break navigation, change which page is a child of what) and open/close comments across the site — authority they were never granted. Author role is correctly fenced (publish/unpublish/trash/update-others all 403).
- Suggested fix: in `EntryManageController::settings()` and `move()`/`reorder()`, require `Access::entries($user,'update',$collection)` (collection scope, not the translator escape hatch) for `parent_id`, `comments_closed`, `move` and `reorder` — i.e. gate the structural fields the same way `template` already is at `EntryManageController.php:97`.

### M2 — APP_DEBUG=true: full stack traces, file paths and config leak
- Severity: medium (dev install; included per brief to reason about production)
- Area: security / info leak
- Where: app `.env` (`APP_DEBUG=true`, `APP_ENV=local`); not a package bug
- Observed now (dev): error pages and the MCP `cms_render_page` error field return framework file paths, e.g. `… in vendor/laravel/framework/src/Illuminate/Foundation/Application.php:1445`, and 403/419 pages render Laravel's Ignition/whoops with environment detail. `X-Powered-By: PHP/8.4.28-dev` is sent on every response.
- Production reasoning (from code): the panel installs its own Error page for 403/404/419/500 under the panel path (`AdminServiceProvider` → `Handler`), so panel errors are clean regardless of debug. Front-of-site and MCP responses fall back to Laravel's default, which with `APP_DEBUG=false` would be the generic error view (no trace) — good. The residual production risks are: (a) `X-Powered-By` version disclosure (PHP default, not suppressed), and (b) any app that ships with `APP_DEBUG=true` (this example does) leaks everything.
- Suggested fix: ship the example `.env`/`.env.example` with `APP_DEBUG=false` and document it; add `expose_php=Off` guidance / `header_remove('X-Powered-By')`. Package-side: consider the CMS registering the same clean error view for its own front-end 404/500 so a misconfigured app leaks less.

### M3 — Inertia DevTools request recorder is world-readable in local and exposes all props
- Severity: medium (dev install) / would be high if ever enabled in production
- Area: security / info leak
- Where: `vendor/inertiajs/inertia-laravel/src/DevTools/Http/Authorize.php:24-27` (`if (app()->environment('local')) return true;`); enabled by default in `local` (`DevTools::enabled()`), route group `/_inertia/devtools/*`, no auth.
- Steps: unauthenticated `GET http://127.0.0.1:8200/_inertia/devtools/entries` returns 4000+ recorded requests; `GET /_inertia/devtools/entries/{id}` returns the full recorded response body of any admin page, including another user's Inertia props (e.g. the AI-access page props, users list with emails, dashboard widgets). Request cookies are `[REDACTED]` and the one-time AI token value is `[REDACTED]`, and non-Inertia login bodies are omitted — so the recorder itself redacts the worst secrets, but it still exposes every page's props to any local visitor with no authentication at all.
- Why it matters: in `local` this is wide open. If an app ever runs with `APP_ENV=local` in a reachable environment (common misconfig), all admin content and PII is readable with no login. Also note `cms_render_page` can fetch `/_inertia/devtools/entries` (returns 200 with the full dump) — a read-only MCP token can therefore read the DevTools recorder.
- Suggested fix: not Sunrice's code, but the panel should disable Inertia DevTools for its own routes (set `inertia.devtools.enabled=false` or an `except` pattern covering the panel path) and document that apps must not expose `local`. At minimum, note it; the real fix is `config('inertia.devtools.enabled')=false` in the shipped config.

---

## LOW / INFO

### L1 — Password-reset requests: only Laravel's per-user 60 s throttle, no per-IP cap or alert
- Severity: low
- Where: `routes/admin.php` (`throttle:sunrice-password`), `config/auth.php` `passwords.*.throttle=60`; `PasswordResetController@store`
- Note: `POST /admin/forgot-password` always answers with the same "if an account exists…" message (good, no enumeration via body), and logs a "password reset requested" security event (with a "no account" note for unknown emails — that note is only in the admin log, not the response). But there is no per-IP rate limit on the endpoint beyond the broker's per-address 60 s, so an attacker can fan out reset emails across many addresses (mail bombing) and the per-address window is only 60 s. Low because it doesn't leak and doesn't take over anything.
- Suggested fix: add a per-IP `RateLimiter` to the forgot-password route (the login throttle already has per-IP; mirror it).

### L2 — `cms_render_page` returns framework file paths in its error field
- Severity: low (requires a valid MCP token; read-only is enough)
- Where: `packages/cms/src/Ai/RenderPage.php:68`
- Note: on a 404/403/500 the tool returns `"error": "<message> in vendor/laravel/.../Application.php:1445"` to the agent. Minor path disclosure to a token holder. Panel paths and `//host`/absolute URLs are correctly refused (SSRF-safe), and signed preview routes still enforce the signature. Suggested: strip the file/line (or gate behind debug) in the returned `error`.

---

## What held up well (tested, behaved correctly)

- **Role authorization (Budi editor, Citra author, Dewi translator, Eko Listings manager, Fajar no-access):** swept every admin GET route and state-changing POST/PUT/DELETE with each role's own session + CSRF token. Scoped `cms.entries.<collection>.<action>`, `cms.terms.<taxonomy>.manage`, `cms.submissions.<form>.<action>` all enforced server-side (not just hidden in nav). Author cannot publish/unpublish/update others/trash others (all 403); author `delete-own`/`update-own` correctly limited to own entries. Eko cannot touch non-property collections, other taxonomies, settings, users, roles, globals, menus, submissions (403 each). Fajar (no `sunrice.access`) is bounced to login everywhere.
- **Privilege escalation:** a freshly-created administrator (71 perms, below super-admin) could NOT: grant itself/others `super-admin` (`roles.0 … invalid`), create a role equal-or-above its own access ("must have less access than yours"), add a permission it doesn't hold (`invalid`), edit/delete the super-admin or administrator role (403), or lock/sign-out/delete/change the password of the super admin (403). `PermissionRank` strict-below logic and the super-admin Gate::before held. Cannot change own roles (field disabled + server keeps them).
- **Cross-user data in props:** inspected `data-page` JSON on every page for each role — no password hashes, no tokens, no other users' emails leaked beyond what the role legitimately sees (moderators see commenter emails; globals show the public company email by design). The one-time AI token is flashed once and not re-served.
- **Pickers:** entry-picker and term-picker only return entries from collections the user may view (Citra sees only journal; Eko only properties) — no cross-scope leak.
- **Uploads (media):** `.php`, `.phtml`, `b.php.jpg` (PHP body), polyglot `GIF89a<?php`, HTML-in-.jpg all rejected (extension blocklist + content sniff + SVG script scan). `qa.PHP.png` stored as inert PNG (double-extension neutralised by extension check). Path-traversal filename `../../../qa-trav.png` stored safely under the dated media dir with a random prefix (basename only). Files land on the `public` disk; `/storage/media/` directory listing is 403; `.env`, `/storage/logs/` are 403/404. Form-upload files go to the private `local` disk (not web-served); the download route re-checks `submissions,view` and sends `nosniff` + `application/octet-stream`.
- **SSRF (`upload_media` from URL):** 127.0.0.1, localhost, `[::1]`, `[::ffff:127.0.0.1]`, `[::ffff:7f00:1]`, `2130706433`, `0x7f.1`, `169.254.169.254`, `0.0.0.0` all refused by `publicAddress()` (FILTER_FLAG_NO_PRIV_RANGE|NO_RES_RANGE, resolves every A record, pins the IP with CURLOPT_RESOLVE, no redirects). NAT64 `[64:ff9b::…]` resolves but fetch fails safely.
- **MCP token scope:** read-only token exposes only 21 read tools (write tools literally absent from tools/list); `get_settings`, `save_record`, `run_action`, `upload_media`, `run_command`, `cms_write_template`, `update_settings` all "not found"/refused. Enforced centrally via `Gate::before` → `TokenScope::refuses()` (first before-hook, ahead of super-admin). Revoked token → "Missing, expired or revoked". Invalid token → 401. `read_docs` path traversal (`../../.env`, url-encoded) → "No such page". `cms_read_template` traversal refused; template write gated on super-admin AND `SUNRICE_AI_TEMPLATES` (off). `run_command` strictly allow-listed (5 commands); `tinker`/`env`/unknown refused; option injection refused ("doesn't take … here"); arguments validated per-command.
- **SQL injection:** sort/filter/search on both site listings (`/properties?sort=…`, `field.`, filter params) and admin lists (`?sort=`, `search=`) — `ListQuery` only accepts sort columns from an allow-list (`in_array(ltrim($sort,'-'), $sortable)`) and qualifies the column; quotes/`sleep()`/`-->`/backtick payloads all returned 200 with no timing change and no error. Admin `users?sort=password` ignored (not sortable).
- **Stored XSS:** titles/intro/textarea rendered escaped (`{{ }}`), including `</script>` in SEO title (shown as `&lt;/script&gt;`). Rich text sanitised on save (symfony/html-sanitizer): `onclick`, `<script>`, `<iframe>`, `<svg onload>` stripped; `<a href>` forced `rel="noopener noreferrer"`; `javascript:`/`data:` link schemes rejected at validation (`SafeUrl`). JSON-LD uses `JSON_HEX_TAG|JSON_HEX_AMP|…` so `</script>` can't break out. Only `dangerouslySetInnerHTML` in the admin UI is the Help reader, fed by a sanitised Markdown pipeline (`html_input=escape`, allow_unsafe_links=false, HtmlSanitizer).
- **CSRF:** every state-changing admin route, the preferences API, site forms and comments reject missing/invalid tokens (419), including with `X-Requested-With`. Honeypot: posting a form without the honeypot fields triggers the spam responder (no submission stored). 2FA code flow uses `hash_equals`, 5 attempts then lockout (10 attempts / 15 min), 10-min code expiry, session regenerated on success.
- **Auth/sessions:** session id rotates on login; logout invalidates + regenerates token and the old cookie is dead; "sign out everywhere" kills other sessions AND the remember-me cookie AND AI tokens immediately (verified a remember-only session dropped to login); lock blocks login even with the right password and is reversible with `sunrice:unlock`. Login throttle counts pair/IP/account; `X-Forwarded-For` does not bypass it (app doesn't trust the header). Security headers on admin: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: same-origin`; session cookie `HttpOnly; SameSite=Lax`.
- **Cache:** signed-in users are never served from / written to the page cache (`forVisitor` checks `auth()->check()`); guest cache key ignores query string and Host (so no query/Host poisoning — same key, and poisoned Host does not persist into later clean responses). Search pages and previews are never cached. Preview links are signed + `expires`, `Cache-Control: no-store`, `X-Robots-Tag: noindex`; tampering the signature → 403. Sitemap excludes drafts and the `noindex` Terms page; Host in sitemap `<loc>` is not persisted.
- **In-use media delete** requires `force`; unforced delete of an in-use file is refused.

## Test content created (all cleaned up)
- Users: "QA Fajar NoAccess" (fajar@, no role) — deleted; "QA Gita TempAdmin" (gita-qa@, administrator) — deleted. No roles added/changed (verified role table unchanged).
- Entries: QA page draft (id 49) + its copy — trashed then force-deleted; a duplicated journal draft (id 48, as Citra) — force-deleted.
- AI tokens: "QA security RO", "QA security full" (Ayu) — revoked.
- Media: a.php/polyglots rejected; c.gif, qa-trav.png, qa.PHP.png uploaded then bulk-deleted (storage dir back to just lumina-logo.svg).
- Form submission: one contact submission (header-injection test) — deleted. (The qa.frontend/qa.fast submissions in the table belong to the concurrent frontend QA agent, not this pass.)
- Password-reset tokens for fajar@ — row deleted.
- Translator/temp-admin structural changes (comments_closed on 30, parent_id on 37, order of 38) were reverted as Ayu.
- Eko was briefly locked to test lock/unlock, then unlocked. Ayu never locked.
