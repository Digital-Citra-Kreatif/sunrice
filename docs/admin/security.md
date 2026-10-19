# Security

The panel guards its own login (see [Signing in](auth.md)), checks every upload (see [Media](media.md#whats-refused)) and records **security events**: what matters for spotting a break-in, including attempts by people who aren't signed in yet.

## Security events

They're lines in the [activity log](activity.md) with the source **Security**, about an account (filter the log by source to see only them; click one to open that user):

| Action | When |
| --- | --- |
| `logged in`, `logged out` | Someone signed in to or out of the panel. |
| `login failed` | A wrong password, or an email with no account (then the email typed is shown). |
| `login refused` | The right password for an account that may not open the panel (`sunrice.access`). |
| `login blocked` | Too many wrong passwords from one IP, or for one email from one IP (once, when the limit is reached). |
| `account locked` | Too many wrong passwords for one account from any IPs: it's locked for `account_lock_seconds`. |
| `password reset requested`, `password reset` | The "Forgot password?" form (also for emails without an account) and a completed reset. |
| `upload blocked` | A script, executable or web page, or an SVG that could run code, uploaded to the media library. |
| `exported` | A resource's CSV export (exports can carry personal data out of the panel). |
| `roles changed`, `permissions changed` | A user's roles or a role's permissions changed, with what was added and removed. |

Each line keeps the IP and, in its properties, the browser's user agent.

## Elsewhere too

- **A log file:** set `SUNRICE_SECURITY_LOG_CHANNEL` (`security.log_channel` in `config/sunrice-admin.php`) to a log channel, e.g. `daily`, to also write each event there as a warning, for fail2ban or a log service.
- **Your code:** every event fires `Sunrice\Admin\Events\SecurityEventRecorded` (`action`, `account`, `label`, `properties`, `ip`, `activity`).
- **Your own events:**

```php
use Sunrice\Admin\Security\SecurityLog;

app(SecurityLog::class)->record('two-factor turned off', $user, null, ['note' => 'by an administrator']);
app(SecurityLog::class)->about('api key rotated', 'integration', $integration, 'Payment gateway');
```
