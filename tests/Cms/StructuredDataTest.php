<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Testing\TestResponse;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Media\MediaUsages;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Entries\SaveEntrySettings;
use Sunrice\Cms\Actions\Seo\SaveSeoSettings;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Settings\SiteSettings;
use Sunrice\Cms\Site\Site;
use Sunrice\Cms\Support\StructuredData;

use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['app.name' => 'Acme']);
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [['handle' => 'intro', 'type' => 'text']]]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}', 'dated' => true, 'listing_route' => '/blog']);
    $this->pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'post', 'route' => '/{slug}', 'hierarchical' => true]);
});

function schemaPost(Collection $collection, string $title, ?array $seo = null): EntryTranslation
{
    return app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($collection, ['title' => $title, 'seo' => $seo]));
}

/**
 * Saves Settings → SEO with these structured data values (the others as
 * they are), as its page sends them.
 *
 * @param  array<string, mixed>  $structured
 */
function saveStructured(array $structured): TestResponse
{
    $site = app(SiteSettings::class);

    return \Pest\Laravel\put('/admin/settings/seo', [
        'site' => ['noindex' => $site->noindex, 'appendName' => $site->appendName, 'separator' => $site->separator, 'twitter' => $site->twitter, 'image' => $site->image],
        'structured' => [...SaveSeoSettings::structuredData()->props()['values'], ...$structured],
    ]);
}

/** Structured data's field `$name` as Settings → SEO shows it. */
function structuredField(string $name): array
{
    $props = get('/admin/settings/seo')->assertOk()->viewData('page')['props'];

    return collect($props['structured']['sections'])->flatMap(fn (array $section) => $section['fields'])->firstWhere('name', $name);
}

/** The page's JSON-LD nodes by @type. */
function nodes(TestResponse $response): array
{
    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', (string) $response->getContent(), $m);
    $data = json_decode($m[1] ?? 'null', true);
    expect($data['@context'] ?? null)->toBe('https://schema.org');

    return collect($data['@graph'])->keyBy('@type')->all();
}

it('describes an article, the website with its search box, and who publishes it', function () {
    travelTo('2026-10-01 09:00');
    $logo = Media::query()->create(['disk' => 'public', 'path' => 'a/logo.png', 'filename' => 'logo.png', 'mime_type' => 'image/png', 'size' => 10]);
    app(SiteSettings::class)->fill(['logo' => $logo->id, 'description' => 'News from Acme.'])->save();
    schemaPost($this->blog, 'Hello world', ['description' => 'A first post.']);

    $nodes = nodes(get('/blog/hello-world')->assertOk());

    expect($nodes['WebSite'])->toMatchArray(['url' => url('/'), 'name' => 'Acme', 'inLanguage' => 'en', 'description' => 'News from Acme.'])
        ->and($nodes['WebSite']['potentialAction']['target']['urlTemplate'])->toBe(url('/search').'?q={search_term_string}')
        ->and($nodes['Organization'])->toMatchArray(['name' => 'Acme', 'logo' => ['@type' => 'ImageObject', 'url' => $logo->url()]])
        ->and($nodes['Article'])->toMatchArray([
            'url' => url('/blog/hello-world'),
            'headline' => 'Hello world',
            'description' => 'A first post.',
            'datePublished' => '2026-10-01T09:00:00+00:00',
            'author' => ['@id' => url('/').'#publisher'],
            'breadcrumb' => ['@id' => url('/blog/hello-world').'#breadcrumb'],
        ])
        ->and(array_column($nodes['BreadcrumbList']['itemListElement'], 'name'))->toBe(['Home', 'Blog', 'Hello world'])
        ->and($nodes['BreadcrumbList']['itemListElement'][1]['item'])->toBe(url('/blog'));
});

