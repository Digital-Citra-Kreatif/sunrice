<?php

declare(strict_types=1);

use Sunrice\Cms\Actions\Install\ScaffoldCollections;
use Sunrice\Cms\Menus;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\CollectionListing;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Fieldset;
use Sunrice\Cms\Models\Form;
use Sunrice\Cms\Models\GlobalSet;
use Sunrice\Cms\Models\Menu;
use Sunrice\Cms\Models\MenuItem;
use Sunrice\Cms\Models\Redirect;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\Term;
use Sunrice\Cms\Models\TermTranslation;
use Sunrice\Cms\Structure\StructureImporter;

use function Pest\Laravel\artisan;
use function Pest\Laravel\get;

beforeEach(fn () => config(['sunrice-cms.locales' => ['en', 'id']]));

it('seeds a demo site through the actions', function () {
    artisan('sunrice:demo-content')->assertSuccessful();

    expect(Collection::query()->pluck('handle')->sort()->values()->all())->toBe(['blog', 'pages'])
        ->and(Entry::query()->count())->toBe(12)
        ->and(EntryTranslation::query()->count())->toBe(24)
        ->and(EntryTranslation::query()->where('status', EntryTranslation::PUBLISHED)->count())->toBe(22)
        ->and(Term::query()->count())->toBe(4)
        ->and(Term::query()->whereNotNull('parent_id')->count())->toBe(2)
        ->and(Fieldset::query()->count())->toBe(2)
        ->and(Menu::query()->count())->toBe(1)
        ->and(MenuItem::query()->count())->toBe(7)
        ->and(GlobalSet::query()->first()?->values()->count())->toBe(2)
        ->and(Form::query()->where('handle', 'contact')->exists())->toBeTrue()
        ->and(Redirect::query()->count())->toBe(2);

    $post = EntryTranslation::query()->where('draft_slug', 'hello-world')->where('locale', 'en')->first();
    expect($post->data['sections'][1]['type'])->toBe('quote_block')
        // A topic of its own, live: no terms field.
        ->and($post->entry->terms->pluck('taxonomy.handle')->all())->toBe(['topics'])
        ->and($post->data)->not->toHaveKey('topics')
        ->and(Entry::query()->whereNotNull('parent_id')->count())->toBe(1);
    get('/blog/hello-world')->assertOk()->assertSee('Hello, world');
    get('/id/blog/hello-world')->assertOk()->assertSee('Halo, dunia');
});

it('adds only what is missing when run again, and starts over with --fresh', function () {
    artisan('sunrice:demo-content')->assertSuccessful();
    artisan('sunrice:demo-content')->expectsOutputToContain('already there')->assertSuccessful();
    expect(Entry::query()->count())->toBe(12);

    artisan('sunrice:demo-content', ['--fresh' => true])->assertSuccessful();
    expect(Entry::withTrashed()->count())->toBe(12)
        ->and(Term::withTrashed()->count())->toBe(4)
        ->and(Collection::withTrashed()->count())->toBe(2);
});

it('leaves out the Indonesian texts on an English-only site', function () {
    config(['sunrice-cms.locales' => ['en']]);

    artisan('sunrice:demo-content')->assertSuccessful();

    expect(Entry::query()->count())->toBe(12)
        ->and(EntryTranslation::query()->where('locale', 'id')->exists())->toBeFalse()
        ->and(TermTranslation::query()->where('locale', 'id')->exists())->toBeFalse()
        ->and(GlobalSet::query()->first()?->values()->pluck('locale')->all())->toBe(['en'])
        ->and(CollectionListing::query()->pluck('locale')->all())->toBe(['en'])
        ->and(Form::query()->where('handle', 'contact')->first()?->translations)->toBe([]);
    get('/about')->assertOk()->assertSee('About us');
    get('/blog/hello-world')->assertOk()->assertSee('Hello, world');
    get('/blog')->assertOk();
});

it('fills an Indonesian-only site', function () {
    config(['sunrice-cms.locales' => ['id']]);

    artisan('sunrice:demo-content')->assertSuccessful();

    expect(Entry::query()->count())->toBe(12)
        ->and(EntryTranslation::query()->pluck('locale')->unique()->values()->all())->toBe(['id'])
        ->and(Term::query()->count())->toBe(4)
        ->and(TermTranslation::query()->where('locale', 'en')->exists())->toBeFalse()
        ->and(GlobalSet::query()->first()?->values()->first()?->data)->toMatchArray(['tagline' => 'Situs demo kecil', 'email' => 'hello@example.com'])
        ->and(CollectionListing::query()->pluck('locale')->all())->toBe(['id'])
        ->and(Form::query()->where('handle', 'contact')->first()?->message)->toStartWith('Terima kasih')
        ->and(array_column(app(Menus::class)->get('main', 'id'), 'label'))->toBe(['Beranda', 'Tentang kami', 'Blog', 'Kontak']);
    get('/about')->assertOk()->assertSee('Tentang kami');
    get('/blog/hello-world')->assertOk()->assertSee('Halo, dunia');
});

