<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Collections\SaveListing;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\ForceDeleteEntry;
use Sunrice\Cms\Actions\Entries\MoveEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveEntrySettings;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Content;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Settings\SiteSettings;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => []]);
    $this->pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page', 'route' => '/{slug}', 'hierarchical' => true, 'ordered' => true]);
});

function structurePage(string $title): Entry
{
    return app(PublishEntry::class)->handle(app(CreateEntry::class)->handle(test()->pages, ['title' => $title]))->entry;
}

it('nests entries under parents, never in a loop', function () {
    [$about, $team, $leaders] = [structurePage('About'), structurePage('Team'), structurePage('Leaders')];
    $settings = app(SaveEntrySettings::class);
    $settings->handle($team, ['parent_id' => $about->id]);
    $settings->handle($leaders, ['parent_id' => $team->id]);

    expect(array_map(fn (Entry $e) => $e->id, $leaders->fresh()?->ancestors() ?? []))->toBe([$about->id, $team->id])
        ->and($about->children()->pluck('id')->all())->toBe([$team->id]);

    expect(fn () => $settings->handle($about->fresh() ?? $about, ['parent_id' => $leaders->id]))->toThrow(ValidationException::class, 'under itself');
    expect(fn () => $settings->handle($about, ['parent_id' => $about->id]))->toThrow(ValidationException::class, 'under itself');

    $articles = Collection::query()->create(['handle' => 'articles', 'title' => 'Articles', 'blueprint' => 'page']);
    $post = app(CreateEntry::class)->handle($articles, ['title' => 'Post'])->entry;
    expect(fn () => $settings->handle($post, ['parent_id' => $about->id]))->toThrow(ValidationException::class, 'Hierarchical');
    expect(fn () => $settings->handle($team, ['parent_id' => $post->id]))->toThrow(ValidationException::class, 'same collection');

    $settings->handle($team->fresh() ?? $team, ['parent_id' => null]);
    expect($team->fresh()?->parent_id)->toBeNull();
});

it('keeps entries in the order editors put them in', function () {
    [$a, $b, $c] = [structurePage('A'), structurePage('B'), structurePage('C')];
    app(MoveEntry::class)->handle($c, 'up');
    app(MoveEntry::class)->handle($a, 'down');

    expect(Content::entries('pages')->ordered()->get()->pluck('title')->all())->toBe(['C', 'A', 'B'])
        ->and(Content::entries('pages')->ordered()->childrenOf(null)->count())->toBe(3);

    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update'));
    get('/admin/cms/collections/pages/entries')->assertInertia(fn (Assert $page) => $page
        ->where('entries.data.0.title', 'C')->where('collection.ordered', true));
    post("/admin/cms/entries/{$b->id}/move", ['direction' => 'up']);
    expect(Content::entries('pages')->ordered()->get()->pluck('title')->all())->toBe(['C', 'B', 'A']);

    $this->pages->update(['ordered' => false]);
    expect(fn () => app(MoveEntry::class)->handle($a->fresh() ?? $a, 'up'))->toThrow(ValidationException::class, 'Manual order');
});

it('saves the parent and template from the editor, and renders the entry\'s own template', function () {
    [$about, $team] = [structurePage('About'), structurePage('Team')];
    View::addLocation(__DIR__.'/Fixtures/views');
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update'));

    get(entryEditUrl($team->id))->assertInertia(fn (Assert $page) => $page->where('settings', ['parentId' => null, 'template' => null, 'commentsClosed' => false, 'blueprint' => 'page']));
    put("/admin/cms/entries/{$team->id}/settings", ['parent_id' => $about->id, 'template' => 'Bad Name!'])->assertSessionHasErrors('template');
    put("/admin/cms/entries/{$team->id}/settings", ['parent_id' => $about->id, 'template' => 'cms.special'])->assertSessionHasNoErrors();

    get('/admin/cms/collections/pages/entries')->assertInertia(fn (Assert $page) => $page->where('entries.data.1.parent', 'About'));
    get('/team')->assertOk()->assertSee('A special page: Team');
    expect(Content::entries('pages')->childrenOf($about)->get()->pluck('title')->all())->toBe(['Team']);
});

