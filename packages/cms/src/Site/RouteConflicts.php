<?php

declare(strict_types=1);

namespace Sunrice\Cms\Site;

use Illuminate\Database\Eloquent\Builder;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\CollectionTaxonomy;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\TermTranslation;
use Sunrice\Cms\Support\Locales;

/**
 * Whether something else has a URL pattern in a language already: another
 * collection (or taxonomy) of the same kind, its own there, else its main
 * one; for entry and term page patterns also the other kind's, and
 * taxonomies' term pages in collections (/books/tags/{slug}); for listing
 * pages and taxonomies' index pages (fixed addresses) each other, and
 * index pages in collections (/books/tags). Two of them with the same
 * pattern in a language would share its addresses. Fixed addresses are
 * matched before any pattern, so an entry or term may not be at one
 * either: a fixed page refuses an address an entry or term has
 * (addressed()), and an entry or term a slug that would put it at one
 * (fixedSlugs()).
 *
 * @internal
 */
final class RouteConflicts
{
    /** The title of what else has the pattern in the language, or null. */
    public static function find(Collection|Taxonomy $record, string $attribute, string $locale, string $pattern): ?string
    {
        // Deleted ones too: they take their addresses back when restored.
        $query = $record->newQueryWithoutScopes();
        if ($record->exists) {
            $query->whereKeyNot($record->getKey());
        }
        foreach ($query->whereNotNull($attribute)->get() as $other) {
            if ($other->routeIn($locale, $attribute) === $pattern) {
                return $other->title;
            }
        }
        if ($attribute !== 'route') {
            // Listing pages and taxonomies' index pages: fixed addresses, which must differ from each other,
            // and from entries' and terms' addresses (matched first, they would hide them). An address the
            // record has already in the language is left as it is: a clash made before isn't in the way of other changes.
            return self::fixedPages($locale, $pattern, $record instanceof Collection ? 'listing_route' : 'index_route', $record->exists ? $record->id : null)
                ?? ($record->exists && self::stored($record)->routeIn($locale, $attribute) === $pattern ? null : self::addressed($locale, $pattern));
        }
        // Entries' and shared term pages' patterns against each other, and both against collections' term pages.
        $others = $record instanceof Collection ? Taxonomy::query()->withTrashed() : Collection::query()->withTrashed();
        foreach ($others->whereNotNull('route')->get() as $other) {
            if ($other->routeIn($locale) === $pattern) {
                return $other->title;
            }
        }

        return self::termPages($locale, $pattern);
    }

    /**
     * What else has a pattern for a taxonomy's term pages in a collection
     * in a language: entries' and shared term pages' patterns, and other
     * collections' term pages.
     */
    public static function forTermPages(Collection $collection, Taxonomy $taxonomy, string $locale, string $pattern): ?string
    {
        foreach ([Collection::query()->withTrashed(), Taxonomy::query()->withTrashed()] as $query) {
            foreach ($query->whereNotNull('route')->get() as $other) {
                if ($other->routeIn($locale) === $pattern) {
                    return $other->title;
                }
            }
        }

        return self::termPages($locale, $pattern, [$collection->id, $taxonomy->id]);
    }

    /**
     * What else has an address for a taxonomy's index page in a collection
     * in a language: listing pages, shared index pages, other collections'
     * index pages, and entries and terms (addressed()).
     */
    public static function forIndexPage(Collection $collection, Taxonomy $taxonomy, string $locale, string $path): ?string
    {
        return self::fixedPages($locale, $path, except: [$collection->id, $taxonomy->id]) ?? self::addressed($locale, $path);
    }

    /**
     * What clashes with a taxonomy's pages in a collection as attached
     * (`$pivot`, maybe not saved yet): its term pages' pattern and its
     * index page's address, while switched on, as set or by default, in
     * every language. The first clash: which page, the language, the URL
     * and what else has it.
     *
     * @return array{page: 'term'|'index', locale: string, url: string, title: string}|null
     */
    public static function forAttachment(Collection $collection, Taxonomy $taxonomy, CollectionTaxonomy $pivot): ?array
    {
        foreach (Locales::all() as $locale) {
            if ($pivot->term_pages && ($title = self::forTermPages($collection, $taxonomy, $locale, $url = $pivot->pattern($collection, $taxonomy, $locale))) !== null) {
                return ['page' => 'term', 'locale' => $locale, 'url' => $url, 'title' => $title];
            }
            if ($pivot->index_page && ($title = self::forIndexPage($collection, $taxonomy, $locale, $url = $pivot->indexPath($collection, $taxonomy, $locale))) !== null) {
                return ['page' => 'index', 'locale' => $locale, 'url' => $url, 'title' => $title];
            }
        }

        return null;
    }

