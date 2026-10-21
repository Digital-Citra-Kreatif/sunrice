<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Testing\TestResponse;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveEntrySettings;
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

it('describes the publisher with its own name, type, contact details and profiles', function () {
    \Pest\Laravel\actingAs(adminUser(Panel::ACCESS, 'cms.settings.site'));
    \Pest\Laravel\put('/admin/settings/site', ['structuredData' => true, 'publisher' => 'Organization', 'publisherType' => 'RealEstate Agent', 'sameAs' => 'not a url'])
        ->assertSessionHasErrors(['publisherType', 'sameAs']);
    \Pest\Laravel\put('/admin/settings/site', [
        'structuredData' => true, 'appendName' => true, 'separator' => '|', 'publisher' => 'Organization',
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
});
