<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Menus\SaveMenu;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Content;
use Sunrice\Cms\Fields\Field;
use Sunrice\Cms\Fields\HydrationContext;
use Sunrice\Cms\Fields\Schema;
use Sunrice\Cms\Menus;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Menu;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\Term;
use Sunrice\Cms\Site\EntryVisibility;
use Sunrice\Cms\Site\Page;
use Sunrice\Cms\Site\PageCache;
use Sunrice\Cms\Site\Search;
use Sunrice\Cms\Site\Sitemap;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 * Cms::hideEntriesUsing(), the edges: every place the site lists entries,
 * caches per kind of visitor, hooks together, previews and the panel.
 */

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [
        ['handle' => 'intro', 'type' => 'text'],
        ['handle' => 'related', 'type' => 'entries', 'config' => ['collections' => ['blog'], 'multiple' => true]],
        ['handle' => 'cta', 'type' => 'link'],
    ]]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'dated' => true, 'route' => '/blog/{slug}', 'listing_route' => '/blog']);
    $this->tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags', 'route' => '/tags/{slug}']);
    $this->tags->collections()->attach($this->blog);
    Collection::find($this->blog->id)?->flushSchema();
    $this->coffee = app(SaveTerm::class)->handle($this->tags, ['title' => 'Coffee']);
    $this->club = app(SaveTerm::class)->handle($this->tags, ['title' => 'Club']); // only the hidden entry has it
    $this->open = gapPost('Coffee notes', terms: ['tags' => [$this->coffee->id]]);
    $this->secret = gapPost('Members coffee club', terms: ['tags' => [$this->coffee->id, $this->club->id]]);
    app()->instance('test.member', false);
    Content::hideEntriesUsing(
        fn (Builder $query, bool $everyone) => $everyone || ! app('test.member') ? $query->where('title', 'not like', 'Members%') : null,
        fn (): string => app('test.member') ? 'member' : 'guest',
    );
});

/** @param  array<string, list<int>>|null  $terms */
function gapPost(string $title, array $data = [], ?array $terms = null, ?Collection $collection = null): EntryTranslation
{
    return app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($collection ?? test()->blog, ['title' => $title, 'data' => $data, 'terms' => $terms]));
}

/** @return list<string> */
function gapTitles(iterable $entries): array
{
    $titles = [];
    foreach ($entries as $entry) {
        $titles[] = $entry->title;
    }
    sort($titles);

    return $titles;
}

function asMember(bool $member = true): void
{
    app()->instance('test.member', $member);
    app()->forgetInstance(Menus::class);
}

it('hides them from every way of reading Cms::entries(), remembered or not', function () {
    config(['sunrice-cms.cache.enabled' => true]);

    foreach ([false, true] as $remember) {
        asMember(false);
        $query = fn () => Content::entries('blog')->remember($remember);
        expect($query()->count())->toBe(1)
            ->and(gapTitles($query()->get()))->toBe(['Coffee notes'])
            ->and($query()->latest()->first()?->title)->toBe('Coffee notes')
            ->and($query()->whereSlug('members-coffee-club')->first())->toBeNull()
            ->and(gapTitles($query()->paginate(10)))->toBe(['Coffee notes'])
            ->and($query()->paginate(10)->total())->toBe(1)
            ->and($query()->whereTerm($this->club)->count())->toBe(0)
            ->and($query()->toBase()->pluck('title')->all())->toBe(['Coffee notes']);

        asMember();
        expect($query()->count())->toBe(2)
            ->and($query()->paginate(10)->total())->toBe(2)
            ->and($query()->whereSlug('members-coffee-club')->first()?->title)->toBe('Members coffee club');
    }
    // The helper and the component read the same query.
    asMember(false);
    expect(trim(Blade::render('<x-sunrice-cms::entries collection="blog">@foreach ($component->entries as $e){{ $e->title }};@endforeach</x-sunrice-cms::entries>')))
        ->toBe('Coffee notes;');
});

it('shows hidden entries in a query\'s preview', function () {
    expect(gapTitles(Content::entries('blog')->preview()->get()))->toBe(['Coffee notes', 'Members coffee club']);
});

it('serves remembered paginated pages per kind of visitor, the member\'s never to a guest', function () {
    config(['sunrice-cms.cache.enabled' => true]);

    asMember();
    expect(Content::entries('blog')->remember()->paginate(10)->total())->toBe(2);
    asMember(false);
    expect(gapTitles(Content::entries('blog')->remember()->paginate(10)))->toBe(['Coffee notes'])
        ->and(Content::entries('blog')->remember()->latest()->first()?->title)->toBe('Coffee notes');
});

