# Mail

The panel sends a few emails, all through Laravel's mailer. Nothing is panel-specific: mail is set up the Laravel way, in `.env` (`config/mail.php`).

## What the panel sends

| Email | When | Queued? |
| --- | --- | --- |
| Password reset link | Someone uses **Forgot password?** on the login page. | No, sent during the request. |
| Login code | Every login, when two-factor login is on (Settings → Security). | No: the person is waiting for it. |
| Security alerts | Something on the [security alerts](security.md#email-alerts) list happens; one email per recipient. | **Yes.** |
| Test email | **Send a test email** on Settings → Mail. | No, so a mail server problem shows on the page. |

Plugins send their own (the CMS, for example, its form and comment notifications); their docs say which.

## The logo at the top of emails

Every email the panel sends shows a brand at the top: a logo, with the name as its alternative text, or the name alone. Without a plugin that gives its own, it's Settings → Branding's name, with its logo when that's an image email clients show (a PNG, JPEG or GIF; never an SVG, which most don't show), at most 48 pixels tall and 240 wide. Without one the top shows the name, as Laravel's emails do. The same name signs the panel's notifications ("Regards, Acme") and is in their footer ("© 2026 Acme."), in place of the app's name (`app.name`); the plain-text part starts with it and the app's URL. Their wording ("a test email from the Acme admin panel", subjects) keeps naming the panel.

`Panel::mailBrand()` (`@api`) gives what's shown, a `Sunrice\Admin\Mail\MailBrand` (`@api`): `name`, `logo` (an absolute URL, or null), and the `width` and `height` it's shown at. A plugin gives its own from its `register()`, once (a second one is an error):

```php
use Sunrice\Admin\Mail\MailBrand;

$panel->mailBrandUsing(fn (): MailBrand => MailBrand::image('Acme', $pngUrl, 192, 96)); // the image's pixel size
// or MailBrand::name('Acme'): the name alone
```

`MailBrand::image($name, $url, $pixelWidth, $pixelHeight)` fits the image in 48 × 240 pixels (`MailBrand::HEIGHT`, `MAX_WIDTH`), never larger than it is, so an image twice that size is sharp on every screen; it makes a relative URL (`/storage/…`) absolute on `APP_URL`, and gives the name alone for an SVG. It's resolved each time an email is drawn (a queued one, when the worker sends it). The CMS gives the site's name and logo: see [the CMS's site icon and logo](../../cms/docs/site.md#site-icon-and-logo).

The panel's notification emails (password reset, invitation, login code, test email) use the markdown template `sunrice-admin::mail.notification`, Laravel's own with the brand at the top (the markdown component `x-mail::sunrice-brand`, which the panel adds beside the app's mail components without replacing any). Emails written as HTML views (the security alert, plugins' own) show it with the Blade component `<x-sunrice-admin::mail-brand />` (`@api`; `:fallback="false"` shows nothing without a logo, for an email that names the brand itself), with inline styles only:

```blade
<body style="font-family: sans-serif;">
    <x-sunrice-admin::mail-brand />
    …
```

## The mailer

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=null          # "smtps" for implicit TLS on port 465
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=postmaster@example.com
MAIL_PASSWORD=secret
MAIL_FROM_ADDRESS=hello@example.com
MAIL_FROM_NAME="${APP_NAME}"
```

`MAIL_FROM_ADDRESS` must be an address your provider lets you send from, or mail is refused or lands in spam. The `log` and `array` mailers deliver nothing: messages only go to the log, and the Mail settings say so. After changing `.env` on a server with a cached config, run `php artisan config:clear` (or `config:cache` again).

## The queue worker

Queued mail goes through the default queue connection (`QUEUE_CONNECTION`). With `sync` it's sent during the request; with `database`, `redis` or another queue, run a worker, or it's never sent:

```bash
php artisan queue:work
```

Keep the worker running with Supervisor, systemd or your host's process manager, and restart it after each deploy (`php artisan queue:restart`).

## Settings → Mail

Users with `sunrice.settings.mail` see **Settings → Mail**: the mailer (and server and port for SMTP), the from address and the queue connection, read from the app's config; passwords are never shown. Nothing there is edited in the panel. It warns while the from address is on a domain reserved for examples (`hello@example.com`, Laravel's default, or `.test`, `localhost` …): set `MAIL_FROM_ADDRESS`.

**Send a test email** sends a message to your own account's address right away (`POST /admin/settings/mail/test`, route `sunrice.admin.settings.mail.test`) and says whether it went, or why not (the mail server's error). It can be used 3 times a minute per person. Do this before turning on two-factor login: if codes can't be delivered, nobody can log in.

The key `mail` belongs to this section; give your own sections another one.
