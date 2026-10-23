# Activity log

**Administration → Activity log** shows who changed what in the panel, and when, newest first. Filter it by source, by who (`user`; or click a person), by kind of thing, by action (`deleted`, `logged in` …), by dates, by IP address (`ip`: a whole address matches exactly, a start such as `192.168.` or `2001:db8:` by prefix; clicking an address in the **IP address** column filters by it; a search that looks like one matches addresses the same way) or by searching, and choose how many lines a page shows (25, 50, 100 or 200; kept in the address as `per_page`); `?type=…&subject=…` in the address shows one item's history. A security event about the person's own account reads "Rina logged in", not "Rina logged in Rina"; the dashboard's Recent activity uses the same sentences (`Activity::sentence()`) and leaves security events out (they're in the log, and alerts are emailed). It needs the `sunrice.activity.view` permission.

## What the panel records

- **Resources** (including Users and Roles): created, updated, deleted, and their actions (`deactivate`, `publish` …), one line per record for bulk actions. "Updated" lines list the fields that changed: a user's `roles`, a new `password`. Saving without changes writes nothing.
- **Settings**: a section's changed settings, with what each was and became ("Close after days: 30 → 365", `properties.values`), except secrets (`#[Encrypted]` properties and `Secret` fields), which are only named.
- **Media**: uploaded, replaced, updated (title, alt text, caption), moved, trashed, restored, deleted; folders created, renamed and deleted.

For resources, values are never stored, only which fields changed, so the log holds no personal data or secrets. The page shows the names readably (`closeAfterDays` as "Close after days"). Each line keeps the user's name as it was, so it reads well after the user is deleted, and the IP address (`ip_address`, indexed for the filter, security events' too). The page's **Columns** menu hides Who, What, Details or IP address, remembered per user (`table.activity`, see [preferences](preferences.md)). There is no export of the log.

**Source** says where a change came from: **Admin** (someone signed in), **AI agent** (with the name of its access token), **System** (the console or the queue) or **Security** ([security events](security.md)). Changes made by nobody signed in, outside the console (a visitor), aren't recorded. Within one request, several "updated" lines for the same thing become one.

## Recording your own

Plugins and apps record their own changes:

```php
use Sunrice\Admin\Activity\ActivityLog;

$log = app(ActivityLog::class);
$log->record('refunded', 'order', $order, "Order #{$order->number}", ['note' => 'Damaged on arrival']);
$log->updated('order', $order, "Order #{$order->number}", ['status', 'tracking_number']);   // nothing when the list is empty

$log->withoutLogging(fn () => $importer->run());                       // imports, seeding
$log->withSource('ai', 'Claude Desktop', fn () => $tool->handle());    // an AI agent's change
```

The subject type is a short name (`order`); use a resource's key (`products`) to get a link to its edit page in the log. `properties` may hold `changed` (a list of field names) and `note`; don't put values or secrets in them.

## Keeping it small

Lines older than `activity.keep_days` (365 by default, `null` for forever) in `config/sunrice-admin.php` are deleted daily by `sunrice:activity-prune`, scheduled by the panel (your app needs Laravel's scheduler running). `activity.enabled => false` stops recording.

Users with `sunrice.activity.prune` also get **Prune…** on the activity log page: it asks how many days to keep (the configured `keep_days` is suggested) and deletes older lines permanently, as the command does. Pruning from the panel is a `log pruned` [security event](security.md) and sends an alert, since deleting the log can cover someone's tracks; the daily command records nothing.