    /**
     * What has a fixed address in a language: a collection's listing page,
     * a taxonomy's shared index page, or its index page in a collection
     * ("Books · Tags"), but the one left out (an attribute's record by id,
     * or a collection's and a taxonomy's pair).
     *
     * @param  'listing_route'|'index_route'|null  $attribute
     * @param  array{0: int, 1: int}|null  $except
     */
    private static function fixedPages(string $locale, string $path, ?string $attribute = null, ?int $id = null, ?array $except = null): ?string
    {
        foreach (self::fixed($locale) as $page) {
            $left = match ($page['kind']) {
                'listing_route', 'index_route' => $attribute === $page['kind'] && $page['id'] === $id,
                default => $except === $page['id'],
            };
            if (! $left && $page['path'] === $path) {
                return $page['title'];
            }
        }

        return null;
    }

    /**
     * The fixed addresses in a language (without its prefix), deleted
     * collections' and taxonomies' too: listing pages, shared index pages
     * and index pages in collections, in that order.
     *
     * @return list<array{path: string, title: string, kind: 'listing_route'|'index_route', id: int}|array{path: string, title: string, kind: 'collection-index', id: array{0: int, 1: int}}>
     */
    private static function fixed(string $locale): array
    {
        $pages = [];
        foreach (Collection::query()->withTrashed()->whereNotNull('listing_route')->get() as $collection) {
            $path = $collection->routeIn($locale, 'listing_route');
            if ($path !== null) {
                $pages[] = ['path' => $path, 'title' => $collection->title, 'kind' => 'listing_route', 'id' => $collection->id];
            }
        }
        foreach (Taxonomy::query()->withTrashed()->whereNotNull('index_route')->get() as $taxonomy) {
            $path = $taxonomy->routeIn($locale, 'index_route');
            if ($path !== null) {
                $pages[] = ['path' => $path, 'title' => $taxonomy->title, 'kind' => 'index_route', 'id' => $taxonomy->id];
            }
        }
        foreach (Taxonomy::query()->withTrashed()->with(['collections' => fn ($q) => $q->withTrashed()->wherePivot('index_page', true)])->get() as $taxonomy) {
            foreach ($taxonomy->collections as $collection) {
                $pivot = $collection->getRelation('pivot');
                if ($pivot instanceof CollectionTaxonomy) {
                    $pages[] = ['path' => $pivot->indexPath($collection, $taxonomy, $locale), 'title' => $collection->title.' · '.$taxonomy->title, 'kind' => 'collection-index', 'id' => [$collection->id, $taxonomy->id]];
                }
            }
        }

        return $pages;
    }

    /**
     * The slugs a collection's entries (or a taxonomy's terms) may not
     * have in a language, as their page would be at a fixed address
     * (matched first, it would hide them): each with what has the address
     * and the address, the language's prefix in it. A collection without
     * entry pages has none; a taxonomy's terms have their shared pages and
     * their pages in collections. A pattern with related entries' slugs in
     * it (/projects/{project}/{slug}) counts whatever they are: they can
     * change after the entry is saved.
     *
     * @return array<string, array{title: string, url: string}>
     */
    public static function fixedSlugs(Collection|Taxonomy $record, string $locale): array
    {
        $patterns = array_filter([$record->routeIn($locale)]);
        if ($record instanceof Taxonomy && $record->exists) {
            foreach ($record->collections()->withTrashed()->wherePivot('term_pages', true)->get() as $collection) {
                $pivot = $collection->getRelation('pivot');
                if ($pivot instanceof CollectionTaxonomy) {
                    $patterns[] = $pivot->pattern($collection, $record, $locale);
                }
            }
        }
        if ($patterns === []) {
            return [];
        }
        $slugs = [];
        foreach (self::fixed($locale) as $page) {
            foreach ($patterns as $pattern) {
                $slug = (new RoutePattern($pattern))->match($page['path'])['slug'] ?? null;
                if ($slug !== null) {
                    $slugs[$slug] ??= ['title' => $page['title'], 'url' => Locales::prefix($locale).$page['path']];
                }
            }
        }

        return $slugs;
    }

