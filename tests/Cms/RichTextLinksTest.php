<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Entries\TrashEntry;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Actions\Terms\TrashTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Fields\Field;
use Sunrice\Cms\Fields\HydrationContext;
use Sunrice\Cms\Fields\Reference;
use Sunrice\Cms\Fields\Schema;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Support\HtmlSanitizer;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

/*
 * Rich text links keep the editor's rel choices, and links to entries,
 * terms and listing pages (data-link) get their current URL when pages
 * show them, or only their text once that's gone.
 */

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['sunrice-cms.locales' => ['en', 'id']]);
    actingAs(adminUser());
    $this->pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'route' => '/{slug}']);
    $this->news = Collection::query()->create(['handle' => 'news', 'title' => 'News', 'route' => '/news/{slug}', 'listing_route' => '/news']);
    $this->topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'route' => '/topics/{slug}']);
});

function richLinkEntry(Collection $collection, string $title, bool $publish = true): EntryTranslation
{
    $translation = app(CreateEntry::class)->handle($collection, ['title' => $title]);

    return $publish ? app(PublishEntry::class)->handle($translation) : $translation;
}

function richLinkStored(string $html): string
{
    return (string) Schema::make([Field::make('body', 'rich_text')])->normalize(['body' => $html])['body'];
}

function richLinkShown(string $stored, string $locale = 'en', ?HydrationContext $context = null): string
{
    return (string) Schema::make([Field::make('body', 'rich_text')])->hydrate(['body' => $stored], $context ?? new HydrationContext($locale))['body'];
}

it('keeps the editor\'s rel choices and new tab, always with noopener noreferrer', function () {
    expect(richLinkStored('<p><a href="/about" rel="nofollow" target="_blank">About</a></p>'))
        ->toContain('rel="nofollow noopener noreferrer"')->toContain('target="_blank"')
        ->and(richLinkStored('<p><a href="https://example.com" rel="ugc opener evil sponsored nofollow nofollow">x</a></p>'))
        ->toContain('rel="nofollow sponsored ugc noopener noreferrer"')->not->toContain('evil')->not->toContain(' opener')
        ->and(richLinkStored('<p><a href="/a">no rel</a></p>'))->toContain('<a href="/a" rel="noopener noreferrer">')
        ->and(HtmlSanitizer::sanitize('<p><a href="/a" rel="">empty</a></p>'))->toContain('rel="noopener noreferrer"');
});

it('keeps data-link only when it names a kind and an id', function () {
    expect(richLinkStored('<p><a href="/about" data-link="entry:12">About</a></p>'))->toContain('data-link="entry:12"')
        ->and(richLinkStored('<p><a href="/about" data-link="entry:12 onclick">x</a><a href="/b" data-link="javascript:alert(1)">y</a><a href="/c" data-link="Entry:x">z</a></p>'))
        ->not->toContain('data-link');
});

it('links an entry at its current address in the page\'s language, never printing data-link', function () {
    $about = richLinkEntry($this->pages, 'About');
    $tentang = app(CreateTranslation::class)->handle($about->entry, 'id');
    app(SaveDraft::class)->handle($tentang, ['title' => 'Tentang', 'slug' => 'tentang']);
    app(PublishEntry::class)->handle($tentang);
    $stored = richLinkStored('<p>See <a href="/old-about" rel="nofollow" target="_blank" data-link="entry:'.$about->entry_id.'">about us</a>.</p>');

    expect(richLinkShown($stored))->toBe('<p>See <a href="'.url('/about').'" rel="nofollow noopener noreferrer" target="_blank">about us</a>.</p>')
        ->and(richLinkShown($stored, 'id'))->toContain('href="'.url('/id/tentang').'"');

    app(SaveDraft::class)->handle($about->refresh(), ['title' => 'About', 'slug' => 'about-us']);
    app(PublishEntry::class)->handle($about->refresh());
    expect(richLinkShown($stored))->toContain('href="'.url('/about-us').'"');
});

it('links an untranslated entry at the page\'s language\'s address, or the main one without the fallback', function () {
    $story = richLinkEntry($this->news, 'Story');
    $stored = richLinkStored('<p><a href="/news/story" data-link="entry:'.$story->entry_id.'">Story</a></p>');

    expect(richLinkShown($stored, 'id'))->toContain('href="'.url('/id/news/story').'"');
    config(['sunrice-cms.site.fallback' => false]);
    expect(richLinkShown($stored, 'id'))->toContain('href="'.url('/news/story').'"');
});

it('keeps only the text of links to drafts, trashed and missing entries', function () {
    $draft = richLinkEntry($this->pages, 'Soon', publish: false);
    $trashed = richLinkEntry($this->pages, 'Old');
    app(TrashEntry::class)->handle($trashed->entry);
    $stored = richLinkStored('<p><a href="/soon" data-link="entry:'.$draft->entry_id.'">soon</a> <a href="/old" data-link="entry:'.$trashed->entry_id.'"><strong>old</strong></a> <a href="/x" data-link="entry:999999">gone</a></p>');

    expect(richLinkShown($stored))->toBe('<p>soon <strong>old</strong> gone</p>')
        // A preview shows drafts, but there's no page to link to yet.
        ->and(richLinkShown($stored, context: new HydrationContext('en', preview: true)))->not->toContain('<a ');
});

