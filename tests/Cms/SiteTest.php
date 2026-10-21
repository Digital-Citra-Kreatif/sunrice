<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Content;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Redirect;
use Sunrice\Cms\Models\Taxonomy;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    actingAs(adminUser());
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [
        ['handle' => 'intro', 'type' => 'text'],
        ['handle' => 'body', 'type' => 'rich_text'],
        ['handle' => 'tags', 'type' => 'terms', 'config' => ['taxonomy' => 'tags', 'multiple' => true]],
    ]]);
    Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}', 'dated' => true]);
    $this->pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'post', 'route' => '/{slug}']);
});

function sitePost(Collection $collection, string $title, array $data = [], bool $publish = true): EntryTranslation
{
    $translation = app(CreateEntry::class)->handle($collection, ['title' => $title, 'data' => $data]);

    return $publish ? app(PublishEntry::class)->handle($translation) : $translation;
}

it('shows live entries at their collection\'s URL', function () {
    $hello = sitePost($this->blog, 'Hello world', ['intro' => 'An intro', 'body' => '<p>The <strong>body</strong></p>']);

    expect($hello->url())->toBe(url('/blog/hello-world'));
    get('/blog/hello-world')->assertOk()->assertSee('<h1>Hello world</h1>', false)->assertSee('<strong>body</strong>', false)->assertSee('An intro');
});

it('escapes every field but rich text', function () {
    sitePost($this->blog, 'Careful', ['intro' => '<script>alert(1)</script>']);

    get('/blog/careful')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
});

it('answers 404 for drafts, future dates, trashed entries and unknown slugs', function () {
    sitePost($this->blog, 'Draft', publish: false);
    travelTo('2026-10-19');
    app(PublishEntry::class)->handle(sitePost($this->blog, 'Later', publish: false), new DateTimeImmutable('2026-12-01'));
    sitePost($this->blog, 'Gone')->entry->delete();

    get('/blog/draft')->assertNotFound();
    get('/blog/later')->assertNotFound();
    get('/blog/gone')->assertNotFound();
    get('/blog/nothing-here')->assertNotFound();
});

it('tries the more specific pattern first, and doesn\'t fall through', function () {
    sitePost($this->pages, 'About');
    sitePost($this->blog, 'Hello');

    get('/about')->assertOk()->assertSee('About');
    get('/blog/hello')->assertOk()->assertSee('Hello');
    // /blog/about matches the blog's pattern, which has no "about".
    get('/blog/about')->assertNotFound();
});

it('serves other languages under their prefix', function () {
    $hello = sitePost($this->blog, 'Hello');
    $id = app(CreateTranslation::class)->handle($hello->entry, 'id');
    app(SaveDraft::class)->handle($id, ['title' => 'Halo', 'slug' => 'halo']);
    app(PublishEntry::class)->handle($id);

    expect($id->fresh()?->url())->toBe(url('/id/blog/halo'));
    get('/id/blog/halo')->assertOk()->assertSee('<html lang="id">', false)->assertSee('Halo');
    get('/blog/halo')->assertNotFound();
    get('/id/blog/hello')->assertNotFound();
});

it('leaves the app\'s own routes first', function () {
    sitePost($this->pages, 'Contact');
    Route::get('/contact', fn () => 'the app contact page');
    app('router')->getRoutes()->refreshNameLookups();

    get('/contact')->assertOk()->assertSee('the app contact page');
});

it('renders with the app\'s templates when there are', function () {
    $views = sys_get_temp_dir().'/sunrice-views-'.uniqid();
    mkdir("{$views}/cms", recursive: true);
    file_put_contents("{$views}/cms/blog.blade.php", 'Blog template: {{ $title }} / {{ $values[\'intro\'] }} / {{ $entry->url() }}');
    file_put_contents("{$views}/cms/entry.blade.php", 'Any entry: {{ $title }}');
    View::addLocation($views);
    sitePost($this->blog, 'Hello', ['intro' => 'Hi']);
    sitePost($this->pages, 'About');

    get('/blog/hello')->assertSeeText('Blog template: Hello / Hi / '.url('/blog/hello'));
    get('/about')->assertSeeText('Any entry: About');
});

it('previews drafts through a signed link only', function () {
    $draft = sitePost($this->blog, 'Not yet', ['intro' => 'Draft intro'], publish: false);
    $url = URL::temporarySignedRoute('sunrice.cms.preview', now()->addMinutes(30), ['translation' => $draft->id]);

    // The ribbon is added whatever the template: an app's own templates get it too.
    get($url)->assertOk()->assertSee('Draft intro')->assertSee('data-sunrice-draft-banner', false)->assertSee('Preview of the draft')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertHeader('Cache-Control', 'no-store, private');
    get("/cms-preview/{$draft->id}")->assertForbidden();
});

it('keeps the query string when redirecting', function () {
    Redirect::query()->create(['from_path' => '/old', 'to_url' => '/new', 'status' => 301]);
    Redirect::query()->create(['from_path' => '/older', 'to_url' => 'https://example.com/?from=older', 'status' => 302]);

    get('/old?utm_source=mail&x=1')->assertRedirect('/new?utm_source=mail&x=1')->assertStatus(301);
    get('/old')->assertRedirect('/new');
    get('/older?x=1')->assertRedirect('https://example.com/?from=older&x=1');
});

