<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Collections\DeleteCollection;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\TrashEntry;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Actions\Terms\TrashTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\Term;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 * Terms' entries_count (taxonomy index pages, <x-sunrice-cms::terms>, the
 * admin's terms list) comes from one grouped query for all the terms
 * (Term::countEntries()), not a count per term: the same counts, and the
 * same terms in the same order, as withCount('entries') gave.
 */

beforeEach(function () {
    // The CMS's pages are bundled: there's no page file to look for.
    config(['sunrice-cms.locales' => ['en', 'id', 'zh'], 'sunrice-cms.cache.enabled' => false, 'inertia.testing.ensure_pages_exist' => false]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    $this->topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'hierarchical' => true, 'route' => '/topics/{slug}', 'index_route' => '/topics']);
    $this->tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags', 'route' => '/tags/{slug}', 'index_route' => '/tags']);
    $term = fn (Taxonomy $taxonomy, string $title, ?Term $parent = null) => app(SaveTerm::class)->handle($taxonomy, ['title' => $title, 'parent_id' => $parent?->id]);
    $fruit = $term($this->topics, 'Fruit');
    $citrus = $term($this->topics, 'Citrus', $fruit);
    $topics = [$fruit, $citrus, $term($this->topics, 'Lemon', $citrus), $term($this->topics, 'Veg'), $old = $term($this->topics, 'Old')];
    $tags = [$term($this->tags, 'Red'), $term($this->tags, 'Green'), $term($this->tags, 'Unused')];
    $this->news = Collection::query()->create(['handle' => 'news', 'title' => 'News', 'dated' => true, 'route' => '/news/{slug}', 'listing_route' => '/news']);
    $this->shop = Collection::query()->create(['handle' => 'shop', 'title' => 'Shop', 'route' => '/shop/{slug}', 'listing_route' => '/shop']);
    $this->gone = Collection::query()->create(['handle' => 'gone', 'title' => 'Gone', 'route' => '/gone/{slug}', 'listing_route' => '/gone']);
    foreach ([$this->news, $this->shop, $this->gone] as $collection) {
        $collection->taxonomies()->attach([$this->topics->id, $this->tags->id]);
        // English only; live in Indonesian too; an Indonesian draft; one scheduled in Indonesian; Chinese live; an English
        // draft with Indonesian live; one scheduled; one in the trash.
        foreach (['Mango', 'apple', 'Kiwi', 'Banana', 'cherry', 'Durian', 'Fig', 'Lime', 'Grape', 'Plum', 'Quince'] as $i => $title) {
            $chosen = ['topics' => array_values(array_unique([$topics[$i % 5]->id, ...($i % 4 === 0 ? [$topics[3]->id] : [])])), 'tags' => $i % 2 === 0 ? [$tags[0]->id] : [$tags[0]->id, $tags[1]->id]];
            $main = app(CreateEntry::class)->handle($collection, ['title' => $title, 'terms' => $chosen]);
            if ($title !== 'Fig') {
                app(PublishEntry::class)->handle($main, $title === 'Plum' ? now()->addDay() : now()->subDays($i % 3)->startOfDay());
            }
            $own = fn (string $locale) => tap(app(CreateTranslation::class)->handle($main->entry, $locale))->update(['draft_title' => "{$title} ({$locale})"]);
            match ($title) {
                'apple', 'Banana', 'Fig' => app(PublishEntry::class)->handle($own('id')->refresh(), now()->subDays($i % 2)->startOfDay()),
                'Kiwi' => $own('id'),
                'cherry' => app(PublishEntry::class)->handle($own('id')->refresh(), now()->addDay()),
                'Durian', 'Quince' => app(PublishEntry::class)->handle($own('zh')->refresh(), now()->subDays(2)->startOfDay()),
                default => null,
            };
            if ($title === 'Lime') {
                app(TrashEntry::class)->handle($main->entry);
            }
        }
    }
    // The shop's entries keep their topics, which no longer count there; a term and a collection in the trash.
    $this->shop->taxonomies()->detach($this->topics->id);
    app(TrashTerm::class)->handle($old);
    app(DeleteCollection::class)->handle($this->gone);
});

