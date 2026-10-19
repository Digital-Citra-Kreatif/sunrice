<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Redirect;
use Sunrice\Cms\Support\Seo;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['sunrice-cms.locales' => ['en', 'id']]);
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
        ->and(Seo::validate(['title' => ' ', 'noindex' => false]))->toBeNull()
        ->and(Seo::validate(null))->toBeNull();

    expect(fn () => Seo::validate(['title' => str_repeat('a', 256)]))->toThrow(ValidationException::class);
    expect(fn () => Seo::validate(['image' => seoImage('application/pdf')->id]))->toThrow(ValidationException::class);
    expect(fn () => Seo::validate('nope'))->toThrow(ValidationException::class);
});

it('puts SEO tags in the page head', function () {
    $image = seoImage();
    seoPost($this->blog, 'Hello', ['seo' => ['title' => 'Hello, search', 'description' => 'About <hello>', 'image' => $image->id]]);

    get('/blog/hello')->assertOk()
        ->assertSee('<title>Hello, search</title>', false)
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
        ->assertSee('<title>Halo</title>', false)
        ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/blog/hello').'">', false)
        ->assertSee('<link rel="alternate" hreflang="id" href="'.url('/id/blog/halo').'">', false);
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