it('moves children up when their parent is permanently deleted', function () {
    [$about, $team] = [structurePage('About'), structurePage('Team')];
    app(SaveEntrySettings::class)->handle($team, ['parent_id' => $about->id]);
    $about->delete();
    app(ForceDeleteEntry::class)->handle($about);

    expect($team->fresh()?->parent_id)->toBeNull()->and(EntryTranslation::query()->count())->toBe(1);
});

it('serves listing pages with their fields, paginated, in each language', function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    Blueprint::query()->create(['handle' => 'listing', 'title' => 'Listing', 'fields' => [['handle' => 'intro', 'type' => 'text']]]);
    $articles = Collection::query()->create(['handle' => 'articles', 'title' => 'Articles', 'blueprint' => 'page', 'route' => '/articles/{slug}',
        'dated' => true, 'listing_route' => '/articles', 'listing_blueprint' => 'listing', 'per_page' => 2]);
    foreach (['One', 'Two', 'Three'] as $i => $title) {
        app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($articles, ['title' => $title]), new DateTimeImmutable('2026-01-0'.($i + 1)));
    }
    app(SaveListing::class)->handle($articles, ['intro' => 'All our <news>']);

    get('/articles')->assertOk()->assertSee('<h1>Articles</h1>', false)->assertSee('All our &lt;news&gt;', false)
        ->assertSeeInOrder(['Three', 'Two'])->assertDontSee('>One<', false)->assertSee('?page=2');
    get('/articles?page=2')->assertOk()->assertSee('One');
    get('/articles?page=9')->assertNotFound();
    // Translated fields are per language.
    get('/id/articles')->assertOk()->assertDontSee('All our');
    get('/sitemap.xml')->assertSee(url('/articles'))->assertSee(url('/id/articles'));

    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update'));
    get('/admin/cms/collections/articles/listing/edit')->assertInertia(fn (Assert $page) => $page
        ->component('Cms/Listing/Edit', shouldExist: false)->where('values.intro', 'All our <news>'));
    put('/admin/cms/collections/articles/listing', ['locale' => 'id', 'data' => ['intro' => 'Semua berita']])->assertSessionHasNoErrors();
    get('/id/articles')->assertSee('Semua berita');
    get('/admin/cms/collections/pages/listing/edit')->assertNotFound();

    // A save over someone else's newer one is refused.
    get('/admin/cms/collections/articles/listing/edit?locale=id')->assertInertia(fn (Assert $page) => $page->where('version', 1));
    put('/admin/cms/collections/articles/listing', ['locale' => 'id', 'data' => ['intro' => 'Berita'], 'version' => 1])->assertSessionHasNoErrors();
    put('/admin/cms/collections/articles/listing', ['locale' => 'id', 'data' => ['intro' => 'Stale'], 'version' => 1])->assertSessionHasErrors('version');
    expect(fn () => app(SaveListing::class)->handle($articles, ['intro' => 'Stale'], 'id', 1))->toThrow(ValidationException::class, 'Someone else saved')
        ->and($articles->listings()->where('locale', 'id')->firstOrFail()->only(['version']))->toBe(['version' => 2]);
    get('/id/articles')->assertSee('Berita');
});

it('serves a homepage saved in code (a seeder) at once', function () {
    $home = structurePage('Welcome');
    get('/')->assertNotFound();

    $settings = app(SiteSettings::class);
    $settings->homepage = $home->id;
    $settings->save();

    get('/')->assertOk()->assertSee('Welcome');
});

it('serves the homepage at /, in each language, and redirects its own address', function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    $home = structurePage('Welcome');
    get('/')->assertNotFound();

    actingAs(adminUser(Panel::ACCESS, 'cms.settings.site'));
    get('/admin/settings/site')->assertOk();
    put('/admin/settings/site', ['homepage' => (string) $home->id])->assertSessionHasNoErrors();

    get('/')->assertOk()->assertSee('Welcome');
    get('/welcome')->assertRedirect(url('/'))->assertStatus(301);
    expect($home->mainTranslation()?->url())->toBe(url('/'));
    // Not translated: the main language's, unless that's turned off.
    get('/id')->assertOk()->assertSee('Welcome');
    get('/id/welcome')->assertRedirect(url('/id'));
    config(['sunrice-cms.site.fallback' => false]);
    get('/id')->assertNotFound();
});
