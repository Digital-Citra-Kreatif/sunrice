# Collections and entries

A **collection** is a kind of content: pages, articles, products. Its entries are filled in with the fields of its [blueprint](fields.md). An **entry** has one **translation** per language, each with its own title, slug, fields, draft and publish state.

## Collections

Manage them in the admin under **Structure → Collections** (permissions `cms.collections.view`, `.create`, `.update`, `.delete`) or with the actions below. A collection has:

| | |
| --- | --- |
| `handle` | Lowercase letters, digits and underscores, starting with a letter. Set when created; it can't change, and a deleted collection's handle stays taken. |
| `title` | The name editors see. |
| `blueprint` | A blueprint's handle, or none. Changing it keeps entries' data: values of fields the new blueprint lacks stay stored, out of sight, and come back with the field. A blueprint can't be deleted while a collection uses it. |
| `blueprints` | More blueprints its entries may use instead of its own (**Other blueprints**); see [per-entry blueprints](#per-entry-blueprints). |
| `dated` | Entries show a date and list newest first. |
| `route` | The entries' URL, like `/blog/{slug}`; none: no pages. See [the site](site.md). |
| `template` | The Blade view entries render with; none: `cms.{handle}`, then `cms.entry`. |
| `listing_route` | The URL of a page listing the entries, like `/blog`; none: no listing page. See [the site](site.md#listing-pages). |
| `listing_blueprint`, `per_page` | The listing page's own fields (an intro…), edited from the entries list, and how many entries it shows per page (10). `SaveListing` saves the fields per language and, given the `version` the editor opened (`CollectionListing::$version`), refuses a save over someone else's newer one. |
| `ordered` | **Manual order**: editors put entries in order with Move up and Move down in the list, instead of by date or title. |
| `hierarchical` | Entries may have a **parent** entry, like pages under pages, for breadcrumbs and sub-menus. URLs stay flat: *Team* under *About* is still `/team`. |

```php
use Sunrice\Cms\Actions\Collections\SaveCollection;

$articles = app(SaveCollection::class)->handle(['handle' => 'articles', 'title' => 'Articles', 'blueprint' => 'article', 'dated' => true]);
```

Deleting a collection (`DeleteCollection`) doesn't erase its entries: they leave the admin and the site.

### Per-entry blueprints

A collection may offer more than one blueprint: its own, plus those ticked under **Other blueprints** (`Collection::$blueprints`, a list of handles). An entry then picks one in its **Page settings** (the **Blueprint** select, shown only when there's a choice), for every language at once:

```php
use Sunrice\Cms\Actions\Entries\ChangeBlueprint;

app(ChangeBlueprint::class)->handle($entry, 'landing');   // null (or the collection's own) for the collection's
```

- `Entry::$blueprint` holds the choice (null: the collection's). A handle the collection no longer offers is ignored: the entry has the collection's fields again. `Entry::blueprintHandle()` says which one applies.
- **No value is moved or dropped.** Fields both blueprints have keep their values; the others stay stored, out of sight, and come back if the entry switches back.
- Every translation's version counts up, so an editor opened on the old fields can't save over the change (the editor reloads after the switch). The change is logged.
- Choosing needs the right to edit the entry itself (`cms.entries.update`, or `update-own` for one's own), not only `translate`.
- **`Entry::schema()`** is the entry's fields. Use it, not `$entry->collection->schema()`, for anything about one entry: the editor, validation, `values()`, shared fields, search text, references, media usage, term syncing and duplicates all do. `Collection::schemaFor($handle)` and `Collection::allSchemas()` (every blueprint it offers: field queries such as `whereField()` and sorting look through all of them) load each blueprint once. The CSV export has a column per field of every blueprint offered; each entry fills its own.

## Editing entries

Editors write entries under **Content → Entries**: a list per collection (search, the trash) and the editor, with the blueprint's fields, the entry's status and its revisions. What each person may do follows their permissions:

| Permission | |
| --- | --- |
| `cms.entries.view` | See the lists and open entries. |
| `cms.entries.create` | Create and duplicate entries. |
| `cms.entries.update` | Save drafts and restore revisions. |
| `cms.entries.publish` | Publish (now or on a date) and unpublish, also several at once. |
| `cms.entries.delete` | Move entries to the trash, restore them and delete them for good, also several at once. |
| `cms.entries.export` | Download a collection's entries as CSV (with `view`). |
| `cms.entries.update-own`, `.delete-own` | The same, for entries they created, in collections they may view. |
| `cms.entries.translate` | Save drafts of entries' other languages (not the main one), in collections they may view. |

Each of the first six also comes for one collection: `cms.entries.blog.publish` publishes only in Blog. They're created with the collection (and deleted with it), and the role editor shows them under "Entries: Blog". The same goes for terms (`cms.terms.{taxonomy}.manage`) and form submissions (`cms.submissions.{form}.view`, `.export`, `.delete`). People see only the collections, taxonomies and forms they may work in. In code: `Sunrice\Cms\Support\Access::entries($user, 'publish', $collection, $entry, $locale)`.

`sunrice:roles` creates `editor` (all content), `author` (writes entries and edits and trashes their own, publishes nothing) and `translator` (writes the other languages): see [default roles](../admin/permissions.md#default-roles).

In the list, tick entries to publish, unpublish or trash them together (in the trash: restore or delete for good); entries one may not act on, or that can't be published as they are, are skipped and counted. **Export CSV** (`cms.entries.export`) downloads the collection's entries in the main language, a column per field (an `exported` security event). In the editor:

- **Ctrl/Cmd+S** saves the draft, and leaving the page with unsaved changes asks first.
- **Page settings**: the parent (in hierarchical collections; never the entry itself or one below it, at most 10 deep), a Blade view for this entry alone (`SaveEntrySettings`) and, when the collection offers more than one, its blueprint (`ChangeBlueprint`), for every language. Deleting an entry for good moves its children up to its parent.
- **Duplicate** copies every language's draft as a new draft entry ("Hello (copy)", slug `hello-copy`).
- Only one person edits an entry in a language at a time: others are told who's editing and can view it or take over (see [edit locks](../admin/locks.md)).
- When someone else saved since you opened the page, saving says so: **Load theirs**, or **Save mine anyway** over their version.

Media fields use the media library's picker, which needs `sunrice.media.view` (and `.upload` to upload from it).

Rich text fields have a formatting toolbar: headings, bold, italic, underline, strikethrough, code, lists, quotes, dividers, links (web addresses, paths on the site, mailto: and tel:; others are refused) and images from the media library, which keep their library id. The server cleans the HTML again when saving.

## Languages

The languages content is written in are set in `config/sunrice-cms.php` (or `SUNRICE_LOCALES=id,en`), the first being the main one. Without either, it's the app's locale. `Sunrice\Cms\Support\Locales::all()` and `::default()` read them.

Each language of an entry is a translation of its own: its own title, slug, draft, status, publish date and revisions. With more than one language, the editor shows a bar of them:

- **Adding a language** (`cms.entries.create`, or `CreateTranslation`) makes a draft copied from the main translation, to translate and publish on its own.
- **Removing one** (`cms.entries.delete`, or `DeleteTranslation`) deletes that translation and its revisions. The main translation can't be removed: trash the entry instead.

Fields are **translated** or **shared**. A field's *Per language* setting in the blueprint decides; left to the type, text fields (text, textarea, rich text, links, groups, repeaters, flexible) are translated and the others (numbers, toggles, selects, dates, media) shared. Shared fields belong to the main translation: other languages show its values, read-only, and templates get them from it (`$translation->values()`). A price changed in the main language is the price in every language. Other languages store only their translated fields (`CreateTranslation` and `SaveDraft` leave the shared ones out; `$translation->ownData()`), and `$translation->completeData()` gives the stored fields with the main translation's shared ones, live or in the draft, which site search text and revisions follow. Shared fields nested inside a translated group, repeater or block aren't shared: a translated field is translated whole.

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
- Both validate the title, the slug and the blueprint's fields, and ignore handles the blueprint doesn't have. The slug check and the write run in one transaction (`EntryInput::write()`), locking the translations the check looks at where the database can; a slug another save took in between is refused with a validation error on `slug`, never a database error.
- `PublishEntry` checks the draft again (the blueprint may have changed), copies it to the live version, counts the `version` up (an editor opened before the publish can't save over it unknowingly) and keeps a revision. The entry is live from its date: now when first published, the original date when published again, or the date you pass, which may be in the future.
- `UnpublishEntry` takes it off the site; publishing again keeps its date.
- Each fires an event (`EntryPublished`, `EntryUnpublished`, `EntryTrashed`, `EntryRestored`, `EntryDeleted`); one published for a later date is announced again when its time comes. See [Caching, events and scheduled publishing](caching.md).

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

Every publish keeps a `Revision` (title, slug, fields and who published it); the latest 50 per translation are kept (`revisions.keep`). `RestoreRevision` loads one into the draft, to publish when ready. If another entry took its slug since, the draft keeps its current slug. In the editor, **Undo the restore** puts the replaced draft back, until the session ends (`PutBackDraft`).

## Trash

`TrashEntry` takes an entry, in all its languages, off the site and out of lists; `RestoreEntry` brings it back as it was. `ForceDeleteEntry` deletes a trashed entry for good, with its languages, revisions and terms. `DuplicateEntry` and `BulkEntries` are the list's and editor's other actions.

Every action is written to the [activity log](../admin/activity.md): created, duplicated, updated (with the fields that changed), published, unpublished, reverted, trashed, restored and deleted.
