# Security

The panel guards its own login (see [Signing in](auth.md)), checks every upload (see [Media](media.md#whats-refused)) and records **security events**: what matters for spotting a break-in, including attempts by people who aren't signed in yet.

## Security events

They're lines in the [activity log](activity.md) with the source **Security**, about an account (filter the log by source to see only them; click one to open that user):

| Action | When |
| --- | --- |
| `logged in`, `logged out` | Someone signed in to or out of the panel. |
| `login failed` | A wrong password, or an email with no account (then the email typed is shown). |
| `login refused` | The right password for an account that may not open the panel (`sunrice.access`). |
| `login blocked` | Too many wrong passwords from one IP, or for one email from one IP (once, when the limit is reached), or a locked account trying to log in or open a page. |
| `account locked` | Too many wrong passwords for one account from any IPs: it's locked for `account_lock_seconds`. |
| `password reset requested`, `password reset` | The "Forgot password?" form (also for emails without an account) and a completed reset. |
| `upload blocked` | A script, executable or web page, or an SVG that could run code, uploaded to the media library. |
| `exported` | A resource's CSV export (exports can carry personal data out of the panel). |
| `roles changed`, `permissions changed` | A user's roles or a role's permissions changed, with what was added and removed. |
| `signed out everywhere`, `account locked by admin`, `account unlocked` | An administrator acted on an account (see below). |

Each line keeps the IP and, in its properties, the browser's user agent.

## When an account may be in the wrong hands

On **Users**, each row's menu has:

- **Sign out everywhere**: ends all of the user's sessions and "remember me" logins. Sessions that signed in before end on their next page, whatever your session driver; with the `database` driver they're deleted at once. They can log in again with their password (change it first if it may have leaked). Signing out yourself keeps your current session.
- **Lock account**: signs them out everywhere, and they can't log in or open a panel page until someone unlocks the account. Nothing is deleted. Nobody can lock their own account.
- **Unlock account**: lets them log in again; it also lifts a lock after too many wrong passwords. From the console: `php artisan sunrice:unlock rina@example.com`.

The list's **Locked** column shows locked accounts. Who may do this follows the Users screen: the `sunrice.users.update` permission and [who may manage whom](users.md#who-may-manage-whom). Each is a security event (`signed out everywhere`, `account locked by admin`, `account unlocked`), and a locked account's attempts are `login blocked`.

In code: `Sunrice\Admin\Security\AccountGuard` has `signOutEverywhere($user)`, `lock($user, $reason)`, `unlock($user)` and `isLocked($user)`.

## Elsewhere too

- **A log file:** set `SUNRICE_SECURITY_LOG_CHANNEL` (`security.log_channel` in `config/sunrice-admin.php`) to a log channel, e.g. `daily`, to also write each event there as a warning, for fail2ban or a log service.
- **Your code:** every event fires `Sunrice\Admin\Events\SecurityEventRecorded` (`action`, `account`, `label`, `properties`, `ip`, `activity`).
- **Your own events:**

```php
use Sunrice\Admin\Security\SecurityLog;

app(SecurityLog::class)->record('two-factor turned off', $user, null, ['note' => 'by an administrator']);
app(SecurityLog::class)->about('api key rotated', 'integration', $integration, 'Payment gateway');
```