    /**
     * The entry or term whose page is at an address in a language
     * ('"About" in Pages', '"News" in Topics'): by its live slug or its draft's,
     * in the trash too (it takes its address back when restored), in
     * deleted collections and taxonomies too. One query for the entries
     * and one for the terms, for the slugs their patterns would need.
     */
    private static function addressed(string $locale, string $path): ?string
    {
        $entries = [];
        foreach (Collection::query()->withTrashed()->whereNotNull('route')->get() as $collection) {
            $route = $collection->routeIn($locale);
            $slug = $route === null ? null : ((new RoutePattern($route))->match($path)['slug'] ?? null);
            if ($slug !== null) {
                $entries[$collection->id] = $slug;
            }
        }
        if ($entries !== []) {
            $found = EntryTranslation::query()->where('locale', $locale)
                ->where(function (Builder $query) use ($entries): void {
                    foreach ($entries as $id => $slug) {
                        $query->orWhere(fn (Builder $q) => $q->where('collection_id', $id)->where(fn (Builder $q) => $q->where('slug', $slug)->orWhere('draft_slug', $slug)));
                    }
                })->first();
            if ($found !== null) {
                return self::named($found->title ?? $found->draft_title, (string) Collection::query()->withTrashed()->find($found->collection_id)?->title);
            }
        }
        $terms = [];
        foreach (Taxonomy::query()->withTrashed()->with(['collections' => fn ($q) => $q->withTrashed()->wherePivot('term_pages', true)])->get() as $taxonomy) {
            $patterns = array_filter([$taxonomy->routeIn($locale)]);
            foreach ($taxonomy->collections as $collection) {
                $pivot = $collection->getRelation('pivot');
                if ($pivot instanceof CollectionTaxonomy) {
                    $patterns[] = $pivot->pattern($collection, $taxonomy, $locale);
                }
            }
            foreach ($patterns as $pattern) {
                $slug = (new RoutePattern($pattern))->match($path)['slug'] ?? null;
                if ($slug !== null) {
                    $terms[$taxonomy->id][] = $slug;
                }
            }
        }
        if ($terms === []) {
            return null;
        }
        $found = TermTranslation::query()->with(['term' => fn ($q) => $q->withTrashed(), 'term.taxonomy' => fn ($q) => $q->withTrashed()])->where('locale', $locale)
            ->where(function (Builder $query) use ($terms): void {
                foreach ($terms as $id => $slugs) {
                    $query->orWhere(fn (Builder $q) => $q->where('taxonomy_id', $id)->whereIn('slug', array_unique($slugs)));
                }
            })->first();

        return $found === null ? null : self::named($found->title, $found->term->taxonomy->title);
    }

    /** An entry or term by title and its collection's or taxonomy's: '"About" in Pages'. */
    private static function named(string $title, string $group): string
    {
        $named = __('":title" in :group', ['title' => $title, 'group' => $group]);

        return is_string($named) ? $named : "\"{$title}\" in {$group}";
    }

    /** The record as stored, before the changes made to it since it was loaded. */
    private static function stored(Collection|Taxonomy $record): Collection|Taxonomy
    {
        return (clone $record)->setRawAttributes($record->getRawOriginal());
    }

    /**
     * The collection and taxonomy whose term pages have a pattern in a
     * language ("Books · Tags"), but the pair left out.
     *
     * @param  array{0: int, 1: int}|null  $except  a collection's and a taxonomy's id
     */
    private static function termPages(string $locale, string $pattern, ?array $except = null): ?string
    {
        foreach (Taxonomy::query()->withTrashed()->with(['collections' => fn ($q) => $q->withTrashed()->wherePivot('term_pages', true)])->get() as $taxonomy) {
            foreach ($taxonomy->collections as $collection) {
                $pivot = $collection->getRelation('pivot');
                if (! $pivot instanceof CollectionTaxonomy || $except === [$collection->id, $taxonomy->id]) {
                    continue;
                }
                if ($pivot->pattern($collection, $taxonomy, $locale) === $pattern) {
                    return $collection->title.' · '.$taxonomy->title;
                }
            }
        }

        return null;
    }
}