it('dates an article modified when it was last published, not when a draft was saved', function () {
    travelTo('2026-10-01 09:00');
    $post = schemaPost($this->blog, 'Hello world');

    travelTo('2026-10-05 12:00');
    app(SaveDraft::class)->handle($post->fresh(), ['title' => 'Hello again']);
    $page = get('/blog/hello-world')->assertOk();
    expect(nodes($page)['Article'])->toMatchArray(['datePublished' => '2026-10-01T09:00:00+00:00', 'dateModified' => '2026-10-01T09:00:00+00:00']);
    $page->assertSee('<meta property="article:modified_time" content="2026-10-01T09:00:00+00:00">', false);
    expect((string) get('/sitemap.xml')->getContent())->toContain('<lastmod>2026-10-01T09:00:00+00:00</lastmod>');

    travelTo('2026-10-07 08:00');
    app(PublishEntry::class)->handle($post->fresh());
    expect(nodes(get('/blog/hello-world')->assertOk())['Article']['dateModified'])->toBe('2026-10-07T08:00:00+00:00');
});

it('uses the collection\'s type, and breadcrumbs through parent pages', function () {
    $this->pages->update(['schema_type' => 'AboutPage']);
    $about = schemaPost($this->pages, 'About');
    $team = schemaPost($this->pages, 'Team');
    app(SaveEntrySettings::class)->handle($team->entry, ['parent_id' => $about->entry_id]);

    $nodes = nodes(get('/team')->assertOk());

    expect($nodes)->toHaveKey('AboutPage')->not->toHaveKey('Article')
        ->and($nodes['AboutPage'])->not->toHaveKey('headline')
        ->and(array_column($nodes['BreadcrumbList']['itemListElement'], 'name'))->toBe(['Home', 'About', 'Team']);
});

it('describes listing, term and search pages, and the homepage without breadcrumbs', function () {
    $home = schemaPost($this->pages, 'Welcome');
    app(SiteSettings::class)->fill(['homepage' => $home->entry_id])->save();
    app(Site::class)->flush();
    $tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags', 'route' => '/tags/{slug}']);
    app(SaveTerm::class)->handle($tags, ['title' => 'News']);

    expect(nodes(get('/blog'))['CollectionPage']['name'])->toBe('Blog')
        ->and(nodes(get('/tags/news'))['CollectionPage']['name'])->toBe('News')
        ->and(nodes(get('/search?q=x')))->toHaveKey('SearchResultsPage')
        ->and(nodes(get('/')))->toHaveKey('WebPage')->not->toHaveKey('BreadcrumbList');
});

it('takes nodes from templates, prints them safely, and can be turned off', function () {
    $html = Blade::render('<x-sunrice-cms::seo title="Events" :schema="[[\'@type\' => \'Event\', \'name\' => \'</script><script>alert(1)</script>\']]" />');
    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

    expect($m[1])->not->toContain('</script')->not->toContain('<script')
        ->and(collect(json_decode($m[1], true)['@graph'])->firstWhere('@type', 'Event')['name'])->toBe('</script><script>alert(1)</script>')
        ->and(StructuredData::graph(null))->toHaveCount(2); // the app's own pages: the website and its publisher

    app(SiteSettings::class)->fill(['structuredData' => false, 'publisher' => 'Person'])->save();
    expect(Blade::render('<x-sunrice-cms::seo title="Events" />'))->not->toContain('application/ld+json');
});

it('describes the publisher with its own name, type, contact details and profiles, set in Settings → SEO', function () {
    \Pest\Laravel\actingAs(adminUser(Panel::ACCESS, 'cms.settings.site'));
    saveStructured(['structuredData' => true, 'publisher' => 'Organization', 'publisherType' => 'RealEstate Agent', 'sameAs' => 'not a url'])
        ->assertSessionHasErrors(['structured.publisherType', 'structured.sameAs']);
    saveStructured([
        'structuredData' => true, 'publisher' => 'Organization',
        'publisherName' => 'Lumina Estates PT', 'publisherType' => 'RealEstateAgent', 'telephone' => '+62 21 555 0100',
        'address' => "Jl. Senopati 10\nJakarta 12190, Indonesia", 'sameAs' => "https://instagram.com/lumina\n\nhttps://www.linkedin.com/company/lumina",
    ])->assertSessionHasNoErrors();
    schemaPost($this->blog, 'Hello world');

    expect(nodes(get('/blog/hello-world')->assertOk())['RealEstateAgent'])->toMatchArray([
        '@id' => url('/').'#publisher',
        'name' => 'Lumina Estates PT',
        'telephone' => '+62 21 555 0100',
        'address' => 'Jl. Senopati 10, Jakarta 12190, Indonesia',
        'sameAs' => ['https://instagram.com/lumina', 'https://www.linkedin.com/company/lumina'],
    ]);
    // The page shows what's saved; turned off there, pages have none.
    expect(get('/admin/settings/seo')->viewData('page')['props']['structured']['values'])->toMatchArray(['publisherName' => 'Lumina Estates PT', 'structuredData' => true]);
    saveStructured(['structuredData' => false])->assertSessionHasNoErrors();
    expect((string) get('/blog/hello-world')->getContent())->not->toContain('application/ld+json');
});

