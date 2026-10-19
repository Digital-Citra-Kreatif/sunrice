<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Site\Search;
use Sunrice\Cms\Support\SearchText;

use function Pest\Laravel\get;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['sunrice-cms.locales' => ['en', 'id']]);
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [
        ['handle' => 'intro', 'type' => 'text'],
        ['handle' => 'body', 'type' => 'rich_text'],
        ['handle' => 'link', 'type' => 'text'],
    ]]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}']);
    $this->help = Collection::query()->create(['handle' => 'help', 'title' => 'Help', 'blueprint' => 'post', 'route' => '/help/{slug}']);
    $this->snippets = Collection::query()->create(['handle' => 'snippets', 'title' => 'Snippets', 'blueprint' => 'post']); // no pages
});

function found(Collection $collection, string $title, array $data = [], bool $publish = true): EntryTranslation
{
    $translation = app(CreateEntry::class)->handle($collection, ['title' => $title, 'data' => $data]);

    return $publish ? app(PublishEntry::class)->handle($translation) : $translation;
}

function titles($results): array
{
    return collect($results->items())->pluck('title')->all();
}

it('keeps the words of the live version for search', function () {
    expect(SearchText::of([
        'intro' => 'Fresh  coffee',
        'body' => '<p>Roasted <strong>daily</strong></p><p>in&nbsp;Bandung</p>',
        'rows' => [['_id' => 'abc123', 'text' => 'Beans']],
        'link' => 'https://example.com/x',
        'seo' => ['title' => 'Not this'],
        'count' => 3,
    ]))->toBe('Fresh coffee Roasted daily in Bandung Beans')
        ->and(SearchText::of([]))->toBeNull();

    $draft = found($this->blog, 'Hello', ['intro' => 'Coffee'], publish: false);
    expect($draft->fresh()?->search_text)->toBeNull(); // drafts aren't searched
    app(PublishEntry::class)->handle($draft);
    expect($draft->fresh()?->search_text)->toBe('Coffee');
});

it('finds live entries with pages by title and field text, titles first', function () {
    found($this->blog, 'All about tea', ['intro' => 'Leaves and coffee']);
    found($this->blog, 'Coffee guide', ['body' => '<p>Beans</p>']);
    found($this->help, 'Brewing', ['body' => '<p>Use <em>COFFEE</em> grounds</p>']);
    found($this->blog, 'Coffee draft', publish: false);
    found($this->snippets, 'Coffee snippet');
    found($this->blog, '100% arabica', ['intro' => 'pure_beans']);

    $search = app(Search::class);
    expect(titles($search->entries('coffee')))->toBe(['Coffee guide', 'Brewing', 'All about tea'])
        ->and(titles($search->entries('coffee', ['help'])))->toBe(['Brewing'])
        ->and(titles($search->entries('100%')))->toBe(['100% arabica'])
        ->and(titles($search->entries('%')))->toBe(['100% arabica']) // typed literally, not a wildcard
        ->and(titles($search->entries('e_b')))->toBe(['100% arabica'])
        ->and(titles($search->entries('  ')))->toBe([])
        ->and($search->entries('coffee', perPage: 1)->total())->toBe(3);
});

it('searches in the page\'s language', function () {
    $post = found($this->blog, 'Coffee');
    $id = app(CreateTranslation::class)->handle($post->entry, 'id');
    app(SaveDraft::class)->handle($id, ['title' => 'Kopi', 'slug' => 'kopi', 'data' => ['intro' => 'Kopi segar']]);
    app(PublishEntry::class)->handle($id);

    expect(titles(app(Search::class)->entries('kopi')))->toBe([])
        ->and(titles(app(Search::class)->entries('kopi', locale: 'id')))->toBe(['Kopi']);
    get('/id/search?q=segar')->assertOk()->assertSee('<a href="'.url('/id/blog/kopi').'">Kopi</a>', false)
        ->assertSee('<html lang="id">', false);
});

it('shows the search page, never indexed, and limits searches', function () {
    found($this->blog, 'Coffee guide');
    config(['sunrice-cms.search.per_minute' => 5, 'sunrice-cms.cache.pages' => true, 'responsecache.debug.enabled' => true]);

    get('/search')->assertOk()->assertSee('<title>Search | Laravel</title>', false)->assertDontSee('results for');
    get('/search?q=coffee')->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, follow')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertSee('1 result for “coffee”', false)
        ->assertSee('<a href="'.url('/blog/coffee-guide').'">Coffee guide</a>', false)
        ->assertSee('value="coffee"', false)
        ->assertSee('class="page-search', false);
    get('/search?q=<script>')->assertOk()->assertDontSee('<script>', false);
    get('/search?q=coffee&page=5')->assertNotFound();
    get('/search?q=coffee')->assertOk()->assertHeader('X-Cache-Status', 'MISS'); // the second time: not kept
    get('/search?q=x')->assertStatus(429);
});

it('gives templates a search box and results of their own', function () {
    found($this->blog, 'Coffee guide');
    found($this->help, 'Coffee help');
    $this->app->instance('request', Request::create('/somewhere', 'GET', ['q' => 'coffee']));

    $box = Blade::render('<x-sunrice-cms::search>{{ $component->action }}|{{ $component->name }}|{{ $component->query }}|{{ var_export($component->results, true) }}</x-sunrice-cms::search>');
    $results = Blade::render('<x-sunrice-cms::search results collection="help">@foreach ($component->results as $r){{ $r->title }}@endforeach</x-sunrice-cms::search>');

    expect(trim($box))->toBe(url('/search').'|q|coffee|NULL')
        ->and(trim($results))->toBe('Coffee help');
});