it('refuses a site with neither English nor Indonesian, saving nothing', function () {
    config(['sunrice-cms.locales' => ['de', 'fr']]);
    $blueprints = Blueprint::query()->count();

    artisan('sunrice:demo-content')
        ->expectsOutputToContain('add en or id to the site\'s languages')
        ->assertFailed();

    expect(Collection::withTrashed()->count())->toBe(0)
        ->and(Blueprint::query()->count())->toBe($blueprints)
        ->and(Menu::query()->count())->toBe(0)
        ->and(Redirect::query()->count())->toBe(0);
});

it('fills the installer\'s Posts instead of making a Blog', function () {
    app(ScaffoldCollections::class)->handle(['pages', 'posts']);

    artisan('sunrice:demo-content')->assertSuccessful();

    $posts = Collection::query()->where('handle', 'posts')->firstOrFail();
    expect(Collection::withTrashed()->pluck('handle')->sort()->values()->all())->toBe(['pages', 'posts'])
        ->and(Entry::query()->where('collection_id', $posts->id)->count())->toBe(8)
        ->and(Entry::query()->count())->toBe(12)
        // The installer's blueprint kept as it is; the sections' fieldsets aren't needed.
        ->and(Blueprint::query()->where('handle', 'post')->first()?->title)->toBe('Post')
        ->and(Fieldset::query()->count())->toBe(0)
        ->and(Taxonomy::query()->where('handle', 'topics')->firstOrFail()->collections()->pluck('handle')->all())->toBe(['posts'])
        ->and(Redirect::query()->where('from_path', '/news')->value('to_url'))->toBe('/blog');

    $post = EntryTranslation::query()->where('collection_id', $posts->id)->where('draft_slug', 'hello-world')->where('locale', 'en')->firstOrFail();
    expect($post->data['excerpt'])->toBe('Hello, world, in a few words.')
        ->and($post->data['body'])->toContain('The story of Hello, world.')->toContain('<blockquote>')
        ->and($post->data)->not->toHaveKeys(['intro', 'sections'])
        ->and($post->entry->terms->pluck('taxonomy.handle')->all())->toBe(['topics']);

    $blog = collect(app(Menus::class)->get('main', 'en'))->firstWhere('label', 'Blog');
    expect($blog['url'] ?? null)->toBe(url('/blog'));
    get('/blog')->assertOk()->assertSee('Hello, world');
    get('/blog/hello-world')->assertOk()->assertSee('The story of Hello, world.');
    get('/id/blog/hello-world')->assertOk()->assertSee('Halo, dunia');

    artisan('sunrice:demo-content')->expectsOutputToContain('already there')->assertSuccessful();
    expect(Entry::query()->count())->toBe(12)
        ->and(MenuItem::query()->count())->toBe(7);

    // --fresh deletes the demo's posts, not the installer's Posts or its blueprint.
    artisan('sunrice:demo-content', ['--fresh' => true])->assertSuccessful();
    expect(Entry::query()->where('collection_id', $posts->id)->count())->toBe(8)
        ->and(Collection::query()->where('handle', 'posts')->exists())->toBeTrue()
        ->and(Collection::withTrashed()->where('handle', 'blog')->exists())->toBeFalse();
});

it('names a collection of the site\'s own at the Blog\'s addresses, saving nothing', function () {
    app(StructureImporter::class)->import([
        'blueprints' => [['handle' => 'article', 'title' => 'Article', 'fields' => []]],
        'collections' => [['handle' => 'articles', 'title' => 'Articles', 'blueprint' => 'article', 'route' => '/blog/{slug}', 'listing_route' => '/blog']],
    ]);

    artisan('sunrice:demo-content')
        ->expectsOutputToContain('"Articles" has the address /blog/{slug}')
        ->doesntExpectOutputToContain('--fresh')
        ->assertFailed();

    expect(Collection::query()->pluck('handle')->all())->toBe(['articles'])
        ->and(Taxonomy::query()->count())->toBe(0)
        ->and(Entry::query()->count())->toBe(0)
        ->and(Menu::query()->count())->toBe(0)
        ->and(Blueprint::query()->pluck('handle')->all())->toBe(['article']);
});
