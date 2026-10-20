# The site

The CMS serves entries as pages: each collection with a **URL** (`route`) shows its live entries there.

## URLs

Set a collection's URL under Structure → Collections: a path with `{slug}` once, like `/blog/{slug}`, or `/{slug}` for pages at the root (and [related entries' slugs](#related-entries-in-the-url) if you like). Collections without one have no pages of their own (their entries can still be shown by your templates).

- The main language has no prefix; others start with their code: `/blog/hello`, `/id/blog/halo`. The app's locale is set from the address as soon as the CMS's route takes over, so 404 pages, redirects and the search page are in that language too. The main language's own prefix (`/en/about`, from before the main language changed) redirects (301) to the plain address, query string kept; your app's own routes aren't touched.
- The more specific pattern is tried first (more fixed characters): `/blog/{slug}` before `/{slug}`. The first pattern that matches decides: `/blog/about` looks for a blog entry "about", and is a 404 if there's none, even when a page "about" exists.
- Two collections can't have the same URL.
- Only live entries have pages: published, past their date, not in the trash, of a collection not deleted. Anything else is a 404.
- The CMS's route is the app's **fallback**: your own routes (`routes/web.php`) always come first. Turn the site off with `sunrice-cms.site.enabled` (the admin and previews keep working); its middleware is `sunrice-cms.site.middleware` (`web`).

`$translation->url()` gives an entry's address (null without a route).

### Untranslated pages

An entry that isn't live in a language other than the main one (no translation there, or one still a draft or scheduled) shows its main language's content at that language's address, with the main language's slug: `/id/blog/hello`. The same goes for term pages without a translation and for the homepage (`/id`). On such a page:

- the app's locale and `$locale` are the address's language, so the layout's own words are in it; templates get `$fallback = true` (and `Cms::page()->fallback`), to say "Not translated yet" if you like;
- `<x-sunrice-cms::seo />` gives the main language's page as the canonical URL and no `hreflang` links, and the main page's `hreflang` links leave the language out; the sitemap lists only the main page;
- menus, entry links and `Cms::localeUrls()` link it in that language, so visitors stay in it; site search in that language doesn't find it (only what's translated).

A translation's own slug works once it's live, and the main language's slug then no longer does in that language. Turn this off with `sunrice-cms.site.fallback` (`SUNRICE_FALLBACK=false`): untranslated pages are then 404s in other languages, and menus and links go to the main language's page.

### Related entries in the URL

A collection's URL may also hold the slug of an entry its entries link to: put the handle of a single-value **Entries** field of its blueprint in braces, `/projects/{project}/{slug}`. The URL is checked when the collection is saved (each `{field}` must be an Entries field without *Several entries*).

- URLs are filled from the linked entry's slug in the same language (else its main language's slug); an entry that links to nothing live gets `-` there: `/projects/-/unit-1`.
- A path matches only with the linked entry's current slug: `/projects/bridge/unit-1` is a 404 when unit 1 belongs to Tower.
- When the linked entry's slug changes, or an entry links to another one, publishing adds redirects from the old URLs (`Site::moved()`), as for a slug of its own.
- Listing pages, the sitemap, menus, links and structured data all use the filled URLs. The slugs are read once per request for every entry a field may link to (and kept in the [site cache](caching.md)), not once per entry.

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
| `$fallback` | whether this is the main language's content in another language ([untranslated pages](#untranslated-pages)) |

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
- `Cms::localeUrls()`: the page shown in each language it's live in (and, with the [main-language fallback](#untranslated-pages), the others), in the order of `sunrice-cms.locales`, for a language switcher (none on your app's own pages).
- `Cms::page()`: the page shown (`Sunrice\Cms\Site\Page`: `type`, `locale`, `view`, `entry`, `collection`, `term`, `preview`, `home`, `pageNumber`, `fallback`), or null.

`@bodyClass` prints the whole attribute: `<body @bodyClass>` or `<body @bodyClass('dark')>` gives `<body class="page-entry … dark">`.

The same as functions, for templates that prefer them (each defined only if the app hasn't defined it first): `cms_entries(...$collections)`, `cms_menu($handle, $locale = null)`, `cms_global($handle, $locale = null)`, `cms_locale_urls()`, `cms_body_class($extra = [])` and `cms_page()`.

### Hooks

In a service provider's `boot()`, change what every page of the CMS does (entries, listing, term and search pages):

```php
use Sunrice\Cms\Content as Cms;
use Sunrice\Cms\Site\Page;

// The view: return another one's name, or null to keep $page->view (what the rules above chose, or an earlier hook).
Cms::resolveTemplateUsing(fn (Page $page): ?string => $page->entry?->entry->parent_id !== null ? 'cms.child-page' : null);

// More body classes (Cms::bodyClass(), @bodyClass), after the CMS's own.
Cms::bodyClassUsing(fn (Page $page): array => $page->collection?->dated ? ['has-date'] : []);
```

Hooks run in the order they were registered, on every request; keep them cheap. How long the page cache keeps a page has its own hook, [`PageCache::lifetimeUsing()`](caching.md).
