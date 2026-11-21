<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Menus\SaveMenu;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Content;
use Sunrice\Cms\Menus;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Menu;
use Sunrice\Cms\Site\Search;
use Sunrice\Cms\Site\Sitemap;
use Sunrice\Cms\Support\SiteCache;

use function Pest\Laravel\get;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [
        ['handle' => 'intro', 'type' => 'text'],
        ['handle' => 'related', 'type' => 'entries', 'config' => ['collections' => ['blog'], 'multiple' => true]],
    ]]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'dated' => true, 'route' => '/blog/{slug}', 'listing_route' => '/blog']);
    $this->open = visibilityPost('Coffee notes');
    $this->secret = visibilityPost('Members coffee club');
    // Who's looking, as a plugin would know it: here, a flag.
    app()->instance('test.member', false);
    Content::hideEntriesUsing(
        fn (Builder $query, bool $everyone) => $everyone || ! app('test.member') ? $query->where('title', 'not like', 'Members%') : null,
        fn (): string => app('test.member') ? 'member' : 'guest',
    );
});

function visibilityPost(string $title, array $data = []): EntryTranslation
{
    return app(PublishEntry::class)->handle(app(CreateEntry::class)->handle(test()->blog, ['title' => $title, 'data' => $data]));
}

/** @return list<string> */
function visibleTitles(iterable $entries): array
{
    $titles = [];
    foreach ($entries as $entry) {
        $titles[] = $entry->title;
    }
    sort($titles);

    return $titles;
}

it('hides entries from Cms::entries() for who may not see them, cached per kind of visitor', function () {
    config(['sunrice-cms.cache.enabled' => true]);
    expect(SiteCache::enabled())->toBeTrue();

    expect(visibleTitles(Content::entries('blog')->remember()->get()))->toBe(['Coffee notes']);
    app()->instance('test.member', true);
    expect(visibleTitles(Content::entries('blog')->remember()->get()))->toBe(['Coffee notes', 'Members coffee club'])
        ->and(Content::entries('blog')->remember()->count())->toBe(2);
    app()->instance('test.member', false);
    expect(Content::entries('blog')->remember()->count())->toBe(1);
});

it('hides them from listing pages and search, not their own page', function () {
    get('/blog')->assertOk()->assertSee('Coffee notes')->assertDontSee('Members coffee club');
    expect(visibleTitles(app(Search::class)->entries('coffee')))->toBe(['Coffee notes']);
    // Gating the page itself is the plugin's (Cms::resolveTemplateUsing()).
    get('/blog/members-coffee-club')->assertOk();

    app()->instance('test.member', true);
    get('/blog')->assertOk()->assertSee('Members coffee club');
    expect(visibleTitles(app(Search::class)->entries('coffee')))->toBe(['Coffee notes', 'Members coffee club']);
});

it('leaves them out of the sitemap whoever asks', function () {
    app()->instance('test.member', true);

    $urls = array_column(iterator_to_array(app(Sitemap::class)->pages(), false), 'url');
    expect($urls)->toContain(url('/blog/coffee-notes'))->not->toContain(url('/blog/members-coffee-club'));
});

it('leaves links to them out of menus, per kind of visitor', function () {
    config(['sunrice-cms.cache.enabled' => true]);
    $menu = Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    app(SaveMenu::class)->handle($menu, [['entry_id' => $this->open->entry_id], ['entry_id' => $this->secret->entry_id]]);

    expect(array_column(app(Menus::class)->get('main'), 'label'))->toBe(['Coffee notes']);
    app()->instance('test.member', true);
    app()->forgetInstance(Menus::class);
    expect(array_column(app(Menus::class)->get('main'), 'label'))->toBe(['Coffee notes', 'Members coffee club']);
});

it('resolves an entries field\'s hidden entries as missing', function () {
    $post = visibilityPost('Brewing', ['related' => [$this->open->entry_id, $this->secret->entry_id]]);

    expect(visibleTitles(EntryTranslation::query()->findOrFail($post->id)->values()['related']))->toBe(['Coffee notes']);
    app()->instance('test.member', true);
    expect(visibleTitles(EntryTranslation::query()->findOrFail($post->id)->values()['related']))->toBe(['Coffee notes', 'Members coffee club']);
});
