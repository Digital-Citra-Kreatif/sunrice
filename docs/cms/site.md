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

1. the entry's own **template** (its Page settings), like `cms.landing`;
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

## SEO

Each entry's **Search and sharing** card holds an SEO title, a description, a share image, *Hide from search engines* and, for a page that copies one elsewhere, a canonical address, per language, with the draft and its revisions (stored under the reserved `seo` key). Put the tags in your layout's `<head>`:

```blade
<head>
    <x-sunrice-cms::seo />
</head>
```

On the CMS's pages it reads the page shown (entry, term or listing page; or name it: `:entry="$entry" :preview="$preview"`, `:term="$term"`), and on your app's own pages it gives the site's tags with `title="Contact"`. It prints the `<title>` (the SEO title, else the page's, then the site name: "About | Acme"), the description, `robots` noindex (always in previews), the canonical URL, `hreflang` links to the page's other languages with `x-default` (the main language), Open Graph tags (`og:type` *article* for dated collections, else *website*; `og:site_name`, `og:locale`) and an X (Twitter) card. `Sunrice\Cms\Support\Seo::for($entry)` (and `forTerm()`, `forListing()`, `forPage()`) gives the same as an array if you'd rather write the tags yourself.

**Settings → Site** (`cms.settings.site`) holds what pages don't set: the site name (else `app.name`), a description, a share image, the title separator and whether titles get the site name, and the site's X account (`twitter:site`). The homepage's title is the site name. `Cms::site()` gives these settings to templates (`->name()`, `->title('About')`).

*Hide the whole site from search engines* (or `SUNRICE_NOINDEX=true`, config `sunrice-cms.seo.noindex`, for a staging copy) marks every page noindex, also in an `X-Robots-Tag` header, and empties the sitemap.

`/sitemap.xml` lists every live entry of a collection with a URL, with its other languages, term and listing pages, but those hidden from search engines (at most 50,000).

## Site code

**Settings → Site code** (`cms.settings.code`) takes HTML for every page, as is: analytics, chat widgets, verification tags. Your layout says where:

```blade
<head>
    …
    <x-sunrice-cms::code place="head" />
</head>
<body>
    <x-sunrice-cms::code place="body-start" />
    …
    <x-sunrice-cms::code place="body-end" />
</body>
```

Code there runs in every visitor's browser, so give the permission only to people you trust with the whole site. Each change is a `site code changed` security event and emails the [security alerts](../admin/security.md#email-alerts).

## Layout helpers

```blade
<body class="{{ Cms::bodyClass('dark') }}">
@foreach (Cms::localeUrls() as $locale => $url)
    <a href="{{ $url }}" hreflang="{{ $locale }}">{{ strtoupper($locale) }}</a>
@endforeach
```

- `Cms::bodyClass($extra)`: what the page is, for CSS and scripts. Entries: `page-entry collection-{handle} entry-{id} entry-{slug}`, with `home`, `has-parent parent-{id}` and `is-preview` when they apply; listing pages: `page-listing collection-{handle}`; term pages: `page-term taxonomy-{handle} term-{id} term-{slug}`; pages past the first: `paged paged-{n}`; always `template-{view} lang-{locale}`, and `logged-in` for people signed in to the panel. On your app's own pages: `lang-{locale}` and your extras.
- `Cms::localeUrls()`: the page shown in each language it's live in, in the order of `sunrice-cms.locales`, for a language switcher (none on your app's own pages).
- `Cms::page()`: the page shown (`Sunrice\Cms\Site\Page`: `type`, `locale`, `view`, `entry`, `collection`, `term`, `preview`, `home`, `pageNumber`), or null.

## Redirects

When a published entry's address changes (a new slug), publishing it adds a permanent redirect from the old address, so links and search results keep working. Chains are flattened: every old address points straight at the newest. Structure → Redirects (`cms.redirects.*`) lists them and takes your own, from a path on the site to a path or a web address, 301 or 302. A live page always wins over a redirect from its address.

## Listing pages

A collection with a **listing page URL** (`listing_route`, like `/blog`) gets a page listing its live entries, `per_page` at a time (`?page=2`), newest first for dated collections, in Manual order for ordered ones, else by title; other languages under their prefix (`/id/blog`). Its own fields come from the collection's listing blueprint and are edited from the entries list (**Listing page**, `cms.entries.update`; `SaveListing` in code), per language. It renders with the first view that exists: `cms.{handle}-listing`, `cms.listing`, or the CMS's plain one, with:

