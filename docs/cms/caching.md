# Caching, events and scheduled publishing

## Events

Listen to these (`Sunrice\Cms\Events`) to react to content, from the admin, AI tools or code alike:

| Event | When |
| --- | --- |
| `EntryPublished` (`$translation`, `$scheduled`) | A language of an entry was published. One published for a later date fires again with `$scheduled` true when its time comes (see [Scheduled publishing](#scheduled-publishing)). |
| `EntryUnpublished` (`$translation`) | A language was taken off the site. |
| `EntryTrashed`, `EntryRestored` (`$entry`) | An entry went to the trash, or came back. |
| `EntryDeleted` (`$entry`) | An entry was deleted for good. |
| `ContentChanged` (`$what`, like `"menu 3"`) | Anything the site shows changed: an entry going live or off, terms, menus, global sets, collections, taxonomies, blueprints, fieldsets, redirects, forms, media files, Settings → Site and Site code. Saving a draft isn't a change. |

They fire after the database transaction they're part of commits. `ContentChanged` may fire several times for one save (once per record), so keep its listeners cheap, or queue them:

```php
Event::listen(ContentChanged::class, fn () => Http::post('https://api.example.com/purge')); // better: a queued listener
```

## The site's cache

The site keeps what it reads on every page in the cache: which page is at an address, the collections' and taxonomies' URL patterns, listing pages, menus and the sitemap. Everything is kept under a **content version**, and every change to what the site shows starts a new one, so nothing old is read again, on any cache store and without tags. Only plain values are kept, never models.

```php
// config/sunrice-cms.php
'cache' => [
    'enabled' => env('SUNRICE_CACHE', true),
    'store' => env('SUNRICE_CACHE_STORE'),   // null: the app's default store
    'ttl' => 3600,                           // seconds anything is kept at most
    'pages' => env('SUNRICE_PAGE_CACHE', false),
],
```

With several servers, use a shared store (redis, memcached, database). Cache your own things the same way, gone with the next change:

```php
app(\Sunrice\Cms\Support\SiteCache::class)->remember('shop:bestsellers', fn () => $ids);
```

## Whole pages

The whole-page cache uses [spatie/laravel-responsecache](https://github.com/spatie/laravel-responsecache) (PHP 8.4). Install it and turn it on:

```bash
composer require spatie/laravel-responsecache
```

```dotenv
SUNRICE_PAGE_CACHE=true
```

Site pages are then rendered once and kept for visitors who aren't signed in. Pages are kept under the content version, so a change shows at once (no clearing needed), and for `cache.ttl` at most. Forms keep working: each visitor gets their own CSRF token in a kept page, and kept pages carry none of the first visitor's cookies.

Left out: anyone signed in (to the app or the panel), a visitor with something flashed to them (form errors, old input, a thank-you message), search results, pages that aren't a plain 200, and what you skip. Pages that show something per visitor (a cart, "Hi, Ana") must be skipped:

```php
// A service provider's boot()
app(\Sunrice\Cms\Site\PageCache::class)->skipWhen(fn (Request $request, ?Page $page): bool =>
    $request->cookies->has('cart') || $page?->collection?->handle === 'products');
```

The callback is asked twice: before a kept page is served (`$page` is null then: only the request is known), and after a page renders, before it's kept.

The CMS sets responsecache's `cache_profile` (`Sunrice\Cms\Site\PageCacheProfile`) and `serializer` (one that drops cookies) unless your `config/responsecache.php` names its own: then extend `PageCacheProfile`, and keep cookies out of what's stored. Where pages are kept is responsecache's `cache.store` (`RESPONSE_CACHE_DRIVER`, `file` by default). With `APP_DEBUG` on, responses say `X-Cache-Status: HIT` or `MISS`.

## Scheduled publishing

An entry published for a later date is live from that moment: the site only shows what's past its date. Laravel's scheduler runs `sunrice:publish-scheduled` every minute to tell everyone else: it starts a new content version (so kept listings, menus, sitemaps and pages show it) and fires `EntryPublished` with `$scheduled` true for each. Add the scheduler to cron once:

```
* * * * * php artisan schedule:run
```

Without it, kept content shows a scheduled entry within `cache.ttl` at the latest.

## Query budgets

Site pages make a fixed number of queries however much content there is: no queries in loops. With the cache warm, the CMS's own views take at most 6 queries for an entry page, 7 for a listing page, 8 for a term page and none for the sitemap (`tests/Cms/CachingTest.php` checks this). Templates add their own: eager load (`Cms::entries()` loads entries with their collections), and see the queries behind a page with Laravel Debugbar or Telescope on staging.
