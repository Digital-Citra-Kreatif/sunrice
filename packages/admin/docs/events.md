# Events and hooks

The panel fires Laravel events (`Sunrice\Admin\Events`, all `@api`) for what happens in it, from its actions, so the media screen, AI agents and your own code calling the actions fire them alike. The CMS's content events (entries, terms, menus, globals, forms, comments) and how before and after events work are on the CMS's [Events and hooks](../../cms/docs/events.md) page; the same rules apply here.

- **Before events** (`MediaDeleting`) fire before anything is written. A listener stops the operation by throwing `Illuminate\Validation\ValidationException`: nothing changes, and the editor (or an AI agent) sees its message like any validation error.
- **After events** fire once the database transaction they're part of commits, never for one rolled back.
- Media events carry who did it, through the `Sunrice\Admin\Events\RecordsActor` trait: `$event->user` (the signed-in user on the panel's guard, an AI agent's token user, or null for the console) and `$event->source` (`"admin"`, `"ai"`, `"system"` or `"site"`; see `ActivityLog::currentSource()`). A plugin's own events can use the trait too: call `$this->recordActor()` in the constructor.

## The events

| Event | When | Properties | Can stop |
| --- | --- | --- | --- |
| `MediaUploaded` | A file was added to the media library (`UploadMedia`). Its image sizes may still be being made on the queue. | `media` | |
| `MediaUpdated` | A file's details changed (`UpdateMedia`: title, alt text, caption, their translations; not when nothing changed), or its file was replaced (`ReplaceMedia`, `changed` is `["file"]`). | `media`, `changed` | |
| `MediaDeleting` | Before a file goes to the trash (`TrashMedia`, `permanent` false) or is deleted for good (`DeleteMedia`, `permanent` true), after the panel's own checks. In a bulk action, a refused file stops the action there (the files before it are done). | `media`, `permanent` | Yes, under `media` |
| `MediaDeleted` | A file went to the trash (its files off the public disk), or was deleted for good (the record and files gone; the model still holds its attributes). | `media`, `permanent` | |
| `SettingsSaved` | A settings section was saved and these settings changed (names only: values may be secrets). See [Settings](settings.md#a-settings-page-of-your-own). | `section`, `changed` | |
| `ActivityRecorded` | A line was added to the activity log. See [Activity log](activity.md). | `activity` | |
| `SecurityEventRecorded` | A security event was recorded: a login, a failed or blocked one, a blocked upload, an export, a change of roles… See [Security](security.md#security-events). | `action`, `account`, `label`, `properties`, `ip`, `activity` | |
| `LoginThrottled` | A login limit was reached (once). Laravel's auth events (`Failed`, `Login`, `Logout`, `PasswordReset`) fire as usual too. See [Signing in](auth.md#events). | `email`, `ip`, `scope`, `seconds` | |

Optimising an image and putting its original back are housekeeping and fire no media event. A Media model's own Eloquent events fire too, but for every write (sizes, versions), so listen to these instead.

```php
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Events\MediaDeleting;

Event::listen(function (MediaDeleting $event): void {
    // Files in the "Brand" folder stay.
    if ($event->media->folder_id === config('app.brand_media_folder')) {
        throw ValidationException::withMessages(['media' => __(':file is a brand file: it can\'t be deleted.', ['file' => $event->media->filename])]);
    }
});
```

Slow work (a webhook, a CDN purge, mail) belongs in a queued listener (`implements ShouldQueue`), which needs [the queue worker](mail.md#the-queue-worker).

## Other hooks

Not every extension point is an event. These change how the panel behaves, from your panel provider or a plugin's `register()`/`boot()`:

| Hook | What it changes | Docs |
| --- | --- | --- |
| `$panel->plugin(...)` and the `Plugin` interface | Resources, pages, navigation, permissions and policies, settings sections, dashboard widgets, field types, AI tools, shared page data, activity log subjects, health checks. | [Writing a plugin](panel.md#writing-a-plugin) |
| `$panel->navigationUsing(Closure)` | Navigation items made from data, worked out on each page. | [Navigation](panel.md#navigation) |
| `$panel->permissionsUsing(Closure)` | Permissions made from data. | [Permissions from data](permissions.md#permissions-from-data) |
| `$panel->mailBrandUsing(Closure)` | The logo at the top of emails. | [Mail](mail.md#the-logo-at-the-top-of-emails) |
| Resource form fields' `loadUsing()` / `saveUsing()` | How a field that isn't a column reads and writes. | [Resources](resources.md#forms) |
| Security alert rules | Your own rules over the activity log and security events. | [Your own rules](security.md#your-own-rules) |
| The CMS's hooks | Templates, body classes, entry links, the page cache's lifetime. | [CMS hooks](../../cms/docs/events.md#other-hooks) |
