# The site

The CMS serves entries as pages: each collection with a **URL** (`route`) shows its live entries there.

## URLs

Set a collection's URL under Structure → Collections: a path with `{slug}` once, like `/blog/{slug}`, or `/{slug}` for pages at the root. Collections without one have no pages of their own (their entries can still be shown by your templates).

- The main language has no prefix; others start with their code: `/blog/hello`, `/id/blog/halo`. The app's locale is set from the address as soon as the CMS's route takes over, so 404 pages, redirects and the search page are in that language too. The main language's own prefix (`/en/about`, from before the main language changed) redirects (301) to the plain address, query string kept; your app's own routes aren't touched.
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

An entry's or a term's own template must be under one of the site's template prefixes, `cms.` unless `sunrice-cms.site.template_prefixes` says otherwise (`['cms.', 'pages.']`), so no other view of the app (`layouts.app`, `emails.invoice`) can be made a public page; a stored one outside them is ignored. Only the collection's editors (`cms.entries.update`) pick it: authors with `cms.entries.update-own` change the other page settings and keep the template as it is.

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

Past the first page of a listing, term or search page, the canonical and `hreflang` URLs (and `Cms::localeUrls()`) carry the page number (`/blog?page=2`), so each page is its own, not a copy of the first.

`/sitemap.xml` lists every live entry of a collection with a URL (with `lastmod` and its other languages), term pages (with `lastmod`) and listing pages, but those hidden from search engines. It's built in chunks and kept in the cache until the content changes. Search engines read at most 50,000 addresses from one file, so past that `/sitemap.xml` becomes an index of `/sitemap-1.xml`, `/sitemap-2.xml`, … (route `sunrice.cms.sitemap.part`); `/sitemap-1.xml` always works.

### Structured data

The seo component also prints structured data (schema.org, as JSON-LD), so search engines know what pages are and can show richer results:

- every page: the **WebSite**, with its search box (a SearchAction, when the search page is on), and who publishes it (an **Organization** with its logo, or a **Person**: Settings → Site);
- entries: an **Article** (dated collections) or a **WebPage**, or the collection's **Structured data type** (Structure → Collections: Article, Blog post, News article, Web page, About page, Contact page). Articles get a headline, publish and change dates, the share image, author and publisher;
- **BreadcrumbList**: Home, the collection's listing page, parent entries, the page;
- listing and term pages: a **CollectionPage**; the search page: a **SearchResultsPage**.

A template adds nodes of its own (an event, a product, FAQs) with `:schema`:

```blade
<x-sunrice-cms::seo :schema="[[
    '@type' => 'Event',
    'name' => $entry->title,
    'startDate' => $values['starts_at'],
    'location' => ['@type' => 'Place', 'name' => $values['venue']],
]]" />
```

What's printed can't end the script (`<`, `>`, `&` and quotes are escaped). `Sunrice\Cms\Support\StructuredData::graph($page, $extra)` gives the nodes as an array. Turn it off in Settings → Site.

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

When a published entry's address changes (a new slug), publishing it adds a permanent redirect from the old address, so links and search results keep working. Chains are flattened: every old address points straight at the newest. The query string comes along (`/old?utm_source=mail` → `/new?utm_source=mail`). Structure → Redirects (`cms.redirects.*`) lists them and takes your own, from a path on the site to a path or a web address, 301 or 302. A live page always wins over a redirect from its address.

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

`entries(...$collections)` (none: every collection), then `locale()`, `preview()`, `whereTerm()`, `whereFieldTerm()`, `whereSlug()`, `childrenOf($entry)` (`null`: top-level entries), `where()`, `whereEntry()`, `except()`, `search()`, `latest()`, `oldest()`, `alphabetical()`, `ordered()` (Manual order), `orderBy()`, `limit()`, `remember()`, and `get()`, `first()`, `count()`, `paginate()` or `toBase()`.

```php
Cms::entries('events')->where('featured', true)->where('date', '>=', today())->orderBy('date')->get();
Cms::entries('shop')->where('price', '<', 100)->where('color', 'in', ['red', 'blue'])->orderBy('price', 'desc')->get();
Cms::entries('blog')->where('city', '!=', 'Jakarta')->except($entry)->limit(3)->get();   // "more like this"
Cms::entries('blog')->whereEntry('related', $entry)->get();                             // pointing at $entry
Cms::entries('blog')->whereTerm('topics', 'news', includeChildren: true)->latest()->paginate(10);
Cms::entries('blog')->search($request->query('q', ''))->paginate(10);
```

