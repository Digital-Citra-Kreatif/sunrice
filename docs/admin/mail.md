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

Users with `sunrice.settings.mail` see **Settings → Mail**: the mailer (and server and port for SMTP), the from address and the queue connection, read from the app's config; passwords are never shown. Nothing there is edited in the panel.

**Send a test email** sends a message to your own account's address right away (`POST /admin/settings/mail/test`, route `sunrice.admin.settings.mail.test`) and says whether it went, or why not (the mail server's error). It can be used 3 times a minute per person. Do this before turning on two-factor login: if codes can't be delivered, nobody can log in.

The key `mail` belongs to this section; give your own sections another one.
