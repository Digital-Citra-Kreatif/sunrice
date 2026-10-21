<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Site\PageCache;
use Sunrice\Cms\View\Components\EntryFilter;

use function Pest\Laravel\get;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['sunrice-cms.locales' => ['en']]);
    Blueprint::query()->create(['handle' => 'product', 'title' => 'Product', 'fields' => [
        ['handle' => 'price', 'type' => 'number'],
        ['handle' => 'color', 'type' => 'select', 'config' => ['options' => [['value' => 'red', 'label' => 'Red'], ['value' => 'blue', 'label' => 'Blue'], ['value' => 'green', 'label' => 'Green']]]],
        ['handle' => 'features', 'type' => 'select', 'config' => ['multiple' => true, 'options' => [['value' => 'wifi', 'label' => 'Wi-Fi'], ['value' => 'parking', 'label' => 'Parking']]]],
        ['handle' => 'sale', 'type' => 'toggle'],
        ['handle' => 'made', 'type' => 'date'],
        ['handle' => 'topics', 'type' => 'terms', 'config' => ['taxonomy' => 'topics', 'multiple' => true]],
    ]]);
    $this->shop = Collection::query()->create(['handle' => 'shop', 'title' => 'Shop', 'blueprint' => 'product', 'route' => '/shop/{slug}', 'per_page' => 10]);
    $topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'hierarchical' => true]);
    $topics->collections()->attach($this->shop);
    $this->home = app(SaveTerm::class)->handle($topics, ['title' => 'Home']);
    $this->kitchen = app(SaveTerm::class)->handle($topics, ['title' => 'Kitchen', 'parent_id' => $this->home->id]);
    $this->garden = app(SaveTerm::class)->handle($topics, ['title' => 'Garden']);

    $product = fn (string $title, array $data) => app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->shop, ['title' => $title, 'data' => $data]));
    $product('Red kettle', ['price' => 30, 'color' => 'red', 'features' => ['wifi'], 'sale' => true, 'made' => '2026-01-10', 'topics' => [$this->kitchen->id]]);
    $product('Blue lamp', ['price' => 120, 'color' => 'blue', 'features' => ['wifi', 'parking'], 'made' => '2026-03-05', 'topics' => [$this->home->id]]);
    $product('Green hose', ['price' => 15, 'color' => 'green', 'features' => [], 'sale' => true, 'made' => '2026-06-01', 'topics' => [$this->garden->id]]);

    $filters = "['q' => 'search', 'topic' => ['type' => 'terms', 'taxonomy' => 'topics'], 'color' => ['type' => 'select', 'multiple' => true], 'feature' => ['type' => 'select', 'field' => 'features'], 'price' => 'range', 'made' => ['type' => 'date_range', 'label' => 'Made'], 'sale' => ['type' => 'toggle', 'label' => 'On sale']]";
    $sorts = "['name' => 'alphabetical', 'cheap' => ['label' => 'Price, low to high', 'order' => 'price'], 'dear' => '-price']";
    Route::get('/catalogue', fn () => Blade::render('<x-sunrice-cms::entry-filter collection="shop" :filters="'.$filters.'" :sorts="'.$sorts.'" />'));
    Route::get('/catalogue-slot', fn () => Blade::render('<x-sunrice-cms::entry-filter collection="shop" :filters="'.$filters.'" :sorts="'.$sorts.'" :per-page="1" page-name="p">'
        .'@foreach ($component->entries as $e){{ $e->title }};@endforeach|{{ $component->entries->total() }}|{{ $component->filtered() ? "filtered" : "all" }}|'
        .'@foreach ($component->active as $a){{ $a->label }}={{ $a->value }}>{{ $a->removeUrl }} @endforeach|{{ $component->clearUrl() }}|{{ $component->query()->count() }}|{{ $component->filter("price")?->inputs["min"] }}'
        .'</x-sunrice-cms::entry-filter>'));
});

function catalogue(string $query = ''): string
{
    $html = (string) get('/catalogue-slot'.($query !== '' ? '?'.$query : ''))->assertOk()->getContent();

    return explode('|', $html)[0];
}

