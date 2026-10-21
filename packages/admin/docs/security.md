# Security

The panel guards its own login (see [Signing in](auth.md)), checks every upload (see [Media](media.md#whats-refused)) and records **security events**: what matters for spotting a break-in, including attempts by people who aren't signed in yet.

## Security events

They're lines in the [activity log](activity.md) with the source **Security**, about an account (filter the log by source to see only them; click one to open that user):

| Action | When |
| --- | --- |
| `logged in`, `logged out` | Someone signed in to or out of the panel. |
| `login failed` | A wrong password, or an email with no account (then the email typed is shown), or a wrong [login code](auth.md#two-factor-login). |
| `login refused` | The right password for an account that may not open the panel (`sunrice.access`). |
| `login blocked` | Too many wrong passwords from one IP, or for one email from one IP: once when the limit is reached, then at most once a minute per email and IP while attempts keep coming (`tried while blocked`), so a sustained attack shows without flooding the log. Also a locked account trying to log in or open a page. |
| `account locked` | Too many wrong passwords for one account from any IPs: it's locked for `account_lock_seconds`. |
| `password reset requested`, `password reset` | The "Forgot password?" form (also for emails without an account) and a completed reset. |
| `password reset blocked` | Too many forgot or reset password requests from one IP (`password_resets_per_minute`, `password_resets_per_hour`), at most once a minute per IP. |
| `upload blocked` | A script, executable or web page, or an SVG that could run code, uploaded to the media library. |
| `exported` | A resource's CSV export (exports can carry personal data out of the panel). |
| `roles changed`, `permissions changed` | A user's roles or a role's permissions changed, with what was added and removed (`added` and `removed` lists in the properties, and a note). |
| `page refused` | A signed-in person was refused a panel page (403), at most `security.alerts.forbidden` times per account in 10 minutes. |
| `two-factor turned on`, `two-factor turned off` | [Two-factor login](auth.md#two-factor-login) was switched, in Settings → Security or from the console. |
| `token created`, `token revoked`, `tokens revoked`, `token used from new IP` | [AI access](ai.md) tokens. |
| `signed out everywhere`, `account locked by admin`, `account unlocked` | An administrator acted on an account (see below). |
| `log pruned` | Someone deleted old activity log lines with **Prune…** on the activity log page (how many, and the days kept). |

Each line keeps the IP and, in its properties, the browser's user agent.

## When an account may be in the wrong hands

On **Users**, each row's menu has:

- **Sign out everywhere**: ends all of the user's sessions and "remember me" logins, and revokes their [AI access tokens](ai.md) (a `tokens revoked` event of its own, so the log says what stopped). Sessions that signed in before end on their next page, whatever your session driver; with the `database` driver they're deleted at once. They can log in again with their password (change it first if it may have leaked) and create new tokens. Signing out yourself keeps your current session.
- **Lock account**: signs them out everywhere, and they can't log in or open a panel page until someone unlocks the account. Its dialog asks for an optional reason (up to 250 characters), shown on the user's page. Nothing is deleted. Nobody can lock their own account.
- **Unlock account**: lets them log in again; it also lifts a lock after too many wrong passwords. From the console: `php artisan sunrice:unlock rina@example.com`.

The list's **Locked** column shows locked accounts. The user's edit page has a **Security** card beside the form: whether the account is locked (when, by whom and why) or locked for a while after wrong passwords, when it was signed out everywhere, how many AI access tokens it has, its last login and IP, its latest 8 security events (with a link to all of them in the activity log), and the **Sign out everywhere** and **Revoke AI tokens** buttons. Who may do this follows the Users screen: the `sunrice.users.update` permission and [who may manage whom](users.md#who-may-manage-whom). Each is a security event (`signed out everywhere`, `account locked by admin`, `account unlocked`), and a locked account's attempts are `login blocked`.

In code: `Sunrice\Admin\Security\AccountGuard` has `signOutEverywhere($user)`, `lock($user, $reason)`, `unlock($user)` and `isLocked($user)`. Which accounts are locked or signed out is kept in the app's default cache (an hour at most) and forgotten by each of these, so panel pages don't ask the database; with several servers, use a shared cache store.

## Security headers

Every panel response (`PanelRoutes::middleware()`, so plugin routes too) carries `X-Frame-Options: DENY` (no other site can frame the panel), `Referrer-Policy: same-origin` and `X-Content-Type-Options: nosniff`. The site's own pages aren't touched.

A `Content-Security-Policy` is sent only when you set one, because what it must allow depends on how your app serves the panel's assets (Vite, a CDN, inline scripts in your layout). Set `SUNRICE_CSP` or `security.headers.csp` in `config/sunrice-admin.php` to the policy string:

```
SUNRICE_CSP="default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; frame-ancestors 'none'"
```

Check the browser console after setting it: the panel's React bundle and styles are served from `/vendor/sunrice-admin`, the media library shows images from your media disk, and the Vite dev server needs its own origin while you develop. A policy an earlier middleware already set is kept.

## Links in emails

Links in the panel's emails (password reset, security alerts) and the CMS's (moderation, submissions, replies) are built from `APP_URL`, not from the request's `Host` header, which whoever sends the request chooses: otherwise a "forgot password" request sent with `Host: evil.example` would email a reset link that hands the token to that host. So set `APP_URL` to the address people use. Plugins build their mail links the same way with `Sunrice\Admin\Support\AppUrl` (`@api`):

```php
use Sunrice\Admin\Support\AppUrl;

AppUrl::route('sunrice.admin.shop.orders.show', ['order' => $order->id]); // https://your-app.example/admin/...
AppUrl::url($entry->url()); // an absolute link moved from the request's host to APP_URL
```

Also turn on Laravel's `TrustHosts` middleware (`$middleware->trustHosts()` in `bootstrap/app.php`) or have your web server answer only your own host names, so the rest of the app (caches keyed by URL, redirects) never works with a made-up host.

## Developer tools

Inertia's DevTools record every request's page props (the users list with emails, settings) and, with `APP_ENV=local`, serve them at `/_inertia/devtools` to anyone, signed in or not. The panel turns them off unless your app turns them on itself (`INERTIA_DEVTOOLS_ENABLED=true`, or `inertia.devtools.enabled`); do that only on a machine nobody else can reach. Never run a reachable site with `APP_ENV=local` or `APP_DEBUG=true`.

## Elsewhere too

- **A log file:** set `SUNRICE_SECURITY_LOG_CHANNEL` (`security.log_channel` in `config/sunrice-admin.php`) to a log channel, e.g. `daily`, to also write each event there as a warning, for fail2ban or a log service.
- **Your code:** every event fires `Sunrice\Admin\Events\SecurityEventRecorded` (`action`, `account`, `label`, `properties`, `ip`, `activity`).
- **Your own events:**

```php
use Sunrice\Admin\Security\SecurityLog;

app(SecurityLog::class)->record('two-factor turned off', $user, null, ['note' => 'by an administrator']);
app(SecurityLog::class)->about('api key rotated', 'integration', $integration, 'Payment gateway');
```

## Email alerts

When something may be a break-in, the panel emails super admins, users with the roles in `security.alerts.roles`, the addresses in `security.alerts.emails` (`config/sunrice-admin.php`), and the roles and addresses chosen in Settings → Security (`SecuritySettings::$alertRoles`, `$alertEmails`). Each alert says what happened and what to do, and links to the security events:

| Alert | When |
| --- | --- |
| An account was locked after wrong passwords | `account locked` |
| One address is trying many accounts | Too many wrong passwords from one IP (password spraying). |
| A powerful account signed in from a new address | Someone with a privileged permission (or a super admin) signed in from an IP they hadn't used. |
| An account signed in after a long time | The first login in `dormant_days` (90). |
| Someone was given privileged access / A role was given privileged access | A user got the super admin role, a privileged permission or a role that has one (read from the event's `added` list). |
| One address keeps asking for password resets | `password reset blocked` |
| A dangerous upload was blocked | `upload blocked` |
| A new AI access token was created | `token created` |
| An AI access token was used from a new address | `token used from new IP` |
| Two-factor login was turned off | `two-factor turned off` |
| The activity log was pruned | `log pruned` |
| Someone keeps opening pages they may not see | `forbidden` (15) panel pages refused (`page refused`) to one signed-in account within 10 minutes. |
| Many exports in a short time | `exports` (5) CSV exports by one person within an hour. |
| Many things deleted in a short time | `mass_deletes` (20) deletes, trashes, unpublishes or purges (deleted content removed for good, `purged`) by one person or AI agent within 10 minutes (`SecurityAlerts::DESTRUCTIVE_ACTIONS`). |

**Privileged permissions** are the panel's `sunrice.users.*`, `sunrice.roles.*`, `sunrice.settings.*`, `sunrice.ai.*` (connecting agents, letting them read the error log and run commands) and `sunrice.activity.*`, plus what plugins register with `$panel->privilegedPermissions()` (the CMS: `cms.settings.code` and creating, editing and deleting blueprints and fieldsets); `Panel::isPrivilegedPermission()` tells.

The same alert for the same account is sent at most once per `cooldown_minutes` (60), and each one sent is an `alert sent` security event. Turn them off with `SUNRICE_SECURITY_ALERTS=false`. Each recipient's mail is queued (`Mail::queue()`), so set up a queue worker or the `sync` driver; they need your app's mail settings.

### Your own rules

The built-in rules aren't configurable classes: there's no registry of rules to add to. Your own rule is your own code that decides, then calls **`SecurityAlerts::raise()`** (`@api`): a rule name, a key (the same rule and key alert once per cooldown), a headline, what happened, what to do and optional details. The CMS does it when site code snippets change.

```php
app(\Sunrice\Admin\Security\SecurityAlerts::class)->raise('shop-payout-changed', (string) $shop->id,
    'The payout account was changed', "{$who} changed where payouts go.",
    'If nobody meant to, change it back and check who did it in the activity log.', ['Who' => $who]);
```

It goes to the same people (super admins, `security.alerts.roles`, `security.alerts.emails` and those chosen in Settings → Security), is queued, records an `alert sent` security event, does nothing when alerts are off, and never throws.

To watch the activity log or the security events the way the built-in rules do, listen to `Sunrice\Admin\Events\ActivityRecorded` (every new line, `$event->activity`) or `SecurityEventRecorded`, count what you need, and raise when a threshold is reached:

```php
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Sunrice\Admin\Events\ActivityRecorded;
use Sunrice\Admin\Security\SecurityAlerts;

// In a service provider's boot(): many refunds by one person within an hour.
Event::listen(function (ActivityRecorded $event): void {
    $line = $event->activity;
    if ($line->action !== 'refunded' || $line->user_id === null) {
        return;
    }
    $key = "shop-refunds:{$line->user_id}";
    Cache::add($key, 0, now()->addHour());
    if (Cache::increment($key) === 10) {
        app(SecurityAlerts::class)->raise('shop-many-refunds', (string) $line->user_id,
            'Many refunds in a short time', "{$line->user_name} refunded 10 orders within an hour.",
            'Check the refunds in the activity log, and lock the account if they weren\'t meant.');
    }
});
```

## Captcha

`Sunrice\Admin\Security\Captcha` gives plugins a captcha for public forms: Cloudflare Turnstile (the default), Google reCAPTCHA v2 or hCaptcha, set with `SUNRICE_CAPTCHA_PROVIDER`, `SUNRICE_CAPTCHA_SITE_KEY` and `SUNRICE_CAPTCHA_SECRET_KEY` (`sunrice-admin.captcha`). It's off until both keys are set.

```php
$captcha = app(\Sunrice\Admin\Security\Captcha::class);
$captcha->enabled();          // both keys set
$captcha->widget();           // the widget, with the provider's script once per page
$captcha->passes($request);   // the visitor's answer is right (always true when it's off)
```

When the provider can't be reached or refuses the secret key, the answer counts as wrong and a warning is logged. The CMS uses it for forms.
