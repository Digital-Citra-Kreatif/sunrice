<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Media\Media;
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
use Sunrice\Cms\Models\Redirect;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Site\Page;
use Sunrice\Cms\Site\Sitemap;
use Sunrice\Cms\Support\SafeUrl;
use Sunrice\Cms\Support\Seo;
use Sunrice\Cms\Support\SiteCache;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['sunrice-cms.locales' => ['en', 'id'], 'app.name' => 'Acme']);
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update'));
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [['handle' => 'intro', 'type' => 'text']]]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}']);
});

function seoPost(Collection $collection, string $title, array $input = [], bool $publish = true): EntryTranslation
{
    $translation = app(CreateEntry::class)->handle($collection, ['title' => $title, ...$input]);

    return $publish ? app(PublishEntry::class)->handle($translation) : $translation;
}

function seoImage(string $mime = 'image/jpeg'): Media
{
    return Media::query()->create(['disk' => 'public', 'path' => 'a/'.uniqid().'.jpg', 'filename' => 'share.jpg', 'mime_type' => $mime, 'size' => 10]);
}

it('validates SEO and stores nothing when it\'s empty', function () {
    $image = seoImage();

    expect(Seo::validate(['title' => '  A   title ', 'description' => 'Words', 'image' => $image->id, 'noindex' => '1']))
        ->toBe(['title' => 'A title', 'description' => 'Words', 'image' => $image->id, 'noindex' => true])
        ->and(Seo::validate(['canonical' => 'https://example.com/elsewhere']))->toBe(['title' => null, 'description' => null, 'image' => null, 'noindex' => false, 'canonical' => 'https://example.com/elsewhere'])
        ->and(Seo::validate(['title' => ' ', 'noindex' => false]))->toBeNull()
        ->and(Seo::validate(null))->toBeNull()
        // An untouched card sends {}: the validator leaves empty arrays out of what it returns.
        ->and(Seo::validate([]))->toBeNull();

    expect(fn () => Seo::validate(['title' => str_repeat('a', 256)]))->toThrow(ValidationException::class);
    expect(fn () => Seo::validate(['image' => seoImage('application/pdf')->id]))->toThrow(ValidationException::class);
    expect(fn () => Seo::validate('nope'))->toThrow(ValidationException::class);
    expect(fn () => Seo::validate(['canonical' => 'javascript:alert(1)']))->toThrow(ValidationException::class);
});

it('puts SEO tags in the page head', function () {
    $image = seoImage();
    seoPost($this->blog, 'Hello', ['seo' => ['title' => 'Hello, search', 'description' => 'About <hello>', 'image' => $image->id]]);

    get('/blog/hello')->assertOk()
        ->assertSee('<title>Hello, search | Acme</title>', false)
        ->assertSee('<meta name="description" content="About &lt;hello&gt;">', false)
        ->assertSee('<link rel="canonical" href="'.url('/blog/hello').'">', false)
        ->assertSee('<meta property="og:image" content="'.$image->url().'">', false)
        ->assertDontSee('noindex');
});

it('falls back to the title, and links the other languages', function () {
    $hello = seoPost($this->blog, 'Hello');
    $id = app(CreateTranslation::class)->handle($hello->entry, 'id');
    app(SaveDraft::class)->handle($id, ['title' => 'Halo', 'slug' => 'halo']);
    app(PublishEntry::class)->handle($id);

    get('/id/blog/halo')->assertOk()
        ->assertSee('<title>Halo | Acme</title>', false)
        ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/blog/hello').'">', false)
        ->assertSee('<link rel="alternate" hreflang="id" href="'.url('/id/blog/halo').'">', false)
        ->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/blog/hello').'">', false)
        ->assertSee('<meta property="og:locale" content="id">', false);
});

