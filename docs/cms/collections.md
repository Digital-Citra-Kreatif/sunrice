# Collections and entries

A **collection** is a kind of content: pages, articles, products. Its entries are filled in with the fields of its [blueprint](fields.md). An **entry** has one **translation** per language, each with its own title, slug, fields, draft and publish state.

## Collections

Manage them in the admin under **Structure → Collections** (permissions `cms.collections.view`, `.create`, `.update`, `.delete`) or with the actions below. A collection has:

| | |
| --- | --- |
| `handle` | Lowercase letters, digits and underscores, starting with a letter. Set when created; it can't change, and a deleted collection's handle stays taken. |
| `title` | The name editors see. |
| `blueprint` | A blueprint's handle, or none. Changing it keeps entries' data: values of fields the new blueprint lacks stay stored, out of sight, and come back with the field. A blueprint can't be deleted while a collection uses it. |
| `dated` | Entries show a date and list newest first. |

```php
use Sunrice\Cms\Actions\Collections\SaveCollection;

$articles = app(SaveCollection::class)->handle(['handle' => 'articles', 'title' => 'Articles', 'blueprint' => 'article', 'dated' => true]);
```

Deleting a collection (`DeleteCollection`) doesn't erase its entries: they leave the admin and the site.

## Languages

The languages content is written in are set in `config/sunrice-cms.php` (or `SUNRICE_LOCALES=id,en`), the first being the main one. Without either, it's the app's locale. `Sunrice\Cms\Support\Locales::all()` and `::default()` read them.

## Drafts and publishing

Editors work on the **draft**; the site shows the **live** version, which only changes on publish.

```php
use Sunrice\Cms\Actions\Entries\{CreateEntry, SaveDraft, PublishEntry, UnpublishEntry};

$translation = app(CreateEntry::class)->handle($articles, ['title' => 'Hello world', 'data' => ['intro' => 'Hi']]);
app(SaveDraft::class)->handle($translation, ['data' => ['intro' => 'Hello'], 'version' => $translation->version]);
app(PublishEntry::class)->handle($translation);                      // live now
app(PublishEntry::class)->handle($translation, now()->addWeek());   // live from next week
app(UnpublishEntry::class)->handle($translation);
```

- `CreateEntry` takes `title`, `slug`, `data` and `locale` (the main language when left out), makes a draft and records the signed-in user as the author. Fields left out get their defaults.
- `SaveDraft` takes any of `title`, `slug`, `data` and `version`; fields left out of `data` keep their values. Pass the `version` the editor opened: if someone saved since, the save is refused instead of overwriting their work.
- Both validate the title, the slug and the blueprint's fields, and ignore handles the blueprint doesn't have.
- `PublishEntry` checks the draft again (the blueprint may have changed), copies it to the live version and keeps a revision. The entry is live from its date: now when first published, the original date when published again, or the date you pass, which may be in the future.
- `UnpublishEntry` takes it off the site; publishing again keeps its date.

| On the translation | |
| --- | --- |
| `title`, `slug`, `data` | The live version; null until first published. |
| `draft_title`, `draft_slug`, `draft_data` | The draft. |
| `status` | `draft` or `published`. |
| `published_at` | When it is (or goes) live. |
| `isPublished()`, `isLive()` | Published; published with its date reached. |
| `hasUnpublishedChanges()` | The draft differs from the live version. |
| `values($context)` | The fields as templates get them (see [fields](fields.md#using-fields-in-code)); the draft's for a preview context. |

`EntryTranslation::query()->live()` finds translations that are published, past their date, and whose entry and collection aren't deleted.

## Slugs

A slug is lowercase letters, digits and dashes. Left empty, it's made from the title (`hello-world`, then `hello-world-2`). Slugs are unique per collection and language, counting drafts and trashed entries, so restoring an entry never clashes.

## Revisions

Every publish keeps a `Revision` (title, slug, fields and who published it); the latest 50 per translation are kept (`revisions.keep`). `RestoreRevision` loads one into the draft, to publish when ready. If another entry took its slug since, the draft keeps its current slug.

## Trash

`TrashEntry` takes an entry, in all its languages, off the site and out of lists; `RestoreEntry` brings it back as it was.

Every action is written to the [activity log](../admin/activity.md): created, updated (with the fields that changed), published, unpublished, reverted, trashed and restored.