it('filters by the address: search, terms with their children, selects, ranges, dates and toggles', function () {
    expect(catalogue())->toBe('Blue lamp;')
        ->and(catalogue('q=kettle'))->toBe('Red kettle;')
        ->and(catalogue('topic[]=home&sort=cheap'))->toBe('Red kettle;')
        ->and(catalogue('topic[]=home&sort=dear'))->toBe('Blue lamp;')
        ->and(catalogue('color[]=green&color[]=red&sort=dear'))->toBe('Red kettle;')
        ->and(catalogue('feature=parking'))->toBe('Blue lamp;')
        ->and(catalogue('price_min=20&price_max=100'))->toBe('Red kettle;')
        ->and(catalogue('made_from=2026-05-01'))->toBe('Green hose;')
        ->and(catalogue('made_to=2026-01-10&sort=cheap'))->toBe('Red kettle;')
        ->and(catalogue('sale=1&sort=cheap'))->toBe('Green hose;')
        ->and(catalogue('sale=1&sort=cheap&p=2'))->toBe('Red kettle;');

    // Values outside a filter's options, bad numbers and dates, and undeclared parameters are ignored.
    $all = explode('|', (string) get('/catalogue-slot?color[]=purple&price_min=abc&made_from=yesterday&topic[]=nope&other=1')->getContent());
    expect($all[1])->toBe('3')->and($all[2])->toBe('all');
});

it('lists the applied filters with links that remove each, and one that clears them', function () {
    $parts = explode('|', (string) get('/catalogue-slot?color[]=red&color[]=blue&price_max=200&q=e&keep=1&p=2')->getContent());

    expect($parts[2])->toBe('filtered')
        ->and($parts[3])->toContain('Q=e>'.url('/catalogue-slot').'?color%5B0%5D=red&amp;color%5B1%5D=blue&amp;price_max=200&amp;keep=1')
        ->toContain('Color=Red>'.url('/catalogue-slot').'?color%5B0%5D=blue&amp;price_max=200&amp;q=e&amp;keep=1')
        ->toContain('Price=max 200>')
        ->and($parts[4])->toBe(url('/catalogue-slot').'?keep=1')
        ->and($parts[5])->toBe('2')
        ->and(trim($parts[6]))->toBe('price_min');
});

it('prints an accessible form, the applied filters and the results without a slot', function () {
    $html = (string) get('/catalogue?topic[]=home&color[]=red&sort=cheap')->assertOk()->getContent();

    expect($html)
        ->toContain('<form method="GET" action="'.url('/catalogue').'" role="search"')
        ->toContain('<label for="filter-q">Q</label>')
        ->toContain('<legend>Topic</legend>')
        ->toContain('name="topic[]" value="home" checked')
        ->toContain('name="topic[]" value="kitchen"')
        ->toContain('name="color[]" value="red" checked')
        ->toContain('<option value="parking"')
        ->toContain('<label for="filter-price-min">Min</label>')
        ->toContain('type="date"')
        ->toContain('<label for="filter-sale">On sale</label>')
        ->toContain('<option value="cheap" selected>Price, low to high</option>')
        ->toContain('aria-label="Applied filters"')
        ->toContain('Remove filter Topic: Home')
        ->toContain('role="status"')
        ->toContain('1 result')
        ->toContain('<a href="'.url('/shop/red-kettle').'">Red kettle</a>')
        ->not->toContain('Blue lamp</a>');
});

