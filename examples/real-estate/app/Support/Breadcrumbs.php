<?php

declare(strict_types=1);

namespace App\Support;

use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Site\Site;
use Sunrice\Cms\Support\Locales;

/**
 * Breadcrumbs for the page shown, in the page's language: Home, the collection's listing page, the parent
 * pages (each in this language, or its main language's page at this language's address when it isn't
 * translated: Site::urlIn()), the page. The seo component prints the matching BreadcrumbList itself.
 */
final class Breadcrumbs
{
    /** @return list<array{label: string, url: string|null}> */
    public static function forEntry(EntryTranslation $entry, ?string $title = null): array
    {
        $locale = app()->getLocale();
        $site = app(Site::class);
        $crumbs = [['label' => (string) __('site.home'), 'url' => url(Locales::prefix($locale) ?: '/')]];

        $collection = $entry->entry->collection;
        if (($listing = $collection->listingUrl($locale)) !== null) {
            $crumbs[] = ['label' => self::listingTitle($collection, $locale), 'url' => $listing];
        }

        foreach ($entry->entry->ancestors() as $ancestor) {
            $own = $ancestor->translation($locale);
            $shown = $own !== null && $own->isLive() ? $own : $ancestor->mainTranslation();
            if ($shown === null || ! $shown->isLive()) {
                continue;
            }
            $crumbs[] = ['label' => (string) $shown->title, 'url' => $site->urlIn($shown, $locale)];
        }

        $crumbs[] = ['label' => $title ?? (string) $entry->title, 'url' => null];

        return $crumbs;
    }

    /** @return list<array{label: string, url: string|null}> */
    public static function forListing(string $title): array
    {
        $locale = app()->getLocale();

        return [
            ['label' => (string) __('site.home'), 'url' => url(Locales::prefix($locale) ?: '/')],
            ['label' => $title, 'url' => null],
        ];
    }

    /**
     * A listing page's name in this language. Collection titles have one language only, so a line in
     * lang/{locale}/site.php ("collections.{handle}") wins over the collection's title.
     */
    public static function listingTitle(Collection $collection, string $locale): string
    {
        $key = 'site.collections.'.$collection->handle;

        return __($key) !== $key ? (string) __($key) : $collection->title;
    }
}