| | |
| --- | --- |
| `$collection` | the `Collection` (`->listingUrl($locale)`) |
| `$entries` | a paginator of `EntryTranslation`s (`{{ $entries->links() }}`) |
| `$values`, `$richText` | the listing page's fields, as for entries |
| `$title`, `$locale`, `$url` | the collection's title, the language and the page's URL |

Listing URLs are fixed, so they're matched before any `{slug}` pattern; they're in the sitemap.

## The homepage

Pick the entry shown at `/` in **Settings → Site** (`cms.settings.site`). In other languages, `/{locale}` shows its translation there. The entry's own address redirects (301) to `/`, and `$translation->url()` gives `/`. Your app's own `/` route, if it has one, wins.

## Querying entries

In any view, `Cms::entries()` finds live entries in the current language (`Sunrice\Cms\Content`, aliased as `Cms` unless your app uses that name):

```blade
@foreach (Cms::entries('blog')->latest()->limit(3)->get() as $post)
    <a href="{{ $post->url() }}">{{ $post->title }}</a>
@endforeach

{{ Cms::entries('blog')->whereTerm($news)->paginate(10)->links() }}
{{ Cms::global('contact')['phone'] ?? '' }}
```

`entries(...$collections)` (none: every collection), then `whereTerm(...$terms)`, `whereSlug()`, `childrenOf($entry)` (`null`: top-level entries), `where()`, `whereEntry()`, `except()`, `latest()`, `oldest()`, `alphabetical()`, `ordered()` (Manual order), `orderBy()`, `limit()`, and `get()`, `first()`, `count()` or `paginate()`.

```php
Cms::entries('events')->where('featured', true)->where('date', '>=', today()->toDateString())->orderBy('date')->get();
Cms::entries('blog')->where('city', '!=', 'Jakarta')->except($entry)->limit(3)->get();   // "more like this"
Cms::entries('blog')->whereEntry('related', $entry)->get();                             // pointing at $entry
```

- `where($field, [$operator,] $value)`: a field, or `title`, `slug`, `published_at`, `parent_id`; operators `=`, `!=`, `<`, `>`, `<=`, `>=`; `null` for "no value". Fields are compared as text by `<` and `>`: right for dates and times, not numbers.
- `whereEntry($field, $entries)`: entries whose entries field links to an entry (or any of a list).
- `orderBy($field, 'asc'|'desc')`: a column or a field.
- `except(...$entries)`: leave these out.

Field filters read the main language's published values (shared fields, like entries and terms, live there).

### Components

For a part of a template without PHP, `<x-sunrice-cms::entries>` and `<x-sunrice-cms::terms>` run the query and leave the markup to you, as `$component->entries` and `$component->terms`:

```blade
<x-sunrice-cms::entries collection="blog" :limit="3" :term="$news" :except="$entry" order="-date">
    @foreach ($component->entries as $post)
        <a href="{{ $post->url() }}">{{ $post->title }}</a>
    @endforeach
</x-sunrice-cms::entries>

<x-sunrice-cms::terms taxonomy="categories" collection="blog" hide-empty tree>
    @foreach ($component->terms as $term)
        <a href="{{ $term->url() }}">{{ $term->title() }}</a> ({{ $term->entries_count }})
        {{-- $term->children: the terms under it --}}
    @endforeach
</x-sunrice-cms::terms>
```

`entries`: `collection` (handles, comma-separated; none for all), `limit`, `paginate` (per page: a paginator, `$component->entries->links()`), `order` (`latest`, `oldest`, `alphabetical`, `ordered`, or a field, `-field` for descending), `term` (one or a list), `parent` (an entry, or `top`), `where` (`['featured' => true]`) and `except`.

`terms`: `taxonomy`, in the editors' order, each with `entries_count` (its live entries in the current language, of `collection` when given); `hide-empty`; `tree` (the top-level terms, each with `children`); `:entry` (only that entry's terms).

In hierarchical collections, `$entry->entry->parent`, `->children` and `->ancestors()` (top-down) give breadcrumbs and sub-menus:

```blade
@foreach ($entry->entry->ancestors() as $ancestor)
    <a href="{{ $ancestor->mainTranslation()?->url() }}">{{ $ancestor->mainTranslation()?->title }}</a> ›
@endforeach
@foreach (Cms::entries('pages')->childrenOf($entry->entry)->ordered()->get() as $child)
    <a href="{{ $child->url() }}">{{ $child->title }}</a>
@endforeach
```

## Previews

The entry editor's **Preview** opens the saved draft in its template through a signed link that works for 30 minutes, for anyone who has it. Previews are marked `noindex`. Save first to see changes.