it('offers common organization types by group, and takes any other schema.org type', function () {
    \Pest\Laravel\actingAs(adminUser(Panel::ACCESS, 'cms.settings.site'));
    $field = structuredField('publisherType');
    $options = collect($field['options']);
    expect($field['type'])->toBe('select')->and($field['searchable'])->toBeTrue()->and($field['other']['label'])->toBe('Other…')
        ->and($options->firstWhere('value', 'RealEstateAgent'))->toBe(['value' => 'RealEstateAgent', 'label' => 'Real estate agent', 'group' => 'Local businesses'])
        ->and($options->firstWhere('value', 'LegalService')['label'])->toBe('Law firm or legal service');

    saveStructured(['publisherType' => 'Brewery'])->assertSessionHasNoErrors();
    expect(app(SiteSettings::class)->publisherType)->toBe('Brewery');
    saveStructured(['publisherType' => 'brewery!'])->assertSessionHasErrors('structured.publisherType');
    saveStructured(['publisherType' => ''])->assertSessionHasNoErrors();
});

it('edits the profiles elsewhere as a list of https addresses, kept one per line', function () {
    \Pest\Laravel\actingAs(adminUser(Panel::ACCESS, 'cms.settings.site'));
    $field = structuredField('sameAs');
    expect($field['type'])->toBe('list')->and($field['accepts'])->toBe('url')->and($field['networks'])->toBeTrue()->and($field['addLabel'])->toBe('Add profile');

    saveStructured(['sameAs' => "https://instagram.com/lumina\n\nhttp://example.com"])->assertSessionHasErrors('structured.sameAs');
    saveStructured(['sameAs' => " https://instagram.com/lumina \n\nhttps://x.com/lumina\n"])->assertSessionHasNoErrors();
    expect(app(SiteSettings::class)->sameAs)->toBe("https://instagram.com/lumina\nhttps://x.com/lumina")
        ->and(app(SiteSettings::class)->profiles())->toBe(['https://instagram.com/lumina', 'https://x.com/lumina']);
});

it('keeps structured data off Settings → Site, and its fields out of what Site saves', function () {
    \Pest\Laravel\actingAs(adminUser(Panel::ACCESS, 'cms.settings.site'));
    \Pest\Laravel\get('/admin/settings/site')->assertInertia(fn ($page) => $page
        ->where('section.description', 'The homepage, and the site\'s name and description.')
        ->where('form', fn ($sections): bool => collect($sections)->pluck('title')->doesntContain('Structured data')
            && collect($sections)->flatMap(fn ($section) => $section['fields'])->pluck('name')->intersect(['structuredData', 'publisher', 'logo', 'sameAs'])->isEmpty()));
    \Pest\Laravel\put('/admin/settings/site', ['name' => 'Acme', 'publisherName' => 'Ignored'])->assertSessionHasNoErrors();
    expect(app(SiteSettings::class)->publisherName)->toBe('')
        ->and(app(SiteSettings::class)->structuredData)->toBeTrue();
});

it('describes people, places and products as what a page is about', function (string $type, string $page) {
    $agents = Collection::query()->create(['handle' => 'agents', 'title' => 'Agents', 'blueprint' => 'post', 'route' => '/team/{slug}', 'schema_type' => $type]);
    schemaPost($agents, 'Ayu Lestari', ['description' => 'Senior agent in Canggu.']);

    $nodes = nodes(get('/team/ayu-lestari')->assertOk());

    expect($nodes[$page])->toMatchArray(['url' => url('/team/ayu-lestari'), 'name' => 'Ayu Lestari'])
        ->and($nodes[$page]['mainEntity'])->toBe(['@type' => $type, '@id' => url('/team/ayu-lestari').'#main', 'name' => 'Ayu Lestari', 'url' => url('/team/ayu-lestari'), 'description' => 'Senior agent in Canggu.'])
        ->and($nodes)->toHaveKey('BreadcrumbList');
})->with([['Person', 'ProfilePage'], ['Place', 'WebPage'], ['Product', 'ItemPage']]);

