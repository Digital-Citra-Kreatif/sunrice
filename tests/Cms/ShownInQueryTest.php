<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
 * EntryTranslation::shownIn() in a language other than the main one, with
 * the main language standing in: on MySQL and SQLite the entries live in
 * the language are found once (NOT IN), not looked up for each
 * main-language row (a NOT EXISTS under an OR, which MySQL can't turn into
 * an anti-join); Postgres keeps the NOT EXISTS. The same entries, in the
 * same order, as the NOT EXISTS gave, on listing, term and index pages and
 * from Cms::entries().
 */

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['sunrice-cms.locales' => ['en', 'id', 'zh'], 'sunrice-cms.cache.enabled' => false]);
    $this->topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'route' => '/topics/{slug}', 'index_route' => '/topics']);
    $this->fruit = app(SaveTerm::class)->handle($this->topics, ['title' => 'Fruit']);
    $this->other = app(SaveTerm::class)->handle($this->topics, ['title' => 'Other']);
    $this->news = Collection::query()->create(['handle' => 'news', 'title' => 'News', 'dated' => true, 'route' => '/news/{slug}', 'listing_route' => '/news', 'per_page' => 3]);
    $this->shop = Collection::query()->create(['handle' => 'shop', 'title' => 'Shop', 'route' => '/shop/{slug}', 'listing_route' => '/shop', 'per_page' => 3]);
    $this->gone = Collection::query()->create(['handle' => 'gone', 'title' => 'Gone', 'dated' => true, 'route' => '/gone/{slug}', 'listing_route' => '/gone', 'per_page' => 3]);
    foreach ([$this->news, $this->shop, $this->gone] as $collection) {
        $collection->taxonomies()->attach($this->topics);
        // English only; live in Indonesian too; an Indonesian draft; one scheduled in Indonesian; Chinese live; an English draft
        // with Indonesian live; one scheduled; one in the trash with Indonesian live. Dates that tie.
        foreach (['Mango', 'apple', 'Kiwi', 'Banana', 'cherry', 'Durian', 'Fig', 'Lime', 'Grape', 'Plum', 'Quince'] as $i => $title) {
            $main = app(CreateEntry::class)->handle($collection, ['title' => $title, 'terms' => ['topics' => [$i % 3 === 0 ? $this->other->id : $this->fruit->id]]]);
            if ($title !== 'Fig') {
                app(PublishEntry::class)->handle($main, $title === 'Plum' ? now()->addDay() : now()->subDays($i % 3)->startOfDay());
            }
            $own = fn (string $locale) => tap(app(CreateTranslation::class)->handle($main->entry, $locale))->update(['draft_title' => "{$title} ({$locale})"]);
            match ($title) {
                'apple', 'Banana', 'Fig', 'Lime' => app(PublishEntry::class)->handle($own('id')->refresh(), now()->subDays($i % 2)->startOfDay()),
                'Kiwi' => $own('id'), // a draft
                'cherry' => app(PublishEntry::class)->handle($own('id')->refresh(), now()->addDay()),
                'Durian', 'Quince' => app(PublishEntry::class)->handle($own('zh')->refresh(), now()->subDays(2)->startOfDay()),
                default => null,
            };
            if ($title === 'Lime') {
                app(TrashEntry::class)->handle($main->entry);
            }
        }
    }
    app(DeleteCollection::class)->handle($this->gone);
});

/**
 * Translations shown in a language as the NOT EXISTS under an OR found
 * them (what shownIn() did before).
 *
 * @param  list<string>  $handles
 * @return Builder<EntryTranslation>
 */
function existsShownIn(string $locale, bool $fallback, bool $preview, array $handles = []): Builder
{
    $table = 'sunrice_entry_translations';

    return EntryTranslation::query()
        ->when($locale === 'en' || ! $fallback, fn (Builder $q) => $q->where("{$table}.locale", $locale), fn (Builder $q) => $q->where(fn (Builder $either) => $either->where("{$table}.locale", $locale)
            ->orWhere(fn (Builder $stand) => $stand->where("{$table}.locale", 'en')->whereNotExists(fn (QueryBuilder $own) => $own->selectRaw('1')->from("{$table} as own")
                ->whereColumn('own.entry_id', "{$table}.entry_id")->where('own.locale', $locale)
                ->when(! $preview, fn (QueryBuilder $live) => $live->where('own.status', EntryTranslation::PUBLISHED)->where('own.published_at', '<=', now()))))))
        ->when(! $preview, fn (Builder $q) => $q->where('status', EntryTranslation::PUBLISHED)->where('published_at', '<=', now()))
        ->notTrashed()
        ->when($handles !== [], fn (Builder $q) => $q->whereIn('collection_id', Collection::withTrashed()->whereIn('handle', $handles)->pluck('id')));
}

