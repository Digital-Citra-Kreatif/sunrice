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

## Editing entries

Editors write entries under **Content → Entries**: a list per collection (see [the entries list](#the-entries-list)) and the editor, with the blueprint's fields, the entry's status and its revisions. What each person may do follows their permissions:

| Permission | |
| --- | --- |
| `cms.entries.view` | See the lists and open entries. |
| `cms.entries.create` | Create and duplicate entries. |
| `cms.entries.update` | Save drafts and restore revisions. |
| `cms.entries.publish` | Publish (now or on a date) and unpublish, also several at once. |
| `cms.entries.delete` | Move entries to the trash, restore them and delete them for good, also several at once. |
| `cms.entries.update-own`, `.delete-own` | The same, for entries they created, in collections they may view. |
| `cms.entries.translate` | Save drafts of entries' other languages (not the main one), in collections they may view. |

Each of the first five also comes for one collection: `cms.entries.blog.publish` publishes only in Blog. They're created with the collection (and deleted with it), and the role editor shows them under "Entries: Blog". The same goes for terms (`cms.terms.{taxonomy}.manage`) and form submissions (`cms.submissions.{form}.view`, `.delete`). People see only the collections, taxonomies and forms they may work in. In code: `Sunrice\Cms\Support\Access::entries($user, 'publish', $collection, $entry, $locale)`.

`sunrice:roles` creates `editor` (all content), `author` (writes entries and edits and trashes their own, publishes nothing) and `translator` (writes the other languages): see [default roles](../admin/permissions.md#default-roles).

In the editor:

- **Ctrl/Cmd+S** saves the draft, and leaving the page with unsaved changes asks first.
- **Page settings**: the parent (in hierarchical collections; never the entry itself or one below it, at most 10 deep) and a Blade view for this entry alone, for every language (`SaveEntrySettings`). Deleting an entry for good moves its children up to its parent.
- **Duplicate** copies every language's draft as a new draft entry ("Hello (copy)", slug `hello-copy`).
- Only one person edits an entry in a language at a time: others are told who's editing and can view it or take over (see [edit locks](../admin/locks.md)).
- When someone else saved since you opened the page, saving says so: **Load theirs**, or **Save mine anyway** over their version.

Media fields use the media library's picker, which needs `sunrice.media.view` (and `.upload` to upload from it).

Rich text fields have a formatting toolbar: headings, bold, italic, underline, strikethrough, code, lists, quotes, dividers, links (web addresses, paths on the site, mailto: and tel:; others are refused) and images from the media library, which keep their library id. The server cleans the HTML again when saving.

## The entries list

Each collection's list (`EntryController::index`, built by `Sunrice\Cms\Admin\EntryList`) shows the entries of one language, 25 to a page, and keeps everything it shows in the address, so a list can be bookmarked or shared:

| Parameter | |
| --- | --- |
| `search` | Titles and slugs containing the text (any case); `#12` finds the entry with id 12. |
| `status` | `draft`, `published`, `scheduled` (published with a date to come) or `trashed` (the trash; `trashed=1` works too). None: everything but the trash. |
| `author` | Entries a user created (the filter offers everyone who wrote in the collection). |
| `terms[topics]` | Entries with that term (id) of a taxonomy attached to the collection, one per taxonomy. |
| `locale` | The language listed (its titles, slugs and statuses); the main one by default. Entries without that language aren't listed. |
| `sort` | `title`, `slug`, `status`, `published_at`, `updated_at`, `created_at`, `author` or `field.{handle}`, with `-` first for descending. None: the collection's order (manual, newest first when dated, else by title). |
| `per_page` | 10, 25, 50 or 100. |

Besides the title, the **Columns** menu shows the slug, the status (with a badge per other language), the author, the dates, and **field columns**: the blueprint's top-level text, textarea, number, date, select, toggle and entries fields (custom fields built on them too), shown as short text (select labels, Yes/No, linked entries' titles). Field columns sort by the draft's value (shared fields: the main language's), numbers and dates as such; entries fields and multiple selects don't sort. The chosen columns and page size are kept per person ([preferences](../admin/preferences.md), key `table.cms.entries.{collection}`); a `per_page` in the address wins.

**Go to** links to what an editor tends to need next, where they may go: the listing page (edit and view), the collection's taxonomies' terms (`cms.terms.*`), the collection's settings (`cms.collections.update`) and its blueprint (`cms.blueprints.manage`). In a collection with manual order, Move up and Move down appear while the list is in that order, unfiltered.

Tick entries to publish, unpublish or trash them together (in the trash: restore or delete for good); entries one may not act on, or that can't be published as they are, are skipped and counted. **Export CSV** downloads the list as it is filtered, searched, sorted and in its language (every page): the ID, title, slug, status, publish date, URL, last change and author, and a column per top-level field (field columns as the list shows them); it's an `exported` security event.

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

## The dashboard

The CMS adds these widgets to the panel's [dashboard](../admin/panel.md#dashboard-widgets), each showing only what the user may see (per collection and per form, as `Support\Access` decides):

| Widget | Shows | Who sees it |
| --- | --- | --- |
| `cms.collections` (Content) | Each collection's entries in the main language: live, drafts and scheduled, linking to its list. | Users who may view some collection's entries. |
| `cms.recent-edits` (Recently edited) | The last eight entry translations edited, any language, with their status. | The same. |
| `cms.comments` (Comments waiting) | How many comments wait for moderation, and the latest five. | `cms.comments.view` |
| `cms.scheduled` (Coming up) | The next eight entries scheduled to go live. | Users who may view some collection's entries. |
| `cms.submissions` (Latest submissions) | The last eight submissions of the forms the user may read, each summed up by its first text field. | `cms.submissions.view`, or a form's own. |

Each reads its data in one query (the comments widget a second one for signed-in commenters' names), after the page has painted. Entries in the trash and deleted collections are left out.
