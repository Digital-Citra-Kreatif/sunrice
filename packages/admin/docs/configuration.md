# Configuration

The panel's settings for developers live in `config/sunrice-admin.php` (key `sunrice-admin`). Publish it to change them:

```bash
php artisan vendor:publish --tag=sunrice-admin-config
```

(`sunrice:install` publishes it when you don't have it yet.) What editors change in the panel itself (the branding, two-factor login) is in **Settings**, not here: see [Settings](settings.md). The panel's path, guard and name are set on the panel in your [panel provider](panel.md), and mail the Laravel way in `.env` ([Mail](mail.md)). The CMS has its own file: see [CMS configuration](../../cms/docs/configuration.md).

A dash in the *Env* column means the key has no environment variable: change it in the published file.

## General

| Key | Default | Env | |
| --- | --- | --- | --- |
| `super_admin_role` | `'super-admin'` | — | The role whose users may do everything (`Gate::before`). Created by `sunrice:sync-permissions`, given by `sunrice:make-admin`. See [Roles and permissions](permissions.md#super-admins). |
| `font.stylesheet` | Instrument Sans from Bunny Fonts | — | A stylesheet loaded on every admin page for the typeface; `null` loads none. Settings → Branding's font wins. See [Branding](branding.md#fonts). |
| `font.family` | `null` | — | The font family the panel uses instead of Instrument Sans (`--sunrice-font`). |

## Signing in

| Key | Default | Env | |
| --- | --- | --- | --- |
| `auth.passwords` | `null` | — | The password broker for "Forgot password?" (`config/auth.php` `passwords.*`); `null` for the app's default. |
| `auth.two_factor` | `null` | — | `true` or `false` forces [two-factor login](auth.md#two-factor-login) on or off, whatever Settings → Security says; `null` follows the setting. |
| `auth.strong_passwords` | `null` | — | `true` or `false` forces [strong passwords](auth.md#strong-passwords) on or off, whatever Settings → Security says; `null` follows the setting (on by default). Off: your app's `Password::defaults()`. |
| `auth.password_min` | `12` | — | The shortest strong password (never fewer than 8). |
| `auth.password_uncompromised` | `true` | `SUNRICE_PASSWORD_UNCOMPROMISED` | Refuse strong passwords found in known data leaks (Have I Been Pwned, by k-anonymity; accepted when it can't be reached). |
| `auth.throttle.attempts`, `auth.throttle.decay_seconds` | `5`, `60` | — | Wrong passwords for the same email from the same IP before waiting, and for how long. |
| `auth.throttle.per_ip`, `auth.throttle.per_ip_decay_seconds` | `20`, `60` | — | Wrong passwords from one IP for any emails (password spraying). |
| `auth.throttle.per_account`, `auth.throttle.account_lock_seconds` | `30`, `900` | — | Wrong passwords for one account from any IPs before it's locked, and for how long. |
| `auth.throttle.password_resets_per_minute` | `5` | — | Forgot and reset password requests per IP a minute. |

See [Signing in](auth.md#brute-force-protection).

## Media

| Key | Default | Env | |
| --- | --- | --- | --- |
| `media.disk` | `'public'` | — | The Laravel disk uploads are stored on. |
| `media.directory` | `'media'` | — | The folder on it (`{directory}/{Y}/{m}/{random}-{name}.{ext}`). |
| `media.trash_disk`, `media.trash_directory` | `'local'`, `'sunrice-media-trash'` | — | Where files in the trash are kept (at their paths under the directory), off the public disk: a disk never served by URL. See [Media](media.md#the-trash). |
| `media.max_upload_kb` | `20480` | — | The largest upload, in KB (PHP's `upload_max_filesize` and `post_max_size` cap it too). |
| `media.allowed_extensions` | images, documents, archives, audio, video, fonts, `json` | — | File types that may be uploaded. Scripts, executables and HTML are always refused. |
| `media.image_sizes` | `thumbnail` 300×300 crop, `medium` 800 wide, `large` 1600 wide | — | Smaller copies of every image: `name => [width, height, mode]` with mode `'crop'` or `'fit'`. Run `sunrice:media-sizes` after changing them. |
| `media.queue` | `null` | — | Make image sizes on the queue (`true`), during the upload (`false`), or on the queue whenever the default queue connection isn't `sync` (`null`). |
| `media.max_pixels` | `40000000` | — | Images larger than this (width × height) get no sizes. |
| `media.optimize.max_width`, `media.optimize.max_height` | `2560`, `2560` | — | `sunrice:optimize-images` scales larger images down to fit. |
| `media.optimize.quality` | `82` | — | Its JPEG, WebP and AVIF quality. |
| `media.optimize.backup`, `media.optimize.backup_disk`, `media.optimize.backup_directory` | `true`, `'local'`, `'sunrice-originals'` | — | Keep each image's first original there, for `--restore`. |

See [Media](media.md).

## Edit locks and the activity log

| Key | Default | Env | |
| --- | --- | --- | --- |
| `locks.store` | `null` | — | The cache store edit locks live in; `null` for the default. Every server must share it. See [Edit locks](locks.md). |
| `activity.enabled` | `true` | — | Record the activity log and security events. |
| `activity.keep_days` | `365` | — | Lines older than this are deleted daily by `sunrice:activity-prune`; `null` keeps them forever. See [Activity log](activity.md). |

## Captcha

| Key | Default | Env | |
| --- | --- | --- | --- |
| `captcha.provider` | `'turnstile'` | `SUNRICE_CAPTCHA_PROVIDER` | `turnstile`, `recaptcha` or `hcaptcha`. |
| `captcha.site_key` | `null` | `SUNRICE_CAPTCHA_SITE_KEY` | The provider's site key. |
| `captcha.secret_key` | `null` | `SUNRICE_CAPTCHA_SECRET_KEY` | The provider's secret key. The captcha is off until both keys are set. |

See [Security: captcha](security.md#captcha).

## Security

| Key | Default | Env | |
| --- | --- | --- | --- |
| `security.csp.enabled` | `true` | `SUNRICE_CSP_ENABLED` | Send the panel's strict `Content-Security-Policy`, with a new nonce on every request. See [Content-Security-Policy](security.md#content-security-policy). |
| `security.csp.report_only` | `false` | `SUNRICE_CSP_REPORT_ONLY` | Send it as `Content-Security-Policy-Report-Only`: report, don't block. |
| `security.csp.report_uri` | `null` | `SUNRICE_CSP_REPORT_URI` | Where browsers send violation reports (`report-uri`). |
| `security.csp.sources` | `[]` | — | More sources per directive: `['img-src' => ['https://images.example.com']]`. |
| `security.headers.csp` | `null` | `SUNRICE_CSP` | A whole `Content-Security-Policy` of your own, sent as is instead of the panel's (no nonce added). |
| `security.log_channel` | `null` | `SUNRICE_SECURITY_LOG_CHANNEL` | Also write security events to this log channel (e.g. `daily`). |
| `security.alerts.enabled` | `true` | `SUNRICE_SECURITY_ALERTS` | Email [security alerts](security.md#email-alerts). |
| `security.alerts.roles` | `[]` | — | Roles whose users get alerts, besides super admins. |
| `security.alerts.emails` | `[]` | — | Addresses that get alerts too. |
| `security.alerts.cooldown_minutes` | `60` | — | The same alert for the same account at most this often. |
| `security.alerts.dormant_days` | `90` | — | A login after this many days without one is an alert. |
| `security.alerts.exports` | `5` | — | CSV exports by one person in an hour before an alert. |
| `security.alerts.mass_deletes` | `20` | — | Deletes, trashes and unpublishes by one person or agent in 10 minutes before an alert. |
| `security.alerts.forbidden` | `15` | — | Pages refused to one account in 10 minutes before an alert (and how many `page refused` events are recorded in that time). |

## AI access

| Key | Default | Env | |
| --- | --- | --- | --- |
| `ai.enabled` | `true` | — | The MCP endpoint for AI agents. (`$panel->aiAccess(false)` turns off the whole feature, page included.) |
| `ai.path` | `'mcp'` | — | Its path (`https://example.com/mcp`). |
| `ai.requests_per_minute` | `120` | — | Requests per minute per token. |
| `ai.local_user` | `null` | `SUNRICE_AI_USER` | The email of the user a local agent acts as over stdio (`php artisan mcp:start sunrice`); unset, it's refused. |
| `ai.error_log` | `true` | `SUNRICE_AI_ERROR_LOG` | Let agents with `sunrice.ai.error-log` read recent errors (`read_error_log`). |
| `ai.log_path` | `null` | `SUNRICE_AI_LOG_PATH` | The logs' directory; `null` for `storage/logs`. |
| `ai.commands` | the panel's maintenance commands and the `*:clear` commands | — | The artisan commands agents with `sunrice.ai.run-commands` may run (`run_command`); `name => [options]` limits the options. |

See [AI access](ai.md).