it('hides them from term pages, in term counts, and keeps them on for members', function () {
    get('/tags/coffee')->assertOk()->assertSee('Coffee notes')->assertDontSee('Members coffee club');
    get('/tags/club')->assertOk()->assertDontSee('Members coffee club');

    $counts = fn () => trim(Blade::render('<x-sunrice-cms::terms taxonomy="tags">@foreach ($component->terms as $t){{ $t->title() }}:{{ $t->entries_count }} @endforeach</x-sunrice-cms::terms>'));
    $hideEmpty = fn () => trim(Blade::render('<x-sunrice-cms::terms taxonomy="tags" hide-empty>@foreach ($component->terms as $t){{ $t->title() }} @endforeach</x-sunrice-cms::terms>'));
    expect($counts())->toBe('Coffee:1 Club:0')->and($hideEmpty())->toBe('Coffee');

    $terms = Term::query()->where('taxonomy_id', $this->tags->id)->orderBy('id')->get();
    Term::countEntries($terms, $this->tags->id, Term::liveIn('en'));
    expect($terms->pluck('entries_count')->all())->toBe([1, 0]);

    asMember();
    get('/tags/club')->assertOk()->assertSee('Members coffee club');
    expect($counts())->toBe('Coffee:2 Club:1');
});

it('hides them from a collection\'s own term pages', function () {
    get('/blog/tags/club')->assertOk()->assertDontSee('Members coffee club');
    get('/blog/tags/coffee')->assertOk()->assertSee('Coffee notes')->assertDontSee('Members coffee club');
});

it('hides them from an ordered collection\'s listing page and its later pages', function () {
    $pages = Collection::query()->create(['handle' => 'guides', 'title' => 'Guides', 'ordered' => true, 'per_page' => 1, 'route' => '/guides/{slug}', 'listing_route' => '/guides']);
    gapPost('Members guide', collection: $pages);
    gapPost('Open guide', collection: $pages);

    get('/guides')->assertOk()->assertSee('Open guide')->assertDontSee('Members guide');
    get('/guides?page=2')->assertDontSee('Members guide');
    asMember();
    get('/guides')->assertSee('Members guide');
});

it('hides them from the site\'s search page, cached per kind of visitor', function () {
    config(['sunrice-cms.cache.enabled' => true]);
    commitForFullText();

    // A member searches first: what's cached for them isn't the guest's.
    asMember();
    expect(gapTitles(app(Search::class)->entries('coffee')))->toBe(['Coffee notes', 'Members coffee club']);
    asMember(false);
    expect(gapTitles(app(Search::class)->entries('coffee')))->toBe(['Coffee notes'])
        ->and(app(Search::class)->entries('club')->total())->toBe(0);
    get('/search?q=coffee')->assertOk()->assertSee('Coffee notes')->assertDontSee('Members coffee club');
});

it('caches menus for a guest apart from a member\'s, whoever comes first', function () {
    config(['sunrice-cms.cache.enabled' => true]);
    $menu = Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    app(SaveMenu::class)->handle($menu, [
        ['entry_id' => $this->open->entry_id],
        ['entry_id' => $this->secret->entry_id, 'children' => [['url' => '/inside', 'labels' => ['en' => 'Inside']]]],
    ]);

    asMember();
    expect(array_column(app(Menus::class)->get('main'), 'label'))->toBe(['Coffee notes', 'Members coffee club']);
    asMember(false);
    $items = app(Menus::class)->get('main');
    expect(array_column($items, 'label'))->toBe(['Coffee notes'])
        // What's under a hidden link goes with it.
        ->and(json_encode($items))->not->toContain('Inside');
});

it('leaves them out of the sitemap\'s index and parts, and term pages only they have', function () {
    asMember();
    $sitemap = app(Sitemap::class);
    $all = $sitemap->index();
    for ($n = 1; ($part = $sitemap->part($n)) !== null; $n++) {
        $all .= $part;
    }
    $all .= implode('', array_column(iterator_to_array($sitemap->pages(), false), 'url'));
    expect($all)->toContain(url('/blog/coffee-notes'))->not->toContain('members-coffee-club');

    get('/sitemap.xml')->assertOk();
    expect(get('/sitemap.xml')->getContent())->not->toContain('members-coffee-club');
});

