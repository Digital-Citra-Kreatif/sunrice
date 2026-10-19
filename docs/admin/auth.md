# Signing in

The panel has its own login, logout and password reset pages under its path (`/admin/login`, `/admin/forgot-password`). They use the panel's guard (`$panel->guard('web')`, a session guard) and the app's user model.

## Who can sign in

Signing in needs the right password **and** the `sunrice.access` permission ("Open the admin panel"): an app's customers can share the users table without getting into the admin. An account without it gets "This account can't use the admin panel." and stays signed out. Every panel route checks the same permission. Give it through a role, or make someone a super admin with `php artisan sunrice:make-admin` (see [Roles and permissions](permissions.md)).

## Brute-force protection

Wrong passwords are counted three ways (`config/sunrice-admin.php`, `auth.throttle`); the first limit reached makes the person wait:

| Key | Default | Counts |
| --- | --- | --- |
| `attempts`, `decay_seconds` | 5 per 60 s | the same email from the same IP |
| `per_ip`, `per_ip_decay_seconds` | 20 per 60 s | one IP trying any emails (password spraying) |
| `per_account`, `account_lock_seconds` | 30, locked 900 s | one account from any IPs (a botnet) |
| `password_resets_per_minute` | 5 | forgot and reset password requests per IP |

A successful login forgives that email's mistakes. Failed logins are logged as notices and limits reached as warnings, with the email and IP, so tools like fail2ban can watch the log.

## Events

Laravel's auth events fire as usual (`Illuminate\Auth\Events\Failed`, `Login`, `Logout`, `PasswordReset`). When a limit is reached the panel also fires `Sunrice\Admin\Events\LoginThrottled` with the email, IP, `scope` (`pair`, `ip` or `account`) and the seconds to wait.

## Password reset

"Forgot password?" emails a link to the panel's reset page (not the app's own `password.reset` route), through Laravel's password broker: set `auth.passwords` in `config/sunrice-admin.php` to use another broker. The form answers the same whether or not the email has an account. New passwords follow `Password::defaults()`, and resetting signs out "remember me" sessions.

Publish the config with `php artisan vendor:publish --tag=sunrice-admin-config`.
