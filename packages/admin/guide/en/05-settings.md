# Settings

**Settings** under Administration gathers the options for the whole site, in sections listed on the left. You see only the sections your role may edit, and each section saves on its own with **Save**.

The panel always has these sections:

- **Branding**: the panel's name, logo, font, colour and sign-in page. See [Appearance and branding](guide:panel/appearance).
- **Security**: two-factor login and password rules (below).
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

## Security: passwords

**Require strong passwords** is on unless you turn it off. Every new password, whoever sets it, then needs 12 characters or more, upper and lower case letters, a number and a symbol. It can't be the person's email address or name, and a password that has appeared in a known data leak is refused. Under every new password a checklist ticks each rule as it's met.

**Ask people with a weak password to change it when they sign in** (on by default) catches passwords chosen before the rules: someone who signs in with one is asked to **Choose a stronger password** before they can do anything else. Both are recorded in the [activity log](guide:panel/activity-log).

Turned off, passwords follow the rules your developer set for the whole app (usually 8 characters or more). If your developer has fixed it on or off for the whole site, the switch can't be changed.

> [!TECHNICAL]
> The length, the data leak check and forcing the setting are in `config/sunrice-admin.php`. See [Strong passwords](docs:panel/auth#strong-passwords).

## Mail

**Settings → Mail** shows how the panel sends email: the mailer, the server, the *From* address and how queued mail is sent. These come from the server's configuration, so nothing here is edited in the panel, and passwords are never shown.

**Send a test email** sends a message to your own email address straight away and tells you whether it went, or the mail server's error if it didn't. Use it after your developer changes the mail settings, and before turning on two-factor login. You can send three a minute.

If the card warns that mail isn't delivered (the *log* mailer, for example), messages only go to a log file: tell your developer. It also warns while mail is sent from an example address such as *hello@example.com*: replies go nowhere and spam filters may drop the mail, so ask your developer to set your own address.

The panel sends password reset links, login codes, security alerts and test emails; the website adds form and comment notifications.

> [!TECHNICAL]
> Mail is set up in `.env` (`MAIL_MAILER`, `MAIL_HOST` …), and queued mail needs a queue worker. See [Mail](docs:panel/mail).