/**
 * The terms (ids in order => entries_count) as an index page counted them
 * before: withCount('entries') on each, of its live translations.
 *
 * @return array<int, int>
 */
function indexCountsBefore(Taxonomy $taxonomy, string $locale, ?Collection $collection): array
{
    $live = fn (Builder $entries) => $entries->whereHas('translations', fn (Builder $q) => $q->whereIn('id', EntryTranslation::query()->live()->shownIn($locale)
        ->when($collection !== null, fn (Builder $q) => $q->where('sunrice_entry_translations.collection_id', $collection?->id))->select('id')));

    return Term::query()->where('taxonomy_id', $taxonomy->id)->withCount(['entries' => $live])
        ->when($collection !== null, fn ($q) => $q->whereHas('entries', $live))
        ->orderBy('sort_order')->orderBy('id')->get()->mapWithKeys(fn (Term $term) => [$term->id => $term->entries_count])->all();
}

/**
 * <x-sunrice-cms::terms>'s terms and counts, as "id:count" in its order.
 *
 * @return list<string>
 */
function termsComponent(string $locale, string $props): array
{
    app()->setLocale($locale);

    return array_values(array_filter(explode(',', trim(Blade::render('<x-sunrice-cms::terms '.$props.'>@foreach ($component->terms as $t){{ $t->id }}:{{ $t->entries_count }},@endforeach</x-sunrice-cms::terms>')))));
}

/**
 * What the component counted before: withCount('entries') on each term of
 * the live entries (of the collection when given); with children, each
 * entry of the term or one under it once.
 *
 * @return array<int, int>
 */
function componentCountsBefore(Taxonomy $taxonomy, string $locale, ?Collection $collection, bool $includeChildren): array
{
    $entries = fn (Builder $q) => $q->when($collection !== null, fn (Builder $q) => $q->where('collection_id', $collection?->id))
        ->whereIn('sunrice_entries.id', EntryTranslation::query()->live()->shownIn($locale)->select('entry_id'));
    $terms = Term::query()->where('taxonomy_id', $taxonomy->id)->withCount(['entries' => $entries])->orderBy('sort_order')->orderBy('id')->get();

    return $terms->mapWithKeys(fn (Term $term) => [$term->id => $includeChildren
        ? $entries(Entry::query()->whereHas('terms', fn (Builder $q) => $q->whereIn('sunrice_terms.id', [$term->id, ...$term->descendantIds()])))->count()
        : $term->entries_count])->all();
}

it('counts the same entries on index pages, shared and in a collection, in every language, with and without the fallback', function () {
    foreach ([true, false] as $fallback) {
        config(['sunrice-cms.site.fallback' => $fallback]);
        foreach (['en', 'id', 'zh'] as $locale) {
            $prefix = $locale === 'en' ? '' : "/{$locale}";
            foreach (['/topics' => [$this->topics, null], '/tags' => [$this->tags, null], '/news/topics' => [$this->topics, $this->news], '/news/tags' => [$this->tags, $this->news], '/shop/tags' => [$this->tags, $this->shop]] as $path => [$taxonomy, $collection]) {
                $terms = get("{$prefix}{$path}")->assertOk()->viewData('terms');
                expect($terms->mapWithKeys(fn (Term $term) => [$term->id => $term->entries_count])->all())
                    ->toBe(indexCountsBefore($taxonomy, $locale, $collection), "{$prefix}{$path} fallback ".(int) $fallback);
            }
            get("{$prefix}/shop/topics")->assertNotFound();
        }
    }
    // Something to compare: in the news, Red on its eight live entries, Green on three, Unused on none (so not listed).
    expect(array_values(indexCountsBefore($this->tags, 'en', $this->news)))->toBe([8, 3]);
});