it('gives each page of a listing or term its own canonical and alternate URLs', function () {
    $this->blog->update(['listing_route' => '/blog', 'per_page' => 1]);
    $topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'route' => '/topics/{slug}']);
    $news = app(SaveTerm::class)->handle($topics, ['title' => 'News']);
    Blueprint::query()->create(['handle' => 'tagged', 'title' => 'Tagged', 'fields' => [['handle' => 'topics', 'type' => 'terms', 'config' => ['taxonomy' => 'topics', 'multiple' => true]]]]);
    $this->blog->update(['blueprint' => 'tagged']);
    foreach (range(1, 11) as $n) {
        seoPost($this->blog, "Post {$n}", ['data' => ['topics' => [$news->id]]]);
    }

    get('/blog')->assertOk()->assertSee('<link rel="canonical" href="'.url('/blog').'">', false);
    get('/blog?page=2')->assertOk()
        ->assertSee('<link rel="canonical" href="'.url('/blog?page=2').'">', false)
        ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/blog?page=2').'">', false)
        ->assertSee('<link rel="alternate" hreflang="id" href="'.url('/id/blog?page=2').'">', false)
        ->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/blog?page=2').'">', false);
    get('/topics/news?page=2')->assertOk()->assertSee('<link rel="canonical" href="'.url('/topics/news?page=2').'">', false);
    // Named (<x-sunrice-cms::seo :term="$term">), the term's tags still carry the page shown.
    expect(Seo::forTerm($news->translations()->firstOrFail())['canonical'])->toBe(url('/topics/news?page=2'));

    expect(Seo::forListing($this->blog, 'en', 3)['canonical'])->toBe(url('/blog?page=3'))
        ->and(Seo::forTerm($news->translations()->firstOrFail(), 1)['canonical'])->toBe(url('/topics/news'))
        ->and((new Page(Page::LISTING, 'en', 'cms.listing', collection: $this->blog, pageNumber: 2))->localeUrls())->toBe(['en' => url('/blog?page=2'), 'id' => url('/id/blog?page=2')])
        ->and((new Page(Page::SEARCH, 'en', 'cms.search', pageNumber: 2))->localeUrls()['id'])->toBe(url('/id/search?page=2'));
});

it('refuses addresses that lead off the site in disguise', function () {
    expect(SafeUrl::isSafe('/about'))->toBeTrue()
        ->and(SafeUrl::isSafe('https://example.com/x'))->toBeTrue()
        ->and(SafeUrl::isSafe('//evil.com/x'))->toBeFalse()
        ->and(SafeUrl::isSafe('/ /evil.com'))->toBeFalse()
        ->and(SafeUrl::isSafe('/\\evil.com'))->toBeFalse()
        ->and(SafeUrl::isSafe('javascript:alert(1)'))->toBeFalse();
});

