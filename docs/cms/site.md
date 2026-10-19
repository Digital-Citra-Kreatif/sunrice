# The site

The CMS serves entries as pages: each collection with a **URL** (`route`) shows its live entries there.

## URLs

Set a collection's URL under Structure → Collections: a path with `{slug}` once, like `/blog/{slug}`, or `/{slug}` for pages at the root. Collections without one have no pages of their own (their entries can still be shown by your templates).

- The main language has no prefix; others start with their code: `/blog/hello`, `/id/blog/halo`.
- The more specific pattern is tried first (more fixed characters): `/blog/{slug}` before `/{slug}`. The first pattern that matches decides: `/blog/about` looks for a blog entry "about", and is a 404 if there's none, even when a page "about" exists.
- Two collections can't have the same URL.
- Only live entries have pages: published, past their date, not in the trash, of a collection not deleted. Anything else is a 404.
- The CMS's route is the app's **fallback**: your own routes (`routes/web.php`) always come first. Turn the site off with `sunrice-cms.site.enabled` (the admin and previews keep working); its middleware is `sunrice-cms.site.middleware` (`web`).

`$translation->url()` gives an entry's address (null without a route).

## Templates

An entry renders with the first view that exists:

1. the collection's **template** (Structure → Collections), like `cms.article`;
2. `cms.{handle}`: `resources/views/cms/blog.blade.php`;
3. `cms.entry`: `resources/views/cms/entry.blade.php`;
4. the CMS's plain page, which shows the title and text fields.

Views get:

| | |
| --- | --- |
| `$entry` | the `EntryTranslation` (`$entry->title`, `->slug`, `->published_at`, `->url()`, `->entry->collection`) |
| `$title` | the title (the draft's in a preview) |
| `$values` | the fields as templates get them (see [fields](fields.md#field-types)) |
| `$collection` | the `Collection` |
| `$locale` | the language; the app's locale is set to it too |
| `$preview` | whether this is a preview of the draft |

Rich text is cleaned when it's saved, so print it as is: `{!! $values['body'] !!}`. Print everything else escaped: `{{ $values['intro'] }}`.

## Querying entries

In any view, `Cms::entries()` finds live entries in the current language (`Sunrice\Cms\Content`, aliased as `Cms` unless your app uses that name):

```blade
@foreach (Cms::entries('blog')->latest()->limit(3)->get() as $post)
    <a href="{{ $post->url() }}">{{ $post->title }}</a>
@endforeach

{{ Cms::entries('blog')->whereTerm($news)->paginate(10)->links() }}
{{ Cms::global('contact')['phone'] ?? '' }}
```

`entries(...$collections)` (none: every collection), then `whereTerm(...$terms)`, `whereSlug()`, `latest()`, `oldest()`, `alphabetical()`, `limit()`, and `get()`, `first()`, `count()` or `paginate()`.

## Previews

The entry editor's **Preview** opens the saved draft in its template through a signed link that works for 30 minutes, for anyone who has it. Previews are marked `noindex`. Save first to see changes.
