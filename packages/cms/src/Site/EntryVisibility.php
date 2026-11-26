<?php

declare(strict_types=1);

namespace Sunrice\Cms\Site;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\EntryTranslation;

/**
 * Which entries the site lists to whom (Cms::hideEntriesUsing()): a
 * plugin narrows the site's queries of entries (listing and term pages,
 * Cms::entries(), search, menus, entries fields, the sitemap) to what the
 * visitor may see, and says who's looking, so that what's cached is kept
 * per kind of visitor. Kept for the life of the app.
 *
 * @internal
 */
final class EntryVisibility
{
    /** @var list<Closure(Builder<EntryTranslation>, bool): void> */
    private array $hide = [];

    /** @var list<Closure(): string> */
    private array $viewers = [];

    /**
     * @param  Closure(Builder<EntryTranslation>, bool): void  $hide
     * @param  Closure(): string  $viewer
     */
    public function add(Closure $hide, Closure $viewer): void
    {
        $this->hide[] = $hide;
        $this->viewers[] = $viewer;
    }

    public function active(): bool
    {
        return $this->hide !== [];
    }

    /**
     * Narrow a query of entries to what the visitor may see, or with
     * `$everyone` to what every visitor may (the sitemap: no one signed in).
     *
     * @template T of Builder<EntryTranslation>
     *
     * @param  T  $query
     * @return T
     */
    public function apply(Builder $query, bool $everyone = false): Builder
    {
        foreach ($this->hide as $hide) {
            $hide($query, $everyone);
        }

        return $query;
    }

    /** Who's looking, for cache keys: '' with no hooks, the same for every visitor the hooks treat alike. */
    public function key(): string
    {
        if ($this->viewers === []) {
            return '';
        }

        return sha1(implode("\n", array_map(fn (Closure $viewer): string => $viewer(), $this->viewers)));
    }

    /**
     * The entries among these the visitor may see in a language (all of
     * them without hooks). What counts is the translation the site shows
     * there, as entry links and fields pick it: this language's when it's
     * live, else the main language's; not whether some other translation
     * would pass, since a plugin may decide per translation.
     *
     * @param  iterable<Entry>  $entries  with their translations loaded
     * @return list<int>
     */
    public function visibleEntryIds(iterable $entries, string $locale, bool $everyone = false): array
    {
        $shown = [];
        $ids = [];
        foreach ($entries as $entry) {
            $ids[] = $entry->id;
            $translation = $entry->translation($locale);
            $translation = $translation !== null && $translation->isLive() ? $translation : $entry->mainTranslation();
            if ($translation !== null) {
                $shown[$translation->id] = $entry->id;
            }
        }
        if (! $this->active() || $ids === []) {
            return $ids;
        }
        if ($shown === []) {
            return [];
        }
        $visible = $this->apply(EntryTranslation::query()->whereKey(array_keys($shown)), $everyone)->pluck('id')->all();

        return array_values(array_unique(array_map(fn (mixed $id): int => $shown[(int) $id], array_filter($visible, is_numeric(...)))));
    }
}
