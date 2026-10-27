<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Collections\DeleteCollection;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\TrashEntry;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Content;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\Term;

use function Pest\Laravel\get;

/*
 * Live entries (EntryTranslation::live(), Cms::entries(), listing and
 * term pages) leave out deleted entries and collections without an
 * exists on the collection nested in the entry's, and Cms::entries()
 * names its collections by id: MySQL joined every collection with the
 * rows for those and sorted them whole (80 ms for a page of 1,300).
 * The same entries, in the same order, as the nested form gave.
 */

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['sunrice-cms.locales' => ['en', 'id', 'zh'], 'sunrice-cms.cache.enabled' => false]);
    $this->topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'route' => '/topics/{slug}']);
    $this->topic = app(SaveTerm::class)->handle($this->topics, ['title' => 'Fruit']);
    $this->news = Collection::query()->create(['handle' => 'news', 'title' => 'News', 'dated' => true, 'route' => '/news/{slug}', 'listing_route' => '/news', 'per_page' => 2]);
    $this->shop = Collection::query()->create(['handle' => 'shop', 'title' => 'Shop', 'route' => '/shop/{slug}', 'listing_route' => '/shop', 'per_page' => 2]);
    $this->gone = Collection::query()->create(['handle' => 'gone', 'title' => 'Gone', 'dated' => true, 'route' => '/gone/{slug}', 'listing_route' => '/gone', 'per_page' => 2]);
    foreach ([$this->news, $this->shop, $this->gone] as $collection) {
        $collection->taxonomies()->attach($this->topics);
        // Live in English only, live in Indonesian too, an Indonesian draft or one scheduled, a draft, one scheduled, one in the trash; dates that tie.
        foreach (['Mango', 'apple', 'Kiwi', 'Banana', 'cherry', 'Durian', 'Fig', 'Lime', 'Grape', 'Plum'] as $i => $title) {
            $main = app(CreateEntry::class)->handle($collection, ['title' => $title, 'terms' => ['topics' => $i % 3 === 0 ? [] : [$this->topic->id]]]);
            if ($title === 'Fig') {
                continue; // a draft
            }
            app(PublishEntry::class)->handle($main, $title === 'Plum' ? now()->addDay() : now()->subDays($i % 3)->startOfDay());
            if ($i % 2 === 0) {
                $own = app(CreateTranslation::class)->handle($main->entry, 'id');
                $own->update(['draft_title' => "{$title} (id)"]);
                $i % 4 === 0 ? app(PublishEntry::class)->handle($own->refresh(), $i === 8 ? now()->addDay() : now()->subDays($i % 3)->startOfDay()) : null;
            }
            if ($title === 'Lime') {
                app(TrashEntry::class)->handle($main->entry);
            }
        }
    }
    app(DeleteCollection::class)->handle($this->gone);
});

/**
 * Translations as the nested exists found them: entry not deleted, and
 * through it the collection.
 *
 * @param  list<string>  $handles
 * @return Builder<EntryTranslation>
 */
function nestedLive(string $locale, bool $fallback, bool $preview, array $handles): Builder
{
    return EntryTranslation::query()->shownIn($locale, $fallback, $preview)
        ->when(! $preview, fn (Builder $q) => $q->where('status', EntryTranslation::PUBLISHED)->where('published_at', '<=', now()))
        ->whereHas('entry', fn (Builder $entry) => $entry->withoutTrashed()->whereHas('collection', fn (Builder $collection) => $collection->withoutTrashed()))
        ->when($handles !== [], fn (Builder $q) => $q->whereIn('collection_id', Collection::withTrashed()->whereIn('handle', $handles)->pluck('id')));
}