it('resolves a link field to a hidden entry as if it weren\'t published, but not in a preview', function () {
    $post = gapPost('Brewing', ['cta' => ['type' => 'entry', 'entry_id' => $this->secret->entry_id, 'label' => 'Join']]);
    $fresh = fn () => EntryTranslation::query()->findOrFail($post->id);

    expect($fresh()->values()['cta']['url'] ?? null)->toBeNull();
    $preview = $fresh()->hydrateWith(new HydrationContext('en', preview: true))->values();
    expect(gapTitles($preview['related'] ?? []))->toBe([])
        ->and($preview['cta']['url'] ?? null)->toBe(url('/blog/members-coffee-club'));
    asMember();
    expect($fresh()->values()['cta']['url'] ?? null)->toBe(url('/blog/members-coffee-club'));
});

it('shows an entries field\'s hidden entries in a preview', function () {
    $post = gapPost('Brewing', ['related' => [$this->open->entry_id, $this->secret->entry_id]]);
    $values = EntryTranslation::query()->findOrFail($post->id)->hydrateWith(new HydrationContext('en', preview: true))->values();

    expect(gapTitles($values['related']))->toBe(['Coffee notes', 'Members coffee club']);
});

it('keeps only the text of rich text links to a hidden entry', function () {
    $html = (string) Schema::make([Field::make('body', 'rich_text')])
        ->normalize(['body' => '<p><a href="/x" data-link="entry:'.$this->secret->entry_id.'">club</a></p>'])['body'];
    $shown = fn () => (string) Schema::make([Field::make('body', 'rich_text')])->hydrate(['body' => $html], new HydrationContext('en'))['body'];

    expect($shown())->toBe('<p>club</p>');
    asMember();
    expect($shown())->toContain('href="'.url('/blog/members-coffee-club').'"');
});

it('hides an entry from related fields when another of its languages passes the hook', function () {
    // The hook narrows translations; an entry's Indonesian one passing it mustn't show its English one where English pages list it.
    $id = app(CreateTranslation::class)->handle($this->secret->entry, 'id');
    app(SaveDraft::class)->handle($id, ['title' => 'Klub kopi', 'slug' => 'klub-kopi']);
    app(PublishEntry::class)->handle($id->refresh());
    $post = gapPost('Brewing', ['related' => [$this->secret->entry_id]]);

    expect(Content::entries('blog')->count())->toBe(2) // Brewing, Coffee notes
        ->and(gapTitles(EntryTranslation::query()->findOrFail($post->id)->values()['related']))->toBe([]);
});

it('hides a menu link when another of the entry\'s languages passes the hook', function () {
    $id = app(CreateTranslation::class)->handle($this->secret->entry, 'id');
    app(SaveDraft::class)->handle($id, ['title' => 'Klub kopi', 'slug' => 'klub-kopi']);
    app(PublishEntry::class)->handle($id->refresh());
    $menu = Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    app(SaveMenu::class)->handle($menu, [['entry_id' => $this->secret->entry_id]]);

    expect(array_column(app(Menus::class)->get('main'), 'label'))->toBe([]);
});

it('combines several plugins\' hooks: an entry any of them hides is hidden', function () {
    app()->instance('test.vip', false);
    Content::hideEntriesUsing(
        fn (Builder $query, bool $everyone) => $everyone || ! app('test.vip') ? $query->where('title', 'not like', '%notes%') : null,
        fn (): string => app('test.vip') ? 'vip' : 'plain',
    );
    gapPost('Plain post');

    expect(gapTitles(Content::entries('blog')->get()))->toBe(['Plain post']);
    asMember();
    expect(gapTitles(Content::entries('blog')->get()))->toBe(['Members coffee club', 'Plain post']);
    app()->instance('test.vip', true);
    expect(gapTitles(Content::entries('blog')->get()))->toBe(['Coffee notes', 'Members coffee club', 'Plain post']);
    asMember(false);
    expect(gapTitles(Content::entries('blog')->get()))->toBe(['Coffee notes', 'Plain post']);
});

