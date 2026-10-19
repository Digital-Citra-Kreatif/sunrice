# Taxonomies and terms

A **taxonomy** groups entries: categories, tags, topics. Its **terms** ("News", "Sports") have a title and slug in each language, and in a hierarchical taxonomy a parent ("Local" under "News").

## Setting up

Under **Structure → Taxonomies** (permissions `cms.taxonomies.view`, `.create`, `.update`, `.delete`): a title, a handle that can't change later, whether terms nest, and the collections whose entries it groups. Deleting a taxonomy keeps its terms, out of sight.

Under **Content → Terms** (`cms.terms.manage`): add, rename, nest, reorder and delete a taxonomy's terms, in each language. A term not yet translated shows the main language's title. Deleting a term moves the terms under it up a level.

In code: `SaveTerm` (title, slug, locale, parent_id), `DeleteTerm`, `MoveTerm`. Slugs are unique per taxonomy and language; left empty, they're made from the title.

## Giving entries terms

Add a **Terms** field to the blueprint, with its taxonomy, and whether it takes several terms (and at most how many). Editors pick from the taxonomy's terms; only that taxonomy's terms are accepted. Terms fields are shared by all languages.

Templates get `Term` models (`$term->title()`, `$term->title('id')`, `$term->translation()->slug`), or a collection of them with several, in the order chosen; deleted terms are left out.

When an entry is published, the terms of its top-level terms fields are kept in `sunrice_entry_term`, so queries can find "entries with this term" without reading JSON:

```php
$term->entries;                 // entries that have it
$entry->terms;                  // the terms of an entry
```
