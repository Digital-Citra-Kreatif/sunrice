# Commands

The CMS's artisan commands besides installing (`sunrice:install`, see
`docs/admin/permissions.md`), translating (`docs/cms/translation.md`) and
scheduled publishing (`docs/cms/caching.md`).

## Deleted collections and taxonomies: `sunrice:orphans`

Deleting a collection or a taxonomy hides it, with its entries or terms,
from the admin and the site, and keeps its handle. This lists them:

```bash
php artisan sunrice:orphans                        # what's kept, and since when
php artisan sunrice:orphans --restore --handle=blog
php artisan sunrice:orphans --purge                # delete for good (asks first; --force doesn't)
```

Purging deletes the entries (their languages, revisions, comments and
terms), or the terms, for good; menu links to them stop leading anywhere,
as when an entry is deleted (`Actions\Structure\PurgeDeleted`).

## Renaming a field: `sunrice:rename-field`

```bash
php artisan sunrice:rename-field post intro summary
```

Renames the blueprint's field `intro` to `summary`, and the values stored
under it: entries (live, draft and revisions), listing pages, terms,
globals and form submissions that use the blueprint
(`Actions\Blueprints\RenameField`). Only fields of the blueprint itself
(not inside groups or fieldsets). Change templates that read the old
handle.

## Demo content: `sunrice:demo-content`

```bash
php artisan sunrice:demo-content 5000              # a "demo" collection, at /demo
php artisan sunrice:demo-content --remove
```

Fills a collection of its own with published fake articles in ten demo
topics, to see how the site does with a lot of content, and prints pages
to try. It only ever touches collections and taxonomies it made (the ones
with the `sunrice_demo` blueprints); `--collection=` picks another handle.
