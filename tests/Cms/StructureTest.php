<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
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

    get("/admin/cms/entries/{$team->id}/edit")->assertInertia(fn (Assert $page) => $page->where('settings', ['parentId' => null, 'template' => null]));
    put("/admin/cms/entries/{$team->id}/settings", ['parent_id' => $about->id, 'template' => 'Bad Name!'])->assertSessionHasErrors('template');
    put("/admin/cms/entries/{$team->id}/settings", ['parent_id' => $about->id, 'template' => 'cms.special'])->assertSessionHasNoErrors();

    get('/admin/cms/collections/pages/entries')->assertInertia(fn (Assert $page) => $page->where('entries.data.1.parent', 'About'));
    get('/team')->assertOk()->assertSee('A special page: Team');
    expect(Content::entries('pages')->childrenOf($about)->get()->pluck('title')->all())->toBe(['Team']);
});

it('moves children up when their parent is deleted for good', function () {
    [$about, $team] = [structurePage('About'), structurePage('Team')];
    app(SaveEntrySettings::class)->handle($team, ['parent_id' => $about->id]);
    $about->delete();
    app(ForceDeleteEntry::class)->handle($about);

    expect($team->fresh()?->parent_id)->toBeNull()->and(EntryTranslation::query()->count())->toBe(1);
});
