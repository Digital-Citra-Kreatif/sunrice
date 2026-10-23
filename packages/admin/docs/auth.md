# Signing in

The panel has its own login, logout and password reset pages under its path (`/admin/login`, `/admin/forgot-password`). They use the panel's guard (`$panel->guard('web')`, a session guard) and the app's user model.

## Who can sign in

Signing in needs the right password **and** the `sunrice.access` permission ("Open the admin panel"): an app's customers can share the users table without getting into the admin. An account without it gets the same answer as a wrong password ("These credentials do not match our records.") and stays signed out: a different message would confirm both the account and its password. The attempt counts towards the brute-force limits and is logged as a "login refused" security event, so you can see why someone couldn't sign in. Every panel route checks the same permission. Give it through a role, or make someone a super admin with `php artisan sunrice:make-admin` (see [Roles and permissions](permissions.md)).

## Two-factor login

Turn on **Two-factor login** in Settings → Security (permission `sunrice.settings.security`): after the right password, the panel emails a 6-digit code, and the person is signed in only once they type it. It's recommended for every live site: a leaked or guessed password alone then doesn't open the panel, and administrators' accounts are the ones worth attacking. It's for every account at once; to keep it on whatever the setting says, set `sunrice-admin.auth.two_factor` to `true`.

- A code works for 10 minutes and allows 5 guesses; a new one can be sent once a minute. Wrong codes also count per account (10 within 15 minutes lock the codes out), so logging in again for fresh codes doesn't give endless guesses. Only a keyed hash of the code is kept, in the session.
- The code goes to the account's `email`, right away (not queued). It's in the body only, not the subject ("Your … login code"), since phones show subjects on locked screens; set `auth.two_factor_code_in_subject` (`SUNRICE_LOGIN_CODE_IN_SUBJECT=true`) to put it in the subject too. When the email can't be sent, nobody is signed in and the login form says so: make sure your app can send email before you turn it on. Settings → Security refuses to turn it on while the mailer only writes to the log (`log` or `array`, as Settings → Mail shows), and `sunrice:two-factor on` warns.
- Locked out because email stopped working? `php artisan sunrice:two-factor off` (and `on`, or no argument to see it). `sunrice-admin.auth.two_factor` (`true` or `false`) forces it whatever the setting says; `null`, the default, follows the setting.
- Codes are typed at `/admin/login/code` (`sunrice.admin.login.code`), limited to 10 tries and resends a minute per IP.

## Strong passwords

**Require strong passwords** in Settings → Security (permission `sunrice.settings.security`) is on by default, for new and existing installs. Every password the panel sets then needs:

- `auth.password_min` characters or more (12 by default; a lower number than 8 counts as 8),
- upper and lower case letters, a number and a symbol (Unicode counts: `É` is upper case, a space or `€` a symbol),
- not to be the person's email address or name (whatever the case),
- not to have appeared in a known data leak (below).

Each rule it misses is its own message ("Use at least 12 characters.", "Add a symbol, such as ! or #." …), and the forms show the rules as a checklist under the new password, ticked as they're met; it comes from the server (`PasswordPolicy::checklist()`), so it always says what is enforced. The email, name and leak checks are only on the server: a leaked password gets "This password has appeared in a data leak. Choose a different one."

It applies everywhere the panel sets a password: creating and editing users (the `Password` form field), **Your account**, the reset and invitation pages, **Choose a stronger password** (below) and `sunrice:make-admin` (`--password` too; asked interactively, it explains the rules when one is refused). AI agents can't set or read passwords at all: `get_record` leaves password fields out, and `save_record` refuses them (a new user is invited to choose their own).

With the setting off, the panel uses your app's `Password::defaults()` as before (Laravel's default is 8 characters; set your own in a service provider), and the checklist shows what those ask for. `sunrice-admin.auth.strong_passwords` (`true` or `false`) forces it either way whatever the setting says, like `auth.two_factor`; `null`, the default, follows the setting.

| Key | Default | |
| --- | --- | --- |
| `auth.strong_passwords` | `null` | `true` or `false` forces strong passwords on or off; `null` follows Settings → Security. |
| `auth.password_min` | `12` | The shortest strong password (at least 8). |
| `auth.password_uncompromised` | `true` (`SUNRICE_PASSWORD_UNCOMPROMISED`) | Refuse passwords from known data leaks. |

**Data leaks.** The password is checked against [Have I Been Pwned's Pwned Passwords](https://haveibeenpwned.com/Passwords) by k-anonymity: only the first 5 characters of its SHA-1 hash are sent (with padding, so the answer's size says nothing either), and the match is made on your server. It's asked only once the other rules pass, with a 3-second timeout, and it **fails open**: when the service can't be reached or answers with an error, the password is accepted and a warning is logged (at most once an hour). Turn it off with `SUNRICE_PASSWORD_UNCOMPROMISED=false` (an offline server, say).

In your own code, the same rules are one call (`@api`):

```php
use Sunrice\Admin\Auth\PasswordPolicy;

$request->validate(['password' => ['required', 'string', 'max:255', 'confirmed', ...PasswordPolicy::rules($user)]]);

PasswordPolicy::strong();      // whether strong passwords are on
PasswordPolicy::checklist();   // ['min' => 12, 'mixedCase' => true, 'letters' => false, 'numbers' => true, 'symbols' => true]
PasswordPolicy::problems($password, $user); // what it lacks, keyed by rule: ['length' => 'Use at least 12 characters.', …]
```

`rules($user)` refuses that account's email address and name; on a form that also sends `email` and `name` (a new account), those are refused too.

### Weak passwords at sign-in

Passwords set before strong passwords were on (or by another app sharing the users table) may not meet the rules. **Ask people with a weak password to change it when they sign in**, under it in Settings → Security, is on by default: at a successful login, the password just typed is checked against the rules, leak check included (one request to the service, failing open, only after the password was found right). A weak one keeps the person signed in, but every panel page leads to **Choose a stronger password** (`/admin/stronger-password`, `sunrice.admin.password.stronger`) until they choose one, typed twice; their current password isn't asked again, since they've just typed it. Then they go on to the page they asked for. Logging out stays open, and the panel's `fetch()` calls get a 409 answer meanwhile.

- Only a mark with the account's id is kept, in the session (never anything about the password): it ends with the session, and nothing is stored in the database. With two-factor login, the password is checked at the first step and the mark counts once the code signs them in.
- Someone signed in some other way (no password typed at the panel's login), and AI access tokens, aren't affected.
- Both are [security events](security.md#security-events): `weak password at sign-in` (its note says which rules it failed, such as `fails: length, symbols`) and `weak password changed`.

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

"Forgot password?" emails a link to the panel's reset page (not the app's own `password.reset` route), through Laravel's password broker: set `auth.passwords` in `config/sunrice-admin.php` to use another broker. Both forms answer the same whether or not the email has an account (the reset form treats an unknown email as a wrong token). New passwords follow the [password rules](#strong-passwords), and resetting signs out "remember me" sessions.

Publish the config with `php artisan vendor:publish --tag=sunrice-admin-config`.
