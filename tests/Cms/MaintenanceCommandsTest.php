<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Collections\DeleteCollection;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Globals\SaveGlobals;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\GlobalSet;
use Sunrice\Cms\Models\Menu;
use Sunrice\Cms\Models\MenuItem;
use Sunrice\Cms\Models\Revision;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\Term;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser());
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [['handle' => 'intro', 'type' => 'text'], ['handle' => 'rating', 'type' => 'number']]]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}']);
});

it('lists, restores and purges deleted collections and taxonomies', function () {
    $post = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->blog, ['title' => 'Hello']));
    $tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    $news = app(SaveTerm::class)->handle($tags, ['title' => 'News']);
    $post->entry->terms()->attach($news);
    $menu = Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    $link = MenuItem::query()->create(['menu_id' => $menu->id, 'entry_id' => $post->entry_id]);
    app(DeleteCollection::class)->handle($this->blog);
    $tags->delete();

    artisan('sunrice:orphans')->assertSuccessful()->expectsOutputToContain('--restore');
    artisan('sunrice:orphans', ['--restore' => true, '--handle' => ['blog']])->assertSuccessful();
    expect($this->blog->fresh()?->trashed())->toBeFalse()->and(Permission::query()->where('name', 'cms.entries.blog.view')->exists())->toBeTrue();
    get('/blog/hello')->assertOk();

    app(DeleteCollection::class)->handle($this->blog->fresh() ?? $this->blog);
    artisan('sunrice:orphans', ['--purge' => true])->expectsConfirmation('Delete all of the above for good? This can\'t be undone.', 'no')->assertFailed();
    artisan('sunrice:orphans', ['--purge' => true, '--force' => true])->assertSuccessful()->expectsOutputToContain('1 entries deleted');

    expect(Collection::withTrashed()->count())->toBe(0)->and(Taxonomy::withTrashed()->count())->toBe(0)
        ->and(Entry::withTrashed()->count())->toBe(0)->and(EntryTranslation::query()->count())->toBe(0)
        ->and(Revision::query()->count())->toBe(0)->and(Term::withTrashed()->count())->toBe(0)
        ->and(DB::table('sunrice_entry_term')->count())->toBe(0)
        ->and($link->fresh()?->entry_id)->toBeNull();
    artisan('sunrice:orphans')->assertSuccessful()->expectsOutputToContain('No deleted collections');
});

it('renames a field in the blueprint and in what is stored with it', function () {
    $post = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->blog, ['title' => 'Hello', 'data' => ['intro' => 'Hi', 'rating' => 4]]));
    app(SaveDraft::class)->handle($post->fresh() ?? $post, ['data' => ['intro' => 'Hey']]);
    $contact = GlobalSet::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'post']);
    app(SaveGlobals::class)->handle($contact, ['intro' => 'Global']);

    artisan('sunrice:rename-field', ['blueprint' => 'post', 'from' => 'intro', 'to' => 'summary'])->assertSuccessful()->expectsOutputToContain('Renamed "intro" to "summary"');

    $post->refresh();
    expect(Blueprint::query()->where('handle', 'post')->first()?->fields[0]['handle'])->toBe('summary')
        ->and($post->data)->toBe(['summary' => 'Hi', 'rating' => 4])->and($post->draft_data['summary'])->toBe('Hey')
        ->and($post->revisions()->first()?->data)->toHaveKey('summary')->not->toHaveKey('intro')
        ->and($contact->fresh()?->data('en')['summary'])->toBe('Global');
    get('/blog/hello')->assertOk()->assertSee('Hi');

    artisan('sunrice:rename-field', ['blueprint' => 'post', 'from' => 'summary', 'to' => 'rating'])->assertFailed()->expectsOutputToContain('has a field "rating" already');
    artisan('sunrice:rename-field', ['blueprint' => 'post', 'from' => 'nope', 'to' => 'x'])->assertFailed();
    artisan('sunrice:rename-field', ['blueprint' => 'post', 'from' => 'summary', 'to' => 'Bad-Name'])->assertFailed();
    artisan('sunrice:rename-field', ['blueprint' => 'missing', 'from' => 'a', 'to' => 'b'])->assertFailed();
});

it('adds demo articles and removes them, never touching real content', function () {
    artisan('sunrice:demo-content', ['count' => 30])->assertSuccessful()->expectsOutputToContain('Added 30 demo articles');
    artisan('sunrice:demo-content', ['count' => 5])->assertSuccessful()->expectsOutputToContain('(35 in all)');

    $demo = Collection::query()->where('handle', 'demo')->firstOrFail();
    expect(EntryTranslation::query()->where('collection_id', $demo->id)->live()->count())->toBe(35)
        ->and(Term::query()->count())->toBe(10);
    get('/demo/demo-article-7')->assertOk()->assertSee('Demo article 7');
    get('/demo')->assertOk();

    artisan('sunrice:demo-content', ['--collection' => 'blog'])->assertFailed()->expectsOutputToContain('is real content');
    artisan('sunrice:demo-content', ['--remove' => true, '--force' => true])->assertSuccessful()->expectsOutputToContain('Removed 35 demo articles and 10 demo topics');
    expect(Collection::withTrashed()->pluck('handle')->all())->toBe(['blog']);
});
