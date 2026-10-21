<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Sunrice\Cms\Fields\HydrationContext;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Site\Page;

/**
 * Entries the CMS hands templates one by one (an entries field's choices, the search page's results) read
 * their fields with a context of their own, a few queries each. This gives them one shared HydrationContext,
 * preloaded with everything their fields point at (fields.md → "Using fields in code"), so a list costs a
 * fixed number of queries, like a Cms::entries() result.
 */
final class Hydrate
{
    /**
     * @param  iterable<EntryTranslation>  $translations
     * @return Collection<int, EntryTranslation>
     */
    public static function together(iterable $translations): Collection
    {
        $items = collect($translations)->filter(fn ($t) => $t instanceof EntryTranslation)->values();
        if ($items->isEmpty()) {
            return $items;
        }

        // Their entries, collections and every language (completeData() reads the main one's shared fields).
        (new EloquentCollection($items->all()))->loadMissing(['entry.collection', 'entry.translations']);

        $page = Page::current();
        $preview = $page !== null && $page->preview;
        $context = new HydrationContext(app()->getLocale(), $preview);
        $references = [];
        foreach ($items as $translation) {
            array_push($references, ...$translation->entry->schema()->references($translation->completeData($preview)));
        }
        $context->preload($references);

        return $items->each(fn (EntryTranslation $translation) => $translation->hydrateWith($context));
    }
}
