<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\TrashEntry;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Content;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Site\SiteController;

use function Pest\Laravel\get;

/*
 * Listing pages and Cms::entries()->paginate() read a page's ids first,
 * then its rows (Query\IdPage): the same entries, in the same order, as
 * paginating the query itself, in every language, with the main language
 * standing in for entries not live in one.
 */

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['sunrice-cms.locales' => ['en', 'id', 'zh'], 'sunrice-cms.cache.enabled' => false]);
    $this->news = Collection::query()->create(['handle' => 'news', 'title' => 'News', 'dated' => true, 'route' => '/news/{slug}', 'listing_route' => '/news', 'per_page' => 2]);
    $this->shop = Collection::query()->create(['handle' => 'shop', 'title' => 'Shop', 'route' => '/shop/{slug}', 'listing_route' => '/shop', 'per_page' => 2]);
    $this->guide = Collection::query()->create(['handle' => 'guide', 'title' => 'Guide', 'ordered' => true, 'route' => '/guide/{slug}', 'listing_route' => '/guide', 'per_page' => 2]);
    // In each: entries in English only, live in Indonesian too, with an Indonesian draft or one scheduled, a draft, one in the trash; dates that tie.
    foreach ([$this->news, $this->shop, $this->guide] as $collection) {
        foreach (['Mango', 'apple', 'Kiwi', 'Banana', 'cherry', 'Durian', 'Fig', 'Lime', 'Grape'] as $i => $title) {
            $main = app(CreateEntry::class)->handle($collection, ['title' => $title]);
            if ($title === 'Fig') {
                continue; // a draft
            }
            app(PublishEntry::class)->handle($main, now()->subDays($i % 3)->startOfDay());
            if ($i % 2 === 0) {
                $own = app(CreateTranslation::class)->handle($main->entry, 'id');
                $own->update(['draft_title' => "{$title} (id)"]);
                $i % 4 === 0 ? app(PublishEntry::class)->handle($own->refresh(), $i === 8 ? now()->addDay() : now()->subDays($i % 3)->startOfDay()) : null;
            }
            if ($title === 'Lime') {
                app(TrashEntry::class)->handle($main->entry);
            }
        }
        // Manual order unlike the titles.
        Entry::query()->where('collection_id', $collection->id)->get()->each(fn (Entry $entry) => $entry->update(['sort_order' => (7 * $entry->id) % 11]));
    }
});

/**
 * The listing's page as paginating the query itself gives it (what the
 * listing page did before it read ids first).
 *
 * @return list<int>
 */
function listingReference(Collection $collection, string $locale, int $page): array
{
    Paginator::currentPageResolver(fn () => $page);
    $query = EntryTranslation::query()->live()->where('collection_id', $collection->id)->shownIn($locale)
        ->when($collection->ordered,
            fn (Builder $q) => $q->orderBy(Entry::query()->select('sort_order')->whereColumn('sunrice_entries.id', 'sunrice_entry_translations.entry_id'))->orderBy('id'),
            fn (Builder $q) => $collection->dated ? $q->orderByDesc('published_at')->orderByDesc('id') : $q->orderBy('title')->orderBy('id'));

    return $query->paginate(max(1, min(100, $collection->per_page)))->getCollection()->modelKeys();
}

it('lists the same entries in the same order as before, page by page, in every language', function () {
    foreach ([$this->news, $this->shop, $this->guide] as $collection) {
        foreach (['en', 'id', 'zh'] as $locale) {
            $prefix = $locale === 'en' ? '' : "/{$locale}";
            $seen = 0;
            for ($page = 1; $page <= 4; $page++) {
                $expected = listingReference($collection, $locale, $page);
                $response = get("{$prefix}{$collection->listing_route}?page={$page}");
                if ($expected === []) {
                    $response->assertNotFound();

                    break;
                }
                $entries = $response->assertOk()->viewData('entries')->paginator();
                expect($entries->getCollection()->modelKeys())->toBe($expected, "{$collection->handle} {$locale} page {$page}")
                    ->and($entries->getCollection()->every(fn (EntryTranslation $t): bool => $t->relationLoaded('entry') && $t->entry->relationLoaded('collection')))->toBeTrue();
                $seen += count($expected);
                expect($entries->total())->toBe(7);
            }
            // Seven live entries, whichever language stands in.
            expect($seen)->toBe(7);
        }
    }
});

it('pages Cms::entries() the same way, cached or not', function () {
    foreach ([false, true] as $cached) {
        config(['sunrice-cms.cache.enabled' => $cached]);
        foreach (['en', 'id', 'zh'] as $locale) {
            for ($page = 1; $page <= 4; $page++) {
                Paginator::currentPageResolver(fn () => $page);
                $reference = Content::entries('news')->locale($locale)->latest()->toBase()->paginate(2);
                $paged = Content::entries('news')->locale($locale)->latest()->paginate(2);
                expect($paged->getCollection()->modelKeys())->toBe($reference->getCollection()->modelKeys())
                    ->and($paged->total())->toBe($reference->total());
            }
        }
    }
});

it('sorts and skips the ids only, not the whole translations', function () {
    $sorted = [];
    DB::listen(function ($query) use (&$sorted) {
        if (str_contains($query->sql, 'from "sunrice_entry_translations"') && str_contains($query->sql, ' offset ')) {
            $sorted[] = $query->sql;
        }
    });

    get('/id/news?page=2')->assertOk();
    get('/zh/shop?page=3')->assertOk();
    expect(Content::entries('guide')->locale('id')->ordered()->paginate(2))->toHaveCount(2);

    expect($sorted)->toHaveCount(3)
        ->and(collect($sorted)->every(fn (string $sql): bool => str_starts_with($sql, 'select "sunrice_entry_translations"."id" from')))->toBeTrue();
    expect(SiteController::listed('id'))->toBe(['entry.collection', 'entry.translations']);
});
