# Signing in

The panel has its own login, logout and password reset pages under its path (`/admin/login`, `/admin/forgot-password`). They use the panel's guard (`$panel->guard('web')`, a session guard) and the app's user model.

## Who can sign in

Signing in needs the right password **and** the `sunrice.access` permission ("Open the admin panel"): an app's customers can share the users table without getting into the admin. An account without it gets the same answer as a wrong password ("These credentials do not match our records.") and stays signed out: a different message would confirm both the account and its password. The attempt counts towards the brute-force limits and is logged as a "login refused" security event, so you can see why someone couldn't sign in. Every panel route checks the same permission. Give it through a role, or make someone a super admin with `php artisan sunrice:make-admin` (see [Roles and permissions](permissions.md)).

## Two-factor login

Turn on **Two-factor login** in Settings → Security (permission `sunrice.settings.security`): after the right password, the panel emails a 6-digit code, and the person is signed in only once they type it.

- A code works for 10 minutes and allows 5 guesses; a new one can be sent once a minute. Wrong codes also count per account (10 within 15 minutes lock the codes out), so logging in again for fresh codes doesn't give endless guesses. Only a keyed hash of the code is kept, in the session.
- The code goes to the account's `email`, right away (not queued). When the email can't be sent, nobody is signed in and the login form says so: make sure your app can send email before you turn it on. Settings → Security refuses to turn it on while the mailer only writes to the log (`log` or `array`, as Settings → Mail shows), and `sunrice:two-factor on` warns.
- Locked out because email stopped working? `php artisan sunrice:two-factor off` (and `on`, or no argument to see it). `sunrice-admin.auth.two_factor` (`true` or `false`) forces it whatever the setting says; `null`, the default, follows the setting.
- Codes are typed at `/admin/login/code` (`sunrice.admin.login.code`), limited to 10 tries and resends a minute per IP.

## Brute-force protection

Wrong passwords are counted three ways (`config/sunrice-admin.php`, `auth.throttle`); the first limit reached makes the person wait:

| Key | Default | Counts |
| --- | --- | --- |
| `attempts`, `decay_seconds` | 5 per 60 s | the same email from the same IP |
| `per_ip`, `per_ip_decay_seconds` | 20 per 60 s | one IP trying any emails (password spraying) |
| `per_account`, `account_lock_seconds` | 30, locked 900 s | one account from any IPs (a botnet) |
| `password_resets_per_minute`, `password_resets_per_hour` | 5, 20 | forgot and reset password requests per IP (the hourly cap stops one address mailing reset links to address after address); reaching either is a `password reset blocked` security event and an alert |

A successful login forgives that email's mistakes. Failed logins are logged as notices and limits reached as warnings, with the email and IP, so tools like fail2ban can watch the log; attempts made while blocked are [security events](security.md#security-events) too, once a minute per email and IP. An email with no account costs a password check all the same (against a hash made once with your hasher), so how long the answer takes doesn't say who has an account.

## Events

Laravel's auth events fire as usual (`Illuminate\Auth\Events\Failed`, `Login`, `Logout`, `PasswordReset`). When a limit is reached the panel also fires `Sunrice\Admin\Events\LoginThrottled` with the email, IP, `scope` (`pair`, `ip` or `account`) and the seconds to wait.

## Password reset

"Forgot password?" emails a link to the panel's reset page (not the app's own `password.reset` route), through Laravel's password broker: set `auth.passwords` in `config/sunrice-admin.php` to use another broker. Both forms answer the same whether or not the email has an account (the reset form treats an unknown email as a wrong token). New passwords follow `Password::defaults()`, and resetting signs out "remember me" sessions.

Publish the config with `php artisan vendor:publish --tag=sunrice-admin-config`.
