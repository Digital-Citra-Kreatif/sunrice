<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Site\Page;
use Sunrice\Cms\Support\StructuredData;

use function Pest\Laravel\get;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['sunrice-cms.locales' => ['en', 'id'], 'app.name' => 'Acme']);
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [['handle' => 'intro', 'type' => 'text']]]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}', 'dated' => true, 'listing_route' => '/blog', 'per_page' => 1]);
});

function edgePost(string $title, array $input = []): EntryTranslation
{
    return app(PublishEntry::class)->handle(app(CreateEntry::class)->handle(test()->blog, ['title' => $title, ...$input]));
}

it('keeps a not-found page out of search results, without a canonical address', function () {
    $views = sys_get_temp_dir().'/sunrice-errors-'.uniqid();
    mkdir($views.'/errors', recursive: true);
    file_put_contents($views.'/errors/404.blade.php', '<html><head><x-sunrice-cms::seo title="Not found" /></head><body>Gone</body></html>');
    config(['view.paths' => [$views, ...config('view.paths')]]);

    $html = (string) get('/no-such-page')->assertNotFound()->getContent();

    expect($html)->toContain('<title>Not found | Acme</title>')
        ->toContain('<meta name="robots" content="noindex">')
        ->not->toContain('rel="canonical"')
        ->not->toContain('og:url');
});

it('shows a listing page past its last as a plain not-found page: no canonical, alternates or listing data (NEW2-front-1)', function () {
    edgePost('One');
    $views = sys_get_temp_dir().'/sunrice-errors-'.uniqid();
    mkdir($views.'/errors', recursive: true);
    file_put_contents($views.'/errors/404.blade.php', '<html><head><x-sunrice-cms::seo /></head><body>Gone</body></html>');
    config(['view.paths' => [$views, ...config('view.paths')]]);

    $html = (string) get('/blog?page=123456')->assertNotFound()->getContent();

    expect($html)->toContain('<meta name="robots" content="noindex">')
        ->not->toContain('rel="canonical"')
        ->not->toContain('hreflang')
        ->not->toContain('og:url')
        ->not->toContain('CollectionPage')
        ->not->toContain('BreadcrumbList')
        ->not->toContain('page=123456')
        ->toContain('"WebSite"');
});

it('numbers the title of a listing past its first page, and links page 1 without ?page=1', function () {
    edgePost('One');
    edgePost('Two');
    edgePost('Three');

    get('/blog')->assertOk()->assertSee('<title>Blog | Acme</title>', false);
    get('/blog?page=2')->assertOk()
        ->assertSee('<title>Blog (page 2) | Acme</title>', false)
        ->assertSee('href="'.url('/blog').'" rel="prev"', false)
        ->assertDontSee('page=1');
    get('/blog?page=3')->assertOk()->assertSee('href="'.url('/blog?page=2').'" rel="prev"', false);
});

it('asks search engines not to index a hidden page, but still to follow its links', function () {
    edgePost('Hidden', ['seo' => ['noindex' => true]]);

    get('/blog/hidden')->assertOk()->assertSee('<meta name="robots" content="noindex">', false);
});

it('names the article\'s author a template gives, and the website per language', function () {
    $post = edgePost('Hello');
    $id = app(CreateTranslation::class)->handle($post->entry, 'id');
    app(PublishEntry::class)->handle(app(SaveDraft::class)->handle($id, ['title' => 'Halo', 'slug' => 'halo']));
    Route::get('/authored', function () use ($post) {
        (new Page(Page::ENTRY, 'en', 'cms.entry', entry: $post->fresh()))->share();

        return Blade::render('<x-sunrice-cms::seo :author="[\'@type\' => \'Person\', \'name\' => \'Ayu\', \'url\' => \'/team/ayu\']" />');
    });

    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', (string) get('/authored')->getContent(), $m);
    $nodes = collect(json_decode($m[1], true)['@graph'])->keyBy('@type');
    expect($nodes['Article']['author'])->toBe(['@type' => 'Person', 'name' => 'Ayu', 'url' => url('/team/ayu')]);

    $graph = fn (string $locale) => collect(StructuredData::graph(new Page(Page::LISTING, $locale, 'cms.listing', collection: $this->blog)))->keyBy('@type')['WebSite'];
    expect($graph('en')['@id'])->toBe(url('/').'#website')
        ->and($graph('id')['@id'])->toBe(url('/id').'#website')
        ->and($graph('id')['url'])->toBe(url('/id'));
});

it('sends trailing-slash and /index.php addresses to the page\'s own address', function () {
    edgePost('Hello');
    get('/blog/hello')->assertOk();

    // The test client trims trailing slashes: through the kernel as a browser would ask.
    $send = fn (string $uri, array $server = []) => TestResponse::fromBaseResponse(app(Kernel::class)->handle(Request::create($uri, server: $server)));
    $send('/')->assertNotFound(); // no homepage here, and no redirect either
    $send('/blog/hello/')->assertRedirect(url('/blog/hello'))->assertStatus(301);
    $send('/blog/hello/?ref=x')->assertRedirect(url('/blog/hello?ref=x'));
    $send('http://localhost/index.php/blog/hello', ['SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => public_path('index.php')])->assertHeader('Location', 'http://localhost/blog/hello');
    $send('http://localhost/index.php', ['SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => public_path('index.php')])->assertHeader('Location', 'http://localhost/');
});

it('lists the other languages of term and listing pages in the sitemap', function () {
    edgePost('Hello');
    $topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'route' => '/topics/{slug}']);
    $news = app(SaveTerm::class)->handle($topics, ['title' => 'News']);
    app(SaveTerm::class)->handle($topics, ['title' => 'Berita', 'locale' => 'id'], $news);

    $xml = (string) get('/sitemap.xml')->assertOk()->getContent();
    $entry = fn (string $loc) => preg_match('#<url><loc>'.preg_quote($loc, '#').'</loc>.*?</url>#', $xml, $m) === 1 ? $m[0] : '';

    expect($entry(url('/topics/news')))->toContain('hreflang="id" href="'.url('/id/topics/berita').'"')->toContain('hreflang="x-default"')
        ->and($entry(url('/id/topics/berita')))->toContain('hreflang="en" href="'.url('/topics/news').'"')
        ->and($entry(url('/blog')))->toContain('hreflang="id" href="'.url('/id/blog').'"')
        ->and($entry(url('/id/blog')))->toContain('hreflang="en" href="'.url('/blog').'"');
    expect(simplexml_load_string($xml))->not->toBeFalse();
});