it('refuses unknown collections, filter types and names', function () {
    expect(fn () => new EntryFilter('nope'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new EntryFilter('shop', ['x' => 'magic']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new EntryFilter('shop', ['Bad Name' => 'search']))->toThrow(InvalidArgumentException::class);

    // Without sorts, the collection's own order; a title ascending one here.
    expect(collect((new EntryFilter('shop'))->entries->items())->pluck('title')->all())->toBe(['Blue lamp', 'Green hose', 'Red kettle']);
});

it('says a page given values it ignores isn\'t to be kept, so made-up values can\'t fill the page cache', function () {
    // Unlisted, a filter's parameters never key a kept page: the filtered page isn't kept at all.
    get('/catalogue?sale=1')->assertOk();
    expect(app(PageCache::class)->keeps(request(), null))->toBeFalse();
    config(['sunrice-cms.cache.query' => ['color', 'sale', 'sort', 'topic', 'price_min', 'made_from']]);
    $kept = function (string $query): bool {
        get('/catalogue?'.$query)->assertOk();

        return app(PageCache::class)->keeps(request(), null);
    };

    expect($kept('color[]=red&sale=1&sort=cheap&topic[]=home'))->toBeTrue()
        ->and($kept('other=1'))->toBeTrue()
        ->and($kept('sale=0'))->toBeTrue()
        // Any number, date or text: as many copies as values.
        ->and($kept('price_min=20'))->toBeFalse()
        ->and($kept('made_from=2026-05-01'))->toBeFalse()
        ->and($kept('q=kettle'))->toBeFalse()
        ->and($kept('color[]=purple'))->toBeFalse()
        ->and($kept('color[]=red&color[]=purple'))->toBeFalse()
        ->and($kept('price_min=abc'))->toBeFalse()
        ->and($kept('made_from=yesterday'))->toBeFalse()
        ->and($kept('sale=maybe'))->toBeFalse()
        ->and($kept('topic[]=nope'))->toBeFalse()
        ->and($kept('sort=random'))->toBeFalse();
});

it('keeps parents in a hierarchical terms filter with hide_empty, counting the entries under them', function () {
    // Kitchen and Garden have entries; Home only through Kitchen once the lamp is moved off it.
    $topics = Taxonomy::query()->where('handle', 'topics')->first();
    $outdoors = app(SaveTerm::class)->handle($topics, ['title' => 'Outdoors']);
    $empty = app(SaveTerm::class)->handle($topics, ['title' => 'Empty', 'parent_id' => $outdoors->id]);
    DB::table('sunrice_entry_term')->where('term_id', $this->home->id)->update(['term_id' => $this->kitchen->id]);

    $filter = new EntryFilter('shop', ['topic' => ['type' => 'terms', 'taxonomy' => 'topics', 'hide_empty' => true]]);
    $options = collect($filter->filter('topic')->options)->mapWithKeys(fn ($o) => [$o['value'] => [$o['count'], $o['depth']]])->all();
    expect($options)->toBe(['home' => [2, 0], 'kitchen' => [2, 1], 'garden' => [1, 0]]);

    request()->query->set('topic', ['home']);
    $filtered = new EntryFilter('shop', ['topic' => ['type' => 'terms', 'taxonomy' => 'topics', 'hide_empty' => true]]);
    expect($filtered->filtered())->toBeTrue()
        ->and($filtered->entries->total())->toBe(2);
    expect($empty->exists)->toBeTrue();
});

it('keeps the ancestors of terms with entries in a tree with hide-empty', function () {
    $html = Blade::render('<x-sunrice-cms::terms taxonomy="topics" hide-empty tree>@foreach ($component->terms as $t){{ $t->title() }}({{ $t->entries_count }})[@foreach ($t->children as $c){{ $c->title() }}({{ $c->entries_count }})@endforeach]@endforeach</x-sunrice-cms::terms>');
    DB::table('sunrice_entry_term')->where('term_id', $this->home->id)->delete();
    $after = Blade::render('<x-sunrice-cms::terms taxonomy="topics" hide-empty tree>@foreach ($component->terms as $t){{ $t->title() }}({{ $t->entries_count }})[@foreach ($t->children as $c){{ $c->title() }}@endforeach]@endforeach</x-sunrice-cms::terms>');
    $counted = Blade::render('<x-sunrice-cms::terms taxonomy="topics" hide-empty tree include-children>@foreach ($component->terms as $t){{ $t->title() }}({{ $t->entries_count }})@endforeach</x-sunrice-cms::terms>');

    expect($html)->toBe('Home(1)[Kitchen(1)]Garden(1)[]')
        ->and($after)->toBe('Home(0)[Kitchen]Garden(1)[]')
        ->and($counted)->toBe('Home(1)Garden(1)');
});

it('takes a term by its slug in any language, or its id, in every language', function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    $topics = Taxonomy::query()->where('handle', 'topics')->firstOrFail();
    app(SaveTerm::class)->handle($topics, ['title' => 'Kebun', 'locale' => 'id'], $this->garden);
    app()->setLocale('id');

    $page = fn (string $query) => (string) get('/catalogue-slot?'.$query)->assertOk()->getContent();
    // The page's own slug, the main language's or the id: the same term, shown in the page's language.
    foreach (['topic[]=kebun', 'topic[]=garden', 'topic[]='.$this->garden->id] as $query) {
        expect(explode('|', $page($query))[0])->toBe('Green hose;')
            ->and($page($query))->toContain('Topic=Kebun>');
    }
    app()->setLocale('en');
    expect(explode('|', $page('topic[]=kebun'))[0])->toBe('Green hose;');
});