it('links terms and listing pages, and drops the link once they are gone', function () {
    $term = app(SaveTerm::class)->handle($this->topics, ['title' => 'Coffee']);
    $stored = richLinkStored('<p><a href="/t" data-link="term:'.$term->id.'">coffee</a> and <a href="/n" data-link="listing:'.$this->news->id.'">the news</a></p>');

    expect(richLinkShown($stored))->toBe('<p><a href="'.url('/topics/coffee').'" rel="noopener noreferrer">coffee</a> and <a href="'.url('/news').'" rel="noopener noreferrer">the news</a></p>')
        ->and(richLinkShown($stored, 'id'))->toContain('href="'.url('/id/news').'"');

    app(TrashTerm::class)->handle($term);
    $this->news->delete();
    expect(richLinkShown($stored))->toBe('<p>coffee and the news</p>');
});

it('leaves links without data-link and other plugins\' kinds as stored', function () {
    $stored = richLinkStored('<p><a href="https://example.com" rel="nofollow">ext</a> <a href="/p/4" data-link="product:4">product</a></p>');

    expect(richLinkShown($stored))->toBe($stored);
});

it('reads every link\'s target at once, one query per kind', function () {
    $entries = collect(['A', 'B', 'C', 'D'])->map(fn (string $title) => richLinkEntry($this->pages, $title));
    $terms = collect(['One', 'Two'])->map(fn (string $title) => app(SaveTerm::class)->handle($this->topics, ['title' => $title]));
    $html = $entries->map(fn ($t) => '<a href="/x" data-link="entry:'.$t->entry_id.'">'.$t->title.'</a>')->implode(' ')
        .$terms->map(fn ($t) => '<a href="/x" data-link="term:'.$t->id.'">t</a>')->implode(' ');
    $stored = richLinkStored('<p>'.$html.'</p>');
    $one = richLinkStored('<p><a href="/x" data-link="entry:'.$entries[0]->entry_id.'">A</a><a href="/x" data-link="term:'.$terms[0]->id.'">t</a></p>');
    richLinkShown($one);

    $count = function (string $html): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        richLinkShown($html);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    expect($count($stored))->toBe($count($one))
        ->and(substr_count(richLinkShown($stored), '<a href="http'))->toBe(6);
});

it('counts linked entries and terms as references', function () {
    $stored = richLinkStored('<p><a href="/a" data-link="entry:7">a</a><a href="/t" data-link="term:3">t</a><a href="/n" data-link="listing:2">n</a></p>');

    expect(Schema::make([Field::make('body', 'rich_text')])->references(['body' => $stored]))
        ->toEqual([new Reference('entry', 7, 'body'), new Reference('term', 3, 'body')]);
});

it('searches entries, terms and listing pages for the link menu, the user\'s to view only', function () {
    $about = richLinkEntry($this->pages, 'About us');
    richLinkEntry($this->news, 'About the news', publish: false);
    $term = app(SaveTerm::class)->handle($this->topics, ['title' => 'About coffee']);
    $tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    app(SaveTerm::class)->handle($tags, ['title' => 'About nothing']);
    actingAs(adminUser('sunrice.access', 'cms.entries.view', 'cms.terms.view'));

    $groups = getJson('/admin/rich-text-links?q=about')->assertOk()->json('groups');

    expect(array_column($groups, 'label'))->toBe(['Entry', 'Term'])
        ->and(collect($groups[0]['items'])->pluck('title')->sort()->values()->all())->toBe(['About the news', 'About us'])
        ->and(collect($groups[0]['items'])->firstWhere('title', 'About us'))->toBe(['link' => 'entry:'.$about->entry_id, 'id' => $about->entry_id, 'title' => 'About us', 'hint' => 'Pages', 'url' => '/about-us'])
        ->and(collect($groups[0]['items'])->firstWhere('title', 'About the news')['hint'])->toBe('News · Draft')
        // Tags have no term pages: nothing to link to.
        ->and($groups[1]['items'])->toBe([['link' => 'term:'.$term->id, 'id' => $term->id, 'title' => 'About coffee', 'hint' => 'Topics', 'url' => '/topics/about-coffee']])
        ->and(getJson('/admin/rich-text-links?q=new&source=listing')->json('groups'))->toBe([
            ['key' => 'listing', 'label' => 'Listing', 'items' => [['link' => 'listing:'.$this->news->id, 'id' => $this->news->id, 'title' => 'News', 'hint' => 'Listing page', 'url' => '/news']]],
        ])
        ->and(getJson('/admin/rich-text-links/entry:'.$about->entry_id)->json('item'))->toMatchArray(['title' => 'About us', 'source' => 'Entry', 'url' => '/about-us']);

    // Only the collections they may view.
    actingAs(adminUser('sunrice.access', 'cms.entries.news.view'));
    expect(collect(getJson('/admin/rich-text-links?q=about')->json('groups'))->pluck('items')->flatten(1)->pluck('title')->all())->toBe(['About the news'])
        ->and(getJson('/admin/rich-text-links/entry:'.$about->entry_id)->json('item'))->toBeNull();

    actingAs(adminUser('sunrice.access'));
    expect(getJson('/admin/rich-text-links?q=about')->json('groups'))->toBe([]);
});

it('guards the link menu\'s search', function () {
    auth()->logout();
    get('/admin/rich-text-links?q=about')->assertRedirect();

    actingAs(adminUser('cms.entries.view'));
    getJson('/admin/rich-text-links?q=about')->assertForbidden();
    getJson('/admin/rich-text-links/entry:1')->assertForbidden();
});