it('keys caches by every hook\'s viewer, and not at all without hooks', function () {
    app()->instance('test.vip', false);
    $visibility = app(EntryVisibility::class);
    $guest = $visibility->key();
    Content::hideEntriesUsing(fn (Builder $query, bool $everyone) => null, fn (): string => app('test.vip') ? 'vip' : 'plain');
    $guestPlain = $visibility->key();
    app()->instance('test.vip', true);
    $guestVip = $visibility->key();
    asMember();

    expect($guest)->not->toBe('')
        ->and($guestPlain)->not->toBe($guest)
        ->and($guestVip)->not->toBe($guestPlain)
        ->and($visibility->key())->not->toBe($guestVip)
        ->and((new EntryVisibility)->key())->toBe('')
        ->and((new EntryVisibility)->active())->toBeFalse()
        ->and((new EntryVisibility)->visibleEntryIds([(new Entry)->forceFill(['id' => 3])->setRelation('translations', collect()), (new Entry)->forceFill(['id' => 1])->setRelation('translations', collect())], 'en'))->toBe([3, 1]);
});

it('keeps a hook that uses orWhere from showing drafts', function () {
    app()->instance(EntryVisibility::class, new EntryVisibility);
    $draft = app(CreateEntry::class)->handle($this->blog, ['title' => 'Members coffee draft']);
    // A plausible hook: what's public, or what this member was given.
    Content::hideEntriesUsing(fn (Builder $query, bool $everyone) => $query->where('title', 'not like', 'Members%')->orWhereIn('entry_id', [$this->secret->entry_id, $draft->entry_id]), fn (): string => 'x');

    expect(gapTitles(Content::entries('blog')->get()))->toBe(['Coffee notes', 'Members coffee club']);
})->todo('EntryVisibility::apply() (packages/cms/src/Site/EntryVisibility.php:55) hands each hook the site\'s own query unwrapped: an orWhere in a hook escapes live() and the language and collection constraints, listing an unpublished draft; wrapping it as $query->where(fn ($q) => $hide($q, $everyone)) would contain it');

it('still answers a hidden entry\'s own page, which the plugin gates with resolveTemplateUsing()', function () {
    // As documented: the page resolves; what it shows is the plugin's.
    get('/blog/members-coffee-club')->assertOk();
    get('/blog/no-such-entry')->assertNotFound();
});

it('does not hide entries in the panel', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view'));
    config(['inertia.testing.ensure_pages_exist' => false]);

    get('/admin/cms/collections/blog/entries')->assertOk()
        ->assertInertia(fn ($page) => $page->where('entries.data', fn ($rows) => str_contains((string) json_encode($rows), 'Members coffee club'))->etc());
});

it('exports links to hidden entries with their address from the panel', function () {
    gapPost('Brewing', ['cta' => ['type' => 'entry', 'entry_id' => $this->secret->entry_id, 'label' => 'Join']]);
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.export'));

    $csv = get('/admin/cms/collections/blog/entries/export')->assertOk()->streamedContent();
    expect(substr_count($csv, url('/blog/members-coffee-club')))->toBe(2); // its own row's URL and Brewing's link
})->todo('the panel\'s CSV export (packages/cms/src/Admin/EntryList.php:308, likewise Admin/RevisionList.php:252) hydrates link fields with a site HydrationContext, not a preview one, so the visitor hook hides the linked entry from the editor and its link exports blank (it exports when the hook lets the request see it)');

it('keeps a member\'s pages out of the page cache when the plugin skips them', function () {
    config(['sunrice-cms.cache.pages' => true, 'responsecache.debug.enabled' => true]);
    app(PageCache::class)->skipWhen(fn ($request, ?Page $page): bool => app('test.member'));

    asMember();
    get('/blog')->assertOk()->assertSee('Members coffee club')->assertHeaderMissing('X-Cache-Status');
    asMember(false);
    get('/blog')->assertOk()->assertHeader('X-Cache-Status', 'MISS')->assertDontSee('Members coffee club');
    get('/blog')->assertHeader('X-Cache-Status', 'HIT')->assertDontSee('Members coffee club');
});

it('never serves a member\'s kept page to a guest, even when the plugin forgets to skip the page cache', function () {
    config(['sunrice-cms.cache.pages' => true, 'responsecache.debug.enabled' => true]);

    asMember(); // a member the panel's and the default guard don't know (the plugin's own guard, a token)
    get('/blog')->assertOk()->assertSee('Members coffee club');
    asMember(false);
    get('/blog')->assertOk()->assertDontSee('Members coffee club');
})->todo('the page cache (PageCacheHasher, PageCache::forVisitor()) ignores EntryVisibility::key(): a member the default and panel guards don\'t know (a plugin\'s own guard, a token) gets a listing kept and served to guests. The docs leave it to the plugin (PageCache::skipWhen()), but the CMS has the viewer key to keep pages apart or skip them');