it('finds the same entries, in the same order, as the nested exists did', function () {
    foreach ([[], ['news'], ['news', 'shop'], ['gone'], ['news', 'gone']] as $handles) {
        foreach (['en', 'id', 'zh'] as $locale) {
            foreach ([true, false] as $fallback) {
                foreach ([false, true] as $preview) {
                    $label = implode(',', $handles)." {$locale} fallback ".(int) $fallback.' preview '.(int) $preview;
                    $query = fn () => Content::entries(...$handles)->locale($locale)->fallback($fallback)->preview($preview);
                    $reference = fn () => nestedLive($locale, $fallback, $preview, $handles);
                    $order = fn (Builder $q) => $preview ? $q->orderByRaw('coalesce(published_at, created_at) desc')->orderByDesc('id') : $q->orderByDesc('published_at')->orderByDesc('id');
                    $expected = $order($reference())->pluck('id')->all();
                    expect($query()->latest()->get()->pluck('id')->all())->toBe($expected, $label)
                        ->and($query()->count())->toBe(count($expected), $label)
                        // By title alone: the same titles in both collections tie, in no set order.
                        ->and($query()->alphabetical()->get()->pluck($preview ? 'draft_title' : 'title')->all())->toBe($reference()->orderBy($preview ? 'draft_title' : 'title')->pluck($preview ? 'draft_title' : 'title')->all(), $label);
                    for ($page = 1; $page <= 3; $page++) {
                        Paginator::currentPageResolver(fn () => $page);
                        $paged = $query()->latest()->paginate(4);
                        expect($paged->getCollection()->modelKeys())->toBe(array_slice($expected, ($page - 1) * 4, 4), "{$label} page {$page}")
                            ->and($paged->total())->toBe(count($expected));
                    }
                    Paginator::currentPageResolver(fn () => 1);
                }
            }
        }
    }
    // Something to compare: the deleted collection's and entry's, drafts and scheduled ones are left out.
    expect(Content::entries()->count())->toBe(14)
        ->and(Content::entries('gone')->get())->toBeEmpty()
        ->and(Content::entries()->locale('id')->fallback(false)->count())->toBe(4)
        ->and(Content::entries()->preview()->count())->toBe(18);
});

it('lists the same entries on listing and term pages as the nested exists did', function () {
    foreach (['en', 'id', 'zh'] as $locale) {
        $prefix = $locale === 'en' ? '' : "/{$locale}";
        foreach ([$this->news, $this->shop] as $collection) {
            $expected = nestedLive($locale, true, false, [$collection->handle])
                ->when($collection->dated, fn (Builder $q) => $q->orderByDesc('published_at')->orderByDesc('id'), fn (Builder $q) => $q->orderBy('title')->orderBy('id'))
                ->pluck('id')->all();
            expect($expected)->toHaveCount(7);
            foreach ([1, 2, 3, 4] as $page) {
                $entries = get("{$prefix}{$collection->listing_route}?page={$page}")->assertOk()->viewData('entries')->paginator();
                expect($entries->getCollection()->modelKeys())->toBe(array_slice($expected, ($page - 1) * 2, 2), "{$collection->handle} {$locale} page {$page}")
                    ->and($entries->total())->toBe(7);
            }
        }
        get("{$prefix}/gone")->assertNotFound();
        $expected = nestedLive($locale, true, false, [])->whereHas('entry.terms', fn (Builder $q) => $q->where('sunrice_terms.id', $this->topic->id))
            ->orderByDesc('published_at')->orderByDesc('id')->pluck('id')->all();
        expect($expected)->toHaveCount(10);
        $entries = get("{$prefix}/topics/fruit")->assertOk()->viewData('entries')->paginator();
        expect($entries->getCollection()->modelKeys())->toBe($expected, "term {$locale}")
            ->and($entries->total())->toBe(10);
    }
});

it('checks the collection on the translation, not through the entry, and names Cms::entries() collections by id', function () {
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $sql = str_replace(['"', '`'], '', $query->sql); // MySQL quotes with backticks
        if (str_contains($sql, 'from sunrice_entry_translations')) {
            $queries[] = $sql;
        }
    });

    expect(Content::entries('news', 'shop')->locale('id')->latest()->paginate(2))->toHaveCount(2)
        ->and(Content::entries()->preview()->latest()->limit(3)->get())->toHaveCount(3);
    get('/id/news?page=2')->assertOk();
    get('/topics/fruit')->assertOk();

    $listed = array_values(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'sunrice_entries.deleted_at is null')));
    expect(count($listed))->toBeGreaterThanOrEqual(6);
    foreach ($listed as $sql) {
        expect($sql)->not->toContain('sunrice_entries.collection_id = sunrice_collections.id')
            ->and($sql)->toContain('sunrice_entry_translations.collection_id not in (select id from sunrice_collections where sunrice_collections.deleted_at is not null)')
            ->and($sql)->not->toContain('handle in');
    }
});