it('describes a real estate listing as its page', function () {
    $homes = Collection::query()->create(['handle' => 'homes', 'title' => 'Homes', 'blueprint' => 'post', 'route' => '/homes/{slug}', 'schema_type' => 'RealEstateListing']);
    schemaPost($homes, 'Villa Damai');

    expect(nodes(get('/homes/villa-damai')->assertOk())['RealEstateListing'])->toMatchArray(['name' => 'Villa Damai'])->not->toHaveKey('mainEntity');
});

it('names listings, collections and the way home in the page\'s language', function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    $this->blog->translate('title', 'id', 'Jurnal')->save();
    schemaPost($this->blog, 'Hello world');

    $nodes = nodes(get('/id/blog/hello-world')->assertOk());
    expect(array_column($nodes['BreadcrumbList']['itemListElement'], 'name'))->toBe(['Beranda', 'Jurnal', 'Hello world'])
        ->and(array_column($nodes['BreadcrumbList']['itemListElement'], 'item'))->toBe([url('/id'), url('/id/blog'), url('/id/blog/hello-world')]);

    $listing = get('/id/blog')->assertOk()->assertSee('<title>Jurnal | Acme</title>', false)->assertSee('<h1>Jurnal</h1>', false);
    expect(nodes($listing)['CollectionPage']['name'])->toBe('Jurnal');
    get('/blog')->assertOk()->assertSee('<h1>Blog</h1>', false);
    expect($this->blog->titleIn('en'))->toBe('Blog')->and($this->blog->fresh()?->titleIn('id'))->toBe('Jurnal');
});

it('lists the logo and share image as used by Settings → SEO in the media library', function () {
    $logo = Media::query()->create(['disk' => 'public', 'path' => 'logo.png', 'filename' => 'logo.png', 'mime_type' => 'image/png', 'size' => 10]);
    $image = Media::query()->create(['disk' => 'public', 'path' => 'share.png', 'filename' => 'share.png', 'mime_type' => 'image/png', 'size' => 10]);
    \Pest\Laravel\actingAs(adminUser(Panel::ACCESS, 'cms.settings.site'));
    saveStructured(['logo' => $logo->id])->assertSessionHasNoErrors();
    \Pest\Laravel\put('/admin/settings/seo', ['site' => ['noindex' => false, 'appendName' => true, 'separator' => '|', 'twitter' => '', 'image' => $image->id]])->assertSessionHasNoErrors();

    $usedBy = fn (Media $media) => app(MediaUsages::class)->of($media);
    expect($usedBy($logo))->toBe([['type' => 'cms.settings', 'label' => 'Settings: SEO', 'url' => route('sunrice.admin.settings.seo.edit')]])
        ->and($usedBy($image))->toBe([['type' => 'cms.settings', 'label' => 'Settings: SEO', 'url' => route('sunrice.admin.settings.seo.edit')]]);
    // Settings → Site has no media pickers left: it uses none.
    \Pest\Laravel\put('/admin/settings/site', ['name' => 'Acme'])->assertSessionHasNoErrors();
    expect($usedBy($logo))->toHaveCount(1);
});

it('moves the logo\'s use from Settings → Site to Settings → SEO when migrating', function () {
    $logo = Media::query()->create(['disk' => 'public', 'path' => 'logo.png', 'filename' => 'logo.png', 'mime_type' => 'image/png', 'size' => 10]);
    app(SiteSettings::class)->fill(['logo' => $logo->id])->save();
    app(MediaUsages::class)->sync('sunrice.settings', 'site', [$logo->id], 'Settings: Site'); // as 0.1 recorded it

    $migration = require dirname(__DIR__, 2).'/packages/cms/database/migrations/2026_10_24_000010_record_seo_settings_media.php';
    $migration->up();
    $migration->up();

    expect(array_column(app(MediaUsages::class)->of($logo), 'label'))->toBe(['Settings: SEO']);
});