it('counts the same entries in the terms component, with its collection, children, order and empty ones left out', function () {
    foreach (['en', 'id', 'zh'] as $locale) {
        foreach ([null, $this->news, $this->shop] as $collection) {
            foreach ([$this->topics, $this->tags] as $taxonomy) {
                $in = $collection === null ? '' : " collection=\"{$collection->handle}\"";
                $label = "{$taxonomy->handle} {$locale}{$in}";
                $before = componentCountsBefore($taxonomy, $locale, $collection, false);
                $pairs = array_map(fn (int $id, int $count): string => "{$id}:{$count}", array_keys($before), $before);
                expect(termsComponent($locale, "taxonomy=\"{$taxonomy->handle}\"{$in}"))->toBe($pairs, $label)
                    ->and(termsComponent($locale, "taxonomy=\"{$taxonomy->handle}\"{$in} hide-empty"))->toBe(array_values(array_filter($pairs, fn (string $pair): bool => ! str_ends_with($pair, ':0'))), "{$label} hide-empty");
                arsort($before);
                expect(array_map(fn (string $pair): int => (int) explode(':', $pair)[1], termsComponent($locale, "taxonomy=\"{$taxonomy->handle}\"{$in} order-by=\"-entries\"")))->toBe(array_values($before), "{$label} by entries");
                $children = componentCountsBefore($taxonomy, $locale, $collection, true);
                expect(termsComponent($locale, "taxonomy=\"{$taxonomy->handle}\"{$in} include-children"))->toBe(array_map(fn (int $id, int $count): string => "{$id}:{$count}", array_keys($children), $children), "{$label} include-children");
            }
        }
    }
    // Fruit counts its children's entries with include-children.
    expect(componentCountsBefore($this->topics, 'en', null, true))->not->toBe(componentCountsBefore($this->topics, 'en', null, false));
});

it('counts the same entries in the admin terms list, the trash too', function () {
    actingAs(adminUser('sunrice.access', 'cms.terms.view'));
    foreach ([[$this->topics, false], [$this->tags, false], [$this->topics, true]] as [$taxonomy, $trashed]) {
        $before = Term::query()->when($trashed, fn ($q) => $q->onlyTrashed())->withCount('entries')->where('taxonomy_id', $taxonomy->id)->get()
            ->mapWithKeys(fn (Term $term) => [$term->id => $term->entries_count])->all();
        get(route('sunrice.admin.cms.taxonomies.terms.index', [$taxonomy->handle, ...($trashed ? ['status' => 'trashed'] : [])]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('terms', fn ($terms) => collect($terms)->mapWithKeys(fn (array $term) => [$term['id'] => $term['entries']])->sortKeys()->all() === collect($before)->sortKeys()->all()));
    }
});

it('counts every term in one query, however many terms there are', function () {
    actingAs(adminUser('sunrice.access', 'cms.terms.view'));
    $counts = function (): array {
        $queries = [];
        foreach (['index' => fn () => get('/id/topics')->assertOk(), 'collection index' => fn () => get('/zh/news/tags')->assertOk(),
            'component' => fn () => termsComponent('id', 'taxonomy="tags" collection="news" hide-empty'), 'component with children' => fn () => termsComponent('id', 'taxonomy="topics" include-children'),
            'admin' => fn () => get(route('sunrice.admin.cms.taxonomies.terms.index', 'topics'))->assertOk()] as $what => $run) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $run();
            $log = collect(DB::getQueryLog())->pluck('query')->map(fn (string $sql) => str_replace(['"', '`'], '', $sql)); // MySQL quotes with backticks
            DB::disableQueryLog();
            // The pivot read once, grouped by term (or its pairs, to count each entry once with the children).
            $pivot = $log->filter(fn (string $sql) => str_contains($sql, 'sunrice_entry_term'));
            $queries[$what] = [$log->count(), $pivot->count(), $pivot->every(fn (string $sql) => str_starts_with($sql, 'select term_id') && str_contains($sql, 'from sunrice_entry_term where term_id in (select id from sunrice_terms where taxonomy_id = ?)'))];
        }

        return $queries;
    };
    $counts(); // the first requests read the settings and routes once
    $few = $counts();
    // Twenty more terms, each with entries.
    foreach (range(1, 20) as $i) {
        $term = app(SaveTerm::class)->handle($i % 2 === 0 ? $this->topics : $this->tags, ['title' => "More {$i}"]);
        DB::table('sunrice_entry_term')->insert(Entry::query()->where('collection_id', $this->news->id)->pluck('id')->map(fn (int $id) => ['entry_id' => $id, 'term_id' => $term->id])->all());
    }

    expect($counts())->toBe($few)
        ->and(collect($few)->every(fn (array $queries): bool => $queries[1] === 1 && $queries[2]))->toBeTrue();
});
