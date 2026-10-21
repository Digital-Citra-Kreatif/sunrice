<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Menus\SaveMenu;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Fieldset;
use Sunrice\Cms\Models\Menu;
use Sunrice\Cms\Models\Taxonomy;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;

/** What publishing the starter templates writes into the app (removed after each test: the test app is shared). */
function starterPaths(): array
{
    return [resource_path('views/cms'), resource_path('views/errors/404.blade.php'), resource_path('views/errors/500.blade.php'), public_path('cms/site.css')];
}

beforeEach(function () {
    foreach (starterPaths() as $path) {
        expect(file_exists($path))->toBeFalse("{$path} exists before the test: remove it.");
    }
});

afterEach(function () {
    File::deleteDirectory(resource_path('views/cms'));
    File::delete([resource_path('views/errors/404.blade.php'), resource_path('views/errors/500.blade.php'), public_path('cms/site.css')]);
    foreach ([resource_path('views/errors'), public_path('cms')] as $directory) {
        if (is_dir($directory) && File::isEmptyDirectory($directory)) {
            File::deleteDirectory($directory);
        }
    }
});

it('publishes the starter templates, and the site renders with them', function () {
    config(['sunrice-cms.locales' => ['en', 'id'], 'app.name' => 'Acme']);
    actingAs(adminUser());
    Fieldset::query()->create(['handle' => 'text_block', 'title' => 'Text', 'fields' => [['handle' => 'heading', 'type' => 'text'], ['handle' => 'body', 'type' => 'rich_text']]]);
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [
        ['handle' => 'excerpt', 'type' => 'text'],
        ['handle' => 'body', 'type' => 'rich_text'],
        ['handle' => 'cta', 'type' => 'link'],
        ['handle' => 'sections', 'type' => 'flexible', 'config' => ['blocks' => ['text_block']]],
        ['handle' => 'topics', 'type' => 'terms', 'config' => ['taxonomy' => 'topics', 'multiple' => true]],
    ]]);
    $topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'route' => '/topics/{slug}']);
    $news = app(SaveTerm::class)->handle($topics, ['title' => 'News']);
    $blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}', 'listing_route' => '/blog', 'dated' => true]);
    $hello = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($blog, ['title' => 'Hello <world>', 'slug' => 'hello', 'data' => [
        'excerpt' => 'A first post',
        'body' => '<p>The <strong>body</strong></p>',
        'cta' => ['type' => 'url', 'url' => 'https://example.com', 'label' => 'Read on'],
        'sections' => [['id' => 'b1', 'type' => 'text_block', 'key' => 'intro', 'hidden' => false, 'values' => ['heading' => 'A block', 'body' => '<p>Block <em>text</em></p>']]],
        'topics' => [$news->id],
    ]]));
    $halo = app(CreateTranslation::class)->handle($hello->entry, 'id');
    app(SaveDraft::class)->handle($halo, ['title' => 'Halo', 'slug' => 'halo']);
    app(PublishEntry::class)->handle($halo->refresh());
    app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($blog, ['title' => 'English only', 'slug' => 'english-only']));
    app(SaveMenu::class)->handle(Menu::query()->create(['handle' => 'main', 'title' => 'Main']), [
        ['collection_id' => $blog->id, 'labels' => ['en' => 'Blog'], 'children' => [['entry_id' => $hello->entry_id]]],
    ]);
    auth()->logout();

    artisan('vendor:publish', ['--tag' => 'sunrice-cms-templates'])->assertSuccessful();
    foreach (['layout', 'entry', 'listing', 'term', 'search', 'comment-unsubscribe', 'partials/card', 'partials/pagination', 'partials/language-switcher', 'partials/menu', 'partials/blocks', 'partials/block', 'partials/fields'] as $view) {
        expect(resource_path("views/cms/{$view}.blade.php"))->toBeFile();
    }
    expect(resource_path('views/errors/404.blade.php'))->toBeFile()
        ->and(resource_path('views/errors/500.blade.php'))->toBeFile()
        ->and(public_path('cms/site.css'))->toBeFile();

    get('/blog/hello')->assertOk()
        ->assertViewIs('cms.entry')
        ->assertSee('<title>Hello &lt;world&gt; | Acme</title>', false)
        ->assertSee('<h1>Hello &lt;world&gt;</h1>', false)
        ->assertSee('<strong>body</strong>', false)
        ->assertSee('<section class="block block-text_block" id="intro">', false)
        ->assertSee('Block <em>text</em>', false)
        ->assertSee('<a class="button" href="https://example.com"', false)
        ->assertSee('href="'.asset('cms/site.css').'"', false)
        ->assertSee('<a class="skip-link" href="#main">', false)
        ->assertSee('<body class="page-entry collection-blog', false)
        // The menu marks the page, and the switcher links the other language.
        ->assertSee('<a href="'.url('/blog/hello').'" aria-current="page"', false)
        ->assertSee('href="'.url('/id/blog/halo').'" hreflang="id" lang="id"', false)
        ->assertDontSee('<script>', false);

    get('/blog')->assertOk()->assertViewIs('cms.listing')->assertSee('<li class="card">', false)->assertSee('A first post');
    get('/topics/news')->assertOk()->assertViewIs('cms.term')->assertSee('<p class="eyebrow">Topics</p>', false)->assertSee('Hello &lt;world&gt;', false);
    commitForFullText();
    get('/search?q=hello')->assertOk()->assertViewIs('cms.search')->assertSee('1 result for “hello”')->assertSee(url('/blog/hello'));
    // Not translated: a note, in the main language's content.
    $page = get('/id/topics/news')->assertOk()->assertSee('This page isn\'t translated yet');
    expect($page->getContent())->toMatch('~<div lang="en">\s*<h1>News</h1>~');
    expect(get('/blog/hello')->getContent())->toContain('<article class="entry">');
    expect(get('/id/blog/english-only')->assertOk()->getContent())->toContain('<article class="entry" lang="en">')
        ->toContain('<p class="notice" role="note" lang="id">');
    get('/nowhere')->assertNotFound()->assertSee('Page not found')->assertSee('role="search"', false);

    expect(view('errors.500')->render())->toContain('Something went wrong')->toContain(asset('cms/site.css'));
});