it('finds the same entries, in the same order, as the NOT EXISTS did', function () {
    foreach ([[], ['news'], ['news', 'shop'], ['gone']] as $handles) {
        foreach (['en', 'id', 'zh'] as $locale) {
            foreach ([true, false] as $fallback) {
                foreach ([false, true] as $preview) {
                    $label = implode(',', $handles)." {$locale} fallback ".(int) $fallback.' preview '.(int) $preview;
                    $query = fn () => Content::entries(...$handles)->locale($locale)->fallback($fallback)->preview($preview);
                    $date = $preview ? 'coalesce(published_at, created_at)' : 'published_at';
                    $expected = existsShownIn($locale, $fallback, $preview, $handles)->orderByRaw("{$date} desc")->orderByDesc('id')->pluck('id')->all();
                    expect($query()->latest()->get()->pluck('id')->all())->toBe($expected, $label)
                        ->and($query()->count())->toBe(count($expected), $label)
                        ->and($query()->oldest()->get()->pluck('id')->all())->toBe(existsShownIn($locale, $fallback, $preview, $handles)->orderByRaw("{$date} asc")->orderBy('id')->pluck('id')->all(), $label);
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
    // Something to compare: per collection, eight live in English; in Indonesian three of their own (one whose
    // English is a draft), six stood in for; in Chinese two of their own, six stood in for.
    expect(Content::entries('news')->count())->toBe(8)
        ->and(Content::entries('news')->locale('id')->count())->toBe(9)
        ->and(Content::entries('news')->locale('id')->fallback(false)->count())->toBe(3)
        ->and(Content::entries('news')->locale('zh')->count())->toBe(8)
        ->and(Content::entries('gone')->locale('id')->get())->toBeEmpty();
});

it('shows the same entries on listing, term and index pages as the NOT EXISTS did', function () {
    foreach (['en', 'id', 'zh'] as $locale) {
        $prefix = $locale === 'en' ? '' : "/{$locale}";
        foreach ([$this->news, $this->shop] as $collection) {
            $expected = existsShownIn($locale, true, false, [$collection->handle])
                ->when($collection->dated, fn (Builder $q) => $q->orderByDesc('published_at')->orderByDesc('id'), fn (Builder $q) => $q->orderBy('title')->orderBy('id'))
                ->pluck('id')->all();
            for ($page = 1; ($page - 1) * 3 < count($expected); $page++) {
                $entries = get("{$prefix}{$collection->listing_route}?page={$page}")->assertOk()->viewData('entries')->paginator();
                expect($entries->getCollection()->modelKeys())->toBe(array_slice($expected, ($page - 1) * 3, 3), "{$collection->handle} {$locale} page {$page}")
                    ->and($entries->total())->toBe(count($expected));
            }
        }
        foreach (['fruit' => $this->fruit, 'other' => $this->other] as $slug => $term) {
            $expected = existsShownIn($locale, true, false)->whereHas('entry.terms', fn (Builder $q) => $q->where('sunrice_terms.id', $term->id))
                ->orderByDesc('published_at')->orderByDesc('id')->pluck('id')->all();
            expect($expected)->not->toBeEmpty();
            $entries = get("{$prefix}/topics/{$slug}")->assertOk()->viewData('entries')->paginator();
            expect($entries->getCollection()->modelKeys())->toBe(array_slice($expected, 0, 10), "term {$slug} {$locale}")
                ->and($entries->total())->toBe(count($expected));
        }
        foreach (['/topics' => null, '/news/topics' => $this->news] as $path => $collection) {
            $counts = get("{$prefix}{$path}")->assertOk()->viewData('terms')->mapWithKeys(fn (Term $term) => [$term->id => $term->entries_count])->all();
            $expected = [];
            foreach ([$this->fruit, $this->other] as $term) {
                $expected[$term->id] = existsShownIn($locale, true, false, $collection === null ? [] : [$collection->handle])
                    ->whereHas('entry.terms', fn (Builder $q) => $q->where('sunrice_terms.id', $term->id))->count();
            }
            expect($counts)->toBe($expected, "index {$path} {$locale}");
        }
    }
});

it('finds the entries live in the language once, not per row, on MySQL and SQLite', function () {
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $sql = str_replace(['"', '`'], '', $query->sql); // MySQL quotes with backticks
        if (str_contains($sql, 'from sunrice_entry_translations as own')) {
            $queries[] = $sql;
        }
    });

    expect(Content::entries('news')->locale('id')->latest()->paginate(3))->toHaveCount(3)
        ->and(Content::entries()->locale('zh')->preview()->limit(2)->get())->toHaveCount(2);
    get('/id/news?page=2')->assertOk();
    get('/zh/topics/fruit')->assertOk();
    get('/id/topics')->assertOk();

    expect(count($queries))->toBeGreaterThanOrEqual(6);
    foreach ($queries as $sql) {
        DB::getDriverName() === 'pgsql'
            ? expect($sql)->toContain('not exists (select 1 from sunrice_entry_translations as own where own.entry_id = sunrice_entry_translations.entry_id and own.locale = ?')
            : expect($sql)->toContain('sunrice_entry_translations.locale in (?, ?) and (sunrice_entry_translations.locale = ? or sunrice_entry_translations.entry_id not in (select own.entry_id from sunrice_entry_translations as own where own.locale = ?')
                ->and($sql)->not->toContain('own.entry_id = sunrice_entry_translations.entry_id');
    }
});

it('indexes live translations with their entries, and the entries live in each language', function () {
    $indexes = collect(Schema::getIndexes('sunrice_entry_translations'))->mapWithKeys(fn (array $index) => [$index['name'] => $index['columns']]);

    expect($indexes->get('sunrice_entry_tr_shown_index'))->toBe(['collection_id', 'locale', 'status', 'published_at', 'entry_id'])
        ->and($indexes->get('sunrice_entry_tr_locale_live_index'))->toBe(['locale', 'status', 'published_at', 'entry_id'])
        ->and($indexes->has('sunrice_entry_tr_live_index'))->toBeFalse();
});