- `locale($locale)`: another language than the current one. `preview()`: every entry not in the trash, by its draft title, slug and fields (`values()` give the draft's too), as the site's preview does; never cached.
- `where($field, [$operator,] $value)`: a field, or `title`, `slug`, `published_at`, `parent_id`; operators `=`, `!=`, `<`, `>`, `<=`, `>=`, and `in`, `not in` with a list; `null` for "no value" (`where('subtitle', null)`, `where('subtitle', '!=', null)`). Number and date fields compare as such (`9 < 100`, a `Carbon` or `2026-01-01` for dates); the others as text.
- `whereTerm($term, ...)`: entries with a term (or any of several): `Term` models or ids (`whereTerm($news, $tips)`, `whereTerm([$news, $tips])`), or a taxonomy handle and a slug or list of slugs (`whereTerm('topics', 'news')`, `whereTerm('topics', ['news', 'tips'])`); `includeChildren: true` takes the terms under them too. Looks at the terms of every terms field.
- `whereFieldTerm($field, $terms, includeChildren: false)`: the same for one terms field, with slugs looked up in that field's taxonomy.
- `whereEntry($field, $entries)`: entries whose entries field links to an entry (or any of a list).
- `search($text)`: entries whose title or field text holds the words, as the site's search matches them; title matches first, then in the query's order.
- `orderBy($field, 'asc'|'desc')`: a column or a field; numbers and dates sort as such.
- `except(...$entries)`: leave these out.
- `remember()`: keep the matched ids in the site's cache (`SiteCache`, when it's on) until the content changes, so the query runs once; the entries themselves are always read fresh.
- `toBase()`: the Eloquent query (`Builder<EntryTranslation>`, a new one each time) for what the methods here don't do.

Field filters read the main language's published values (shared fields, like entries and terms, live there).

A result's entries share one `HydrationContext`: whatever their fields point at (media, entries, terms) is loaded up front, one query per kind, so `$post->values()` in a loop costs no queries of its own.

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

`entries`: `collection` (handles, comma-separated; none for all), `limit`, `paginate` (per page: a paginator, `$component->entries->links()`), `order` (`latest`, `oldest`, `alphabetical`, `ordered`, or a field, `-field` for descending), `term` (one or a list), `parent` (an entry, or `top`), `where` (`['featured' => true]`), `except`, `search` (words to find) and `remember` (keep the matched ids in the site's cache).

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

## Search

`/search?q=…` (and `/{locale}/search`) searches the live entries that have pages, in that language: their title and field text (the words only: no field handles, HTML tags, addresses or SEO), titles first, then newest first. It renders with `cms.search`, or the CMS's plain page, with `$query`, `$results` (a paginator of `EntryTranslation`s), `$title` and `$locale`. Search pages are never indexed or kept by the page cache, and one visitor may search `per_minute` times a minute, everyone together `per_minute_global` times. Your app's own `/search` route wins; the search page wins over an entry at that address.

Each database searches its own way, with the index of migration `000017`: MySQL and MariaDB by whole words with a FULLTEXT index (natural language mode, so words shorter than `innodb_ft_min_token_size`, three characters, and stopwords aren't found), Postgres by words with a GIN index (the `simple` dictionary: every language, no stemming), and SQLite, for development only, by matching what's typed anywhere (`%` and `_` aren't wildcards). The same search within a minute is answered from the [site cache](caching.md) (ids only), gone with the next change to content.

```php
// config/sunrice-cms.php
'search' => [
    'enabled' => env('SUNRICE_SEARCH', true),
    'path' => 'search',
    'collections' => null,   // handles; null: every collection with pages
    'per_page' => 10,
    'per_minute' => 30,         // searches per visitor
    'per_minute_global' => 300, // and for everyone together
],
```

`<x-sunrice-cms::search>` makes a search box anywhere, or results of your own:

```blade
<x-sunrice-cms::search>
    <form action="{{ $component->action }}" role="search">
        <input type="search" name="{{ $component->name }}" value="{{ $component->query }}">
    </form>
</x-sunrice-cms::search>

<x-sunrice-cms::search results collection="help" :per-page="20">
    @foreach ($component->results as $entry)
        <a href="{{ $entry->url() }}">{{ $entry->title }}</a>
    @endforeach
</x-sunrice-cms::search>
```

In code: `app(\Sunrice\Cms\Site\Search::class)->entries('coffee', ['blog'], perPage: 20, locale: 'id')`. Field text is kept when an entry is published (`search_text`, its first 20,000 characters).

## The site's words

What the CMS's own views say to visitors (the plain pages' **Newer** and **Older**, the search page, comments, the forms' **Send**, the reply emails, and the messages after posting) are translation keys, `sunrice-cms::site.*`, in English and Indonesian, shown in the page's language. To change them or add a language, put your own lines in `lang/vendor/sunrice-cms/{locale}/site.php` (only the keys you change):

```php
// lang/vendor/sunrice-cms/id/site.php
return ['comments' => ['post' => 'Kirim']];
```

Laravel's own lines (`pagination`, `validation`, `auth`, `passwords`, and the pagination view's words) come in English only; the CMS brings Indonesian ones, used for each file the app has none of: your `lang/id/validation.php` always wins, as do the lines in `lang/id.json`. Form and comment errors, and paginator links, are then in Indonesian on Indonesian pages.

## Previews

The entry editor's **Preview** opens the saved draft in its template through a signed link that works for 30 minutes, for anyone who has it. Previews are marked `noindex` and `Cache-Control: no-store, private`, so no cache keeps a draft. Save first to see changes.