it('asks search engines to skip noindex pages', function () {
    seoPost($this->blog, 'Hidden', ['seo' => ['noindex' => true]]);

    get('/blog/hidden')->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

it('saves SEO from the editor with the draft', function () {
    $translation = seoPost($this->blog, 'Hello', publish: false);

    put("/admin/cms/entries/{$translation->entry_id}", ['title' => 'Hello', 'seo' => ['description' => 'New words'], 'version' => $translation->version])
        ->assertSessionHasNoErrors();
    expect($translation->fresh()?->draft_data['seo'])->toEqual(['title' => null, 'description' => 'New words', 'image' => null, 'noindex' => false]);

    // The editor sends an empty card as {}.
    put("/admin/cms/entries/{$translation->entry_id}", ['title' => 'Hello again', 'seo' => [], 'version' => $translation->fresh()?->version])->assertSessionHasNoErrors();
    expect($translation->fresh()?->draft_data['seo'])->toBeNull();

    put("/admin/cms/entries/{$translation->entry_id}", ['seo' => ['title' => str_repeat('a', 300)], 'version' => $translation->fresh()?->version])
        ->assertSessionHasErrors('seo.title');
});

it('redirects the old address when a published slug changes', function () {
    $hello = seoPost($this->blog, 'Hello');
    app(SaveDraft::class)->handle($hello, ['slug' => 'hello-there']);
    app(PublishEntry::class)->handle($hello->fresh() ?? $hello);

    get('/blog/hello')->assertRedirect('/blog/hello-there')->assertStatus(301);
    get('/blog/hello-there')->assertOk();
});

it('flattens chains and drops a redirect away from a page that came back', function () {
    $hello = seoPost($this->blog, 'Hello');
    foreach (['two', 'three'] as $slug) {
        app(SaveDraft::class)->handle($hello = $hello->fresh() ?? $hello, ['slug' => $slug]);
        app(PublishEntry::class)->handle($hello->fresh() ?? $hello);
    }

    expect(Redirect::query()->orderBy('from_path')->pluck('to_url', 'from_path')->all())
        ->toBe(['/blog/hello' => '/blog/three', '/blog/two' => '/blog/three']);

    app(SaveDraft::class)->handle($hello = $hello->fresh() ?? $hello, ['slug' => 'hello']);
    app(PublishEntry::class)->handle($hello->fresh() ?? $hello);

    get('/blog/hello')->assertOk();
    expect(Redirect::query()->pluck('to_url', 'from_path')->all())->each->toBe('/blog/hello');
});

it('doesn\'t redirect when a live page has the address', function () {
    seoPost($this->blog, 'Hello');
    Redirect::query()->create(['from_path' => '/blog/hello', 'to_url' => '/elsewhere', 'status' => 302]);

    get('/blog/hello')->assertOk();
});

it('lists live, indexable pages in the sitemap', function () {
    $hello = seoPost($this->blog, 'Hello');
    $id = app(CreateTranslation::class)->handle($hello->entry, 'id');
    app(SaveDraft::class)->handle($id, ['title' => 'Halo', 'slug' => 'halo']);
    app(PublishEntry::class)->handle($id);
    seoPost($this->blog, 'Draft', publish: false);
    seoPost($this->blog, 'Hidden', ['seo' => ['noindex' => true]]);
    Collection::query()->create(['handle' => 'snippets', 'title' => 'Snippets', 'blueprint' => 'post']);

    $xml = get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();

    expect($xml)->toContain('<loc>'.url('/blog/hello').'</loc>')
        ->toContain('<loc>'.url('/id/blog/halo').'</loc>')
        ->toContain('hreflang="id" href="'.url('/id/blog/halo').'"')
        ->not->toContain('draft')
        ->not->toContain('hidden');
    expect(simplexml_load_string((string) $xml))->not->toBeFalse();
    get('/sitemap-1.xml')->assertOk()->assertSee('<loc>'.url('/blog/hello').'</loc>', false);
    get('/sitemap-2.xml')->assertNotFound();
});

it('splits the sitemap into an index of files past the limit', function () {
    app()->bind(Sitemap::class, fn () => new Sitemap(app(SiteCache::class), limit: 2));
    foreach (range(1, 5) as $i) {
        seoPost($this->blog, "Post {$i}");
    }
    $tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags', 'route' => '/tags/{slug}']);
    $news = app(SaveTerm::class)->handle($tags, ['title' => 'News']);

    $index = get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
    expect($index)->toContain('<sitemapindex')->not->toContain('<url>');
    foreach ([1, 2, 3] as $n) {
        expect($index)->toContain('<loc>'.url("/sitemap-{$n}.xml").'</loc>');
    }
    expect($index)->not->toContain('sitemap-4.xml');
    expect(simplexml_load_string((string) $index))->not->toBeFalse();

    $all = '';
    foreach ([1, 2, 3] as $n) {
        $part = get("/sitemap-{$n}.xml")->assertOk()->getContent();
        expect(substr_count((string) $part, '<url>'))->toBe(2)->and(simplexml_load_string((string) $part))->not->toBeFalse();
        $all .= $part;
    }
    foreach (range(1, 5) as $i) {
        expect($all)->toContain('<loc>'.url("/blog/post-{$i}").'</loc>');
    }
    expect($all)->toContain('<loc>'.url('/tags/news').'</loc><lastmod>'.$news->translation()?->updated_at?->toAtomString().'</lastmod>');
    get('/sitemap-4.xml')->assertNotFound();
    get('/sitemap-0.xml')->assertNotFound();

    // Built once: every file is read from the cache afterwards.
    DB::enableQueryLog();
    get('/sitemap-2.xml')->assertOk();
    get('/sitemap.xml')->assertOk();
    expect(DB::getQueryLog())->toBe([]);
    DB::disableQueryLog();
});

it('guards redirects with their permissions', function () {
    actingAs(adminUser(Panel::ACCESS));
    get('/admin/redirects')->assertForbidden();

    actingAs(adminUser(Panel::ACCESS, 'cms.redirects.view', 'cms.redirects.create'));
    get('/admin/redirects')->assertOk();
    post('/admin/redirects', ['from_path' => '/old', 'to_url' => '/new', 'status' => '301'])->assertSessionHasNoErrors();
    post('/admin/redirects', ['from_path' => 'no-slash', 'to_url' => 'javascript:alert(1)', 'status' => '301'])
        ->assertSessionHasErrors(['from_path', 'to_url']);

    get('/old')->assertRedirect('/new');
});
