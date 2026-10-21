<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Content;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [['handle' => 'intro', 'type' => 'text']]]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}']);
    $this->news = Collection::query()->create(['handle' => 'news', 'title' => 'News', 'blueprint' => 'post', 'route' => '/news/{slug}']);
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update'));
    $this->post = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->blog, ['title' => 'Hello', 'data' => ['intro' => 'Live words']]));
    app(SaveDraft::class)->handle($this->post->refresh(), ['title' => 'Hello again', 'data' => ['intro' => 'Draft words']]);
    Auth::logout();
});

function draftViewer(string ...$extra): void
{
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view-drafts', 'cms.entries.view', ...$extra));
}

it('shows editors a banner on live pages with newer drafts', function () {
    draftViewer();

    get('/blog/hello')->assertOk()->assertSee('Live words')->assertDontSee('Draft words')
        ->assertSee('data-sunrice-draft-banner', false)
        ->assertSee('This page has unpublished changes.')
        ->assertSee('href="'.url('/blog/hello').'?draft"', false)
        ->assertSee(url(entryEditUrl($this->post->entry_id, 'en')), false)
        ->assertHeader('Cache-Control', 'no-store, private');
    expect(Content::page()?->draft)->toBeFalse();
});

it('opens the draft with ?draft: never indexed, cached or kept', function () {
    draftViewer();

    $response = get('/blog/hello?draft')->assertOk()->assertSee('<h1>Hello again</h1>', false)->assertSee('Draft words')->assertDontSee('Live words')
        ->assertSee('You are viewing unpublished changes.')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertViewHas('draft', true);
    // The banner comes right after <body>, whatever the template.
    expect((string) $response->getContent())->toMatch('/<body[^>]*>\s*<aside aria-label="Draft" data-sunrice-draft-banner/')
        ->and(Content::page()?->draft)->toBeTrue()
        ->and(Content::bodyClass())->toContain('is-draft')->not->toContain('is-preview');
});

it('shows visitors and editors without the permission only the live page', function () {
    get('/blog/hello?draft')->assertOk()->assertSee('Live words')->assertDontSee('Draft words')->assertDontSee('data-sunrice-draft-banner', false);

    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view'));
    get('/blog/hello?draft')->assertOk()->assertSee('Live words')->assertDontSee('data-sunrice-draft-banner', false);

    // The permission, but not the collection.
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view-drafts', 'cms.entries.news.view'));
    get('/blog/hello?draft')->assertOk()->assertSee('Live words')->assertDontSee('data-sunrice-draft-banner', false);
});

it('shows no banner when the draft is what\'s live', function () {
    app(PublishEntry::class)->handle(EntryTranslation::query()->findOrFail($this->post->id));
    draftViewer();

    get('/blog/hello')->assertOk()->assertDontSee('data-sunrice-draft-banner', false);
    get('/blog/hello?draft')->assertOk()->assertViewHas('draft', false);
});

it('never keeps a draft in the page cache', function () {
    config(['sunrice-cms.cache.pages' => true, 'responsecache.enabled' => true]);
    draftViewer();
    get('/blog/hello?draft')->assertSee('Draft words');
    Auth::logout();

    get('/blog/hello?draft')->assertSee('Live words')->assertDontSee('Draft words');
});

it('lets editors and authors see drafts by default', function () {
    artisan('sunrice:roles')->assertSuccessful();

    expect(Role::findByName('editor', 'web')->hasPermissionTo('cms.entries.view-drafts'))->toBeTrue()
        ->and(Role::findByName('author', 'web')->hasPermissionTo('cms.entries.view-drafts'))->toBeTrue()
        ->and(Role::findByName('translator', 'web')->hasPermissionTo('cms.entries.view-drafts'))->toBeFalse();
});