it('speaks the address\'s language before anything is looked up, and drops the main language\'s prefix', function () {
    $hello = sitePost($this->blog, 'Hello');
    Route::get('/en/custom', fn () => 'the app has its own page here');
    app('router')->getRoutes()->refreshNameLookups();

    get('/id/blog/nothing-here')->assertNotFound();
    expect(app()->getLocale())->toBe('id');
    get('/blog/nothing-here')->assertNotFound();
    expect(app()->getLocale())->toBe('en');
    get('/id/search?q=x')->assertOk();
    expect(app()->getLocale())->toBe('id');

    get('/en/blog/hello?x=1')->assertRedirect('/blog/hello?x=1')->assertStatus(301);
    get('/en')->assertRedirect('/');
    get('/en/custom')->assertOk()->assertSee('the app has its own page here');
    expect($hello->url())->toBe(url('/blog/hello'));
});

it('gives the editor the preview and live links', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    $hello = sitePost($this->blog, 'Hello');
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view'));

    get("/admin/cms/entries/{$hello->entry_id}/edit")->assertInertia(fn ($page) => $page
        ->where('entry.url', url('/blog/hello'))
        ->where('entry.previewUrl', fn (string $url) => str_contains($url, "/cms-preview/{$hello->id}?expires=")));
});

it('checks collection URLs in the admin', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser(Panel::ACCESS, 'cms.collections.view', 'cms.collections.create'));

    post('/admin/collections', ['title' => 'News', 'handle' => 'news', 'route' => 'news/{slug}'])->assertSessionHasErrors('route');
    post('/admin/collections', ['title' => 'News', 'handle' => 'news', 'route' => '/news/{id}'])->assertSessionHasErrors('route');
    post('/admin/collections', ['title' => 'News', 'handle' => 'news', 'route' => '/blog/{slug}'])->assertSessionHasErrors('route');
    post('/admin/collections', ['title' => 'News', 'handle' => 'news', 'route' => '/news/{slug}'])->assertSessionHasNoErrors();
});

it('queries live entries for templates', function () {
    $news = app(SaveTerm::class)->handle(Taxonomy::query()->firstOrFail(), ['title' => 'News']);
    travelTo('2026-10-01');
    sitePost($this->blog, 'Older', ['tags' => [$news->id]]);
    travelTo('2026-10-10');
    sitePost($this->blog, 'Newer');
    sitePost($this->blog, 'Draft', ['tags' => [$news->id]], publish: false);
    sitePost($this->pages, 'About');

    expect(Content::entries('blog')->latest()->get()->pluck('title')->all())->toBe(['Newer', 'Older'])
        ->and(Content::entries('blog')->whereTerm($news)->get()->pluck('title')->all())->toBe(['Older'])
        ->and(Content::entries()->count())->toBe(3)
        ->and(Content::entries('blog')->whereSlug('newer')->first()?->title)->toBe('Newer')
        ->and(Content::entries('blog')->paginate(1)->total())->toBe(2);
});

it('serves the main language where an entry isn\'t translated, at that language\'s address', function () {
    $hello = sitePost($this->blog, 'Hello', ['intro' => 'In English']);
    $draft = app(CreateTranslation::class)->handle($hello->entry, 'id');
    app(SaveDraft::class)->handle($draft, ['title' => 'Halo', 'slug' => 'halo']);

    // A translation that isn't live doesn't count, nor does its slug.
    get('/id/blog/hello')->assertOk()->assertSee('<html lang="id">', false)->assertSee('<h1>Hello</h1>', false)->assertSee('In English')
        ->assertSee('lang-id', false);
    expect(app()->getLocale())->toBe('id')
        ->and(Content::page()?->fallback)->toBeTrue()
        ->and(Content::localeUrls())->toBe(['en' => url('/blog/hello'), 'id' => url('/id/blog/hello')]);
    get('/id/blog/halo')->assertNotFound();

    app(PublishEntry::class)->handle($draft->refresh());
    get('/id/blog/halo')->assertOk()->assertSee('<h1>Halo</h1>', false);
    get('/id/blog/hello')->assertNotFound();
});

it('gives templates $fallback, and answers 404 with the fallback off', function () {
    sitePost($this->blog, 'Hello');
    config(['sunrice-cms.site.fallback' => false]);

    get('/id/blog/hello')->assertNotFound();
    expect(Content::page())->toBeNull();

    config(['sunrice-cms.site.fallback' => true]);
    get('/id/blog/hello')->assertOk()->assertViewHas('fallback', true)->assertViewHas('locale', 'id');
    get('/blog/hello')->assertOk()->assertViewHas('fallback', false);
});

it('serves an untranslated term\'s page in the main language too', function () {
    $tags = Taxonomy::query()->firstOrFail();
    $tags->update(['route' => '/tags/{slug}']);
    $news = app(SaveTerm::class)->handle($tags, ['title' => 'News']);

    get('/id/tags/news')->assertOk()->assertViewHas('fallback', true)->assertViewHas('locale', 'id');
    expect(Content::localeUrls())->toBe(['en' => url('/tags/news'), 'id' => url('/id/tags/news')]);

    app(SaveTerm::class)->handle($tags, ['title' => 'Berita', 'locale' => 'id'], $news);
    get('/id/tags/news')->assertNotFound();
    get('/id/tags/berita')->assertOk()->assertViewHas('fallback', false);
});

it('leaves pages shown in the main language out of the search in another', function () {
    sitePost($this->blog, 'Coffee beans');

    get('/id/search?q=coffee')->assertOk()->assertDontSee(url('/id/blog/coffee-beans'))->assertDontSee(url('/blog/coffee-beans'));
});
