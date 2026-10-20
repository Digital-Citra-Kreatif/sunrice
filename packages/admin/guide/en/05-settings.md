# Settings

**Settings** under Administration gathers the options for the whole site, in sections listed on the left. You see only the sections your role may edit, and each section saves on its own with **Save**.

The panel always has these sections:

- **Branding**: the panel's name, logo, font, colour and sign-in page. See [Appearance and branding](guide:panel/appearance).
- **Security**: two-factor login (below).
- **Mail**: how the panel sends email, and a test email (below).

Plugins add their own. The website (CMS) adds **Site**, **Languages**, **Site code** and **Comments**: see [The website's settings](guide:website/settings).

Secret values, such as passwords for other services, are never shown again after saving: the field says *Saved. Leave empty to keep it.*

## Security: two-factor login

Turn on **Two-factor login** to make everyone type a 6-digit code, sent to their email address, after their password. Someone who learns a password still can't get in without the person's mailbox.

> [!WARNING]
> Two-factor login needs working email. Before you turn it on, send yourself a test email from **Settings → Mail**. If codes can't be delivered, nobody can log in.

Turning it off is recorded and emailed to the people who receive security alerts, because it weakens every account. If your developer has fixed it on or off for the whole site, the switch has no effect.

> [!TECHNICAL]
> Locked out because email stopped working? On the server: `php artisan sunrice:two-factor off`. See [Signing in](docs:panel/auth#two-factor-login).

## Mail

**Settings → Mail** shows how the panel sends email: the mailer, the server, the *From* address and how queued mail is sent. These come from the server's configuration, so nothing here is edited in the panel, and passwords are never shown.

**Send a test email** sends a message to your own email address straight away and tells you whether it went, or the mail server's error if it didn't. Use it after your developer changes the mail settings, and before turning on two-factor login. You can send three a minute.

If the card warns that mail isn't delivered (the *log* mailer, for example), messages only go to a log file: tell your developer.

The panel sends password reset links, login codes, security alerts and test emails; the website adds form and comment notifications.

> [!TECHNICAL]
> Mail is set up in `.env` (`MAIL_MAILER`, `MAIL_HOST` …), and queued mail needs a queue worker. See [Mail](docs:panel/mail).
