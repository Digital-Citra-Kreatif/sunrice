<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Permission;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Mail\SecurityAlert;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Entries\SaveEntrySettings;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Content;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Settings\CodeSettings;
use Sunrice\Cms\Settings\SiteSettings;
use Sunrice\Cms\Site\Site;
use Sunrice\Cms\Support\Seo;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['sunrice-cms.locales' => ['en', 'id'], 'app.name' => 'Acme']);
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [
        ['handle' => 'city', 'type' => 'text'],
        ['handle' => 'featured', 'type' => 'toggle'],
        ['handle' => 'event_date', 'type' => 'date'],
        ['handle' => 'related', 'type' => 'entries', 'config' => ['collections' => ['blog'], 'multiple' => true]],
        ['handle' => 'tags', 'type' => 'terms', 'config' => ['taxonomy' => 'tags', 'multiple' => true]],
    ]]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}', 'dated' => true, 'hierarchical' => true, 'listing_route' => '/blog']);
    $this->tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags', 'route' => '/tags/{slug}']);
    $this->tags->collections()->attach($this->blog);
});

function codePost(string $title, array $data = [], ?array $seo = null): EntryTranslation
{
    return app(PublishEntry::class)->handle(app(CreateEntry::class)->handle(test()->blog, ['title' => $title, 'data' => $data, 'seo' => $seo]));
}

function saveSite(array $values): void
{
    app(SiteSettings::class)->fill($values)->save();
    app(Site::class)->flush(); // as saving in the panel does
}

it('adds the site name to titles, and fills in what pages leave empty', function () {
    $image = Media::query()->create(['disk' => 'public', 'path' => 'a/share.jpg', 'filename' => 'share.jpg', 'mime_type' => 'image/jpeg', 'size' => 10]);
    saveSite(['name' => 'Acme News', 'description' => 'All the news.', 'separator' => '—', 'image' => $image->id, 'twitter' => 'acme']);
    codePost('Hello');

    get('/blog/hello')->assertOk()
        ->assertSee('<title>Hello — Acme News</title>', false)
        ->assertSee('<meta name="description" content="All the news.">', false)
        ->assertSee('<meta property="og:image" content="'.$image->url().'">', false)
        ->assertSee('<meta property="og:type" content="article">', false)
        ->assertSee('<meta property="og:site_name" content="Acme News">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
        ->assertSee('<meta name="twitter:site" content="@acme">', false);

    saveSite(['appendName' => false]);
    get('/blog/hello')->assertSee('<title>Hello</title>', false);
    expect(Content::site()->name())->toBe('Acme News');
});

it('shows the site name on the homepage, and the listing page\'s own title', function () {
    $home = codePost('Welcome');
    saveSite(['homepage' => $home->entry_id]);

    get('/')->assertOk()->assertSee('<title>Acme</title>', false)->assertSee('<meta property="og:type" content="website">', false);
    get('/blog')->assertOk()->assertSee('<title>Blog | Acme</title>', false)->assertSee('<link rel="canonical" href="'.url('/blog').'">', false)
        ->assertSee('<link rel="alternate" hreflang="id" href="'.url('/id/blog').'">', false)
        ->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/blog').'">', false);
});

it('uses an entry\'s own canonical address', function () {
    codePost('Copy', seo: ['canonical' => 'https://example.com/original']);

    get('/blog/copy')->assertSee('<link rel="canonical" href="https://example.com/original">', false);
});

it('hides the whole site from search engines', function () {
    codePost('Hello');
    saveSite(['noindex' => true]);

    get('/blog/hello')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    expect(get('/sitemap.xml')->assertOk()->getContent())->not->toContain('<url>');

    saveSite(['noindex' => false]);
    get('/blog/hello')->assertHeaderMissing('X-Robots-Tag');
    config(['sunrice-cms.seo.noindex' => 'true']); // SUNRICE_NOINDEX on a staging copy
    get('/blog/hello')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('edits the site settings behind their permission', function () {
    actingAs(adminUser(Panel::ACCESS));
    get('/admin/settings/site')->assertForbidden();

    actingAs(adminUser(Panel::ACCESS, 'cms.settings.site'));
    put('/admin/settings/site', ['name' => 'Acme', 'twitter' => 'not a handle!'])->assertSessionHasErrors('twitter');
    put('/admin/settings/site', ['name' => 'Acme', 'twitter' => '@acme', 'separator' => '·', 'appendName' => true])->assertSessionHasNoErrors();
    expect(app(SiteSettings::class)->title('About'))->toBe('About · Acme');
});

it('prints the site code, and alerts when it changes', function () {
    Mail::fake();
    config(['sunrice-admin.security.alerts.emails' => ['security@example.com']]);
    codePost('Hello');
    actingAs(adminUser(Panel::ACCESS, 'cms.settings.site'));
    put('/admin/settings/code', ['head' => '<script>x</script>'])->assertForbidden();

    actingAs($ana = adminUser(Panel::ACCESS, 'cms.settings.code'));
    put('/admin/settings/code', ['head' => '<script src="https://stats.example.com/a.js"></script>', 'bodyEnd' => '<div id="chat"></div>'])->assertSessionHasNoErrors();

    get('/blog/hello')->assertSee('<script src="https://stats.example.com/a.js"></script>', false)->assertSee('<div id="chat"></div>', false);
    expect(Activity::query()->where('action', 'site code changed')->sole()->properties['note'])->toBe('head, end of the body');
    Mail::assertQueued(SecurityAlert::class, fn (SecurityAlert $mail) => $mail->headline === 'The site\'s code was changed' && $mail->hasTo('security@example.com'));
    expect(app(CodeSettings::class)->at('body-start'))->toBe('')->and(app(CodeSettings::class)->at('nowhere'))->toBe('');

    // Saving it unchanged is no event.
    put('/admin/settings/code', ['head' => '<script src="https://stats.example.com/a.js"></script>', 'bodyEnd' => '<div id="chat"></div>']);
    expect(Activity::query()->where('action', 'site code changed')->count())->toBe(1);
});

it('gives the body classes of the page shown', function () {
    $hello = codePost('Hello');
    $child = codePost('Child');
    app(SaveEntrySettings::class)->handle($child->entry, ['parent_id' => $hello->entry_id]);
    $news = app(SaveTerm::class)->handle($this->tags, ['title' => 'News']);

    expect(get('/blog/child')->getContent())->toContain('<body class="page-entry collection-blog entry-'.$child->entry_id.' entry-child has-parent parent-'.$hello->entry_id.' template-sunrice-cms-entry lang-en">');
    expect(get('/blog')->getContent())->toContain('<body class="page-listing collection-blog template-sunrice-cms-listing lang-en">');
    expect(get('/tags/news')->getContent())->toContain('<body class="page-term taxonomy-tags term-'.$news->id.' term-news template-sunrice-cms-term lang-en">');
    app()->instance('request', Request::create('/contact')); // one of the app's own pages
    expect(Content::bodyClass('Dark  wide'))->toBe('lang-en dark wide'); // outside a CMS page
    expect(Content::page())->toBeNull()->and(Content::localeUrls())->toBe([]);
});

it('gives the page in each language for a switcher', function () {
    $hello = codePost('Hello');
    $halo = app(CreateTranslation::class)->handle($hello->entry, 'id');
    app(SaveDraft::class)->handle($halo, ['title' => 'Halo', 'slug' => 'halo']);
    app(PublishEntry::class)->handle($halo);
    View::addNamespace('switch', __DIR__.'/Fixtures/views');
    $this->blog->update(['template' => 'switch::cms.switcher']);

    expect(json_decode((string) get('/id/blog/halo')->getContent(), true))->toBe(['en' => url('/blog/hello'), 'id' => url('/id/blog/halo')]);
});

it('lists entries with the entries component and its filters', function () {
    $a = codePost('Alpha', ['city' => 'Jakarta', 'featured' => true, 'event_date' => '2026-03-01']);
    $b = codePost('Beta', ['city' => 'Bandung', 'event_date' => '2026-01-01', 'related' => [$a->entry_id]]);
    $c = codePost('Gamma', ['city' => 'Jakarta', 'featured' => true, 'event_date' => '2026-02-01', 'related' => [$a->entry_id, $b->entry_id]]);
    $titles = fn (string $props) => trim(Blade::render('<x-sunrice-cms::entries collection="blog" '.$props.'>@foreach ($component->entries as $e){{ $e->title }} @endforeach</x-sunrice-cms::entries>', ['a' => $a, 'c' => $c]));

    expect($titles('order="alphabetical"'))->toBe('Alpha Beta Gamma')
        ->and($titles('order="-event_date"'))->toBe('Alpha Gamma Beta')
        ->and($titles('order="event_date" :limit="2"'))->toBe('Beta Gamma')
        ->and($titles(':where="[\'featured\' => true]" order="alphabetical"'))->toBe('Alpha Gamma')
        ->and($titles(':where="[\'city\' => \'Jakarta\']" :except="$c"'))->toBe('Alpha')
        ->and($titles(':paginate="2" order="alphabetical"'))->toBe('Alpha Beta');

    expect(Content::entries('blog')->whereEntry('related', $a)->alphabetical()->get()->pluck('title')->all())->toBe(['Beta', 'Gamma'])
        ->and(Content::entries('blog')->whereEntry('related', [$b->entry_id])->get()->pluck('title')->all())->toBe(['Gamma'])
        ->and(Content::entries('blog')->where('city', '!=', 'Jakarta')->get()->pluck('title')->all())->toBe(['Beta'])
        ->and(Content::entries('blog')->where('event_date', '>=', '2026-02-01')->orderBy('event_date')->get()->pluck('title')->all())->toBe(['Gamma', 'Alpha'])
        ->and(Content::entries('blog')->where('subtitle', null)->count())->toBe(3)
        ->and(Content::entries('blog')->where('title', 'Beta')->first()?->is($b))->toBeTrue()
        ->and(fn () => Content::entries('blog')->where('city', 'like', '%a%'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Content::entries('blog')->where('data->x', 'y'))->toThrow(InvalidArgumentException::class);
});

it('lists terms with their live entries\' counts, as a tree', function () {
    $categories = Taxonomy::query()->create(['handle' => 'categories', 'title' => 'Categories', 'hierarchical' => true]);
    $categories->collections()->attach($this->blog);
    Collection::find($this->blog->id)?->flushSchema();
    $news = app(SaveTerm::class)->handle($this->tags, ['title' => 'News']);
    app(SaveTerm::class)->handle($this->tags, ['title' => 'Empty']);
    $local = app(SaveTerm::class)->handle($categories, ['title' => 'Local']);
    app(SaveTerm::class)->handle($categories, ['title' => 'City', 'parent_id' => $local->id]);
    $post = codePost('Hello', ['tags' => [$news->id]]);
    app(CreateEntry::class)->handle($this->blog, ['title' => 'Draft', 'data' => ['tags' => [$news->id]]]);

    $render = fn (string $props, array $data = []) => trim(Blade::render('<x-sunrice-cms::terms '.$props.'>@foreach ($component->terms as $t){{ $t->title() }}:{{ $t->entries_count }}@if ($t->relationLoaded(\'children\'))[@foreach ($t->children as $c){{ $c->title() }}@endforeach]@endif @endforeach</x-sunrice-cms::terms>', $data));

    expect($render('taxonomy="tags"'))->toBe('News:1 Empty:0')
        ->and($render('taxonomy="tags" hide-empty'))->toBe('News:1')
        ->and($render('taxonomy="tags" :entry="$post"', ['post' => $post]))->toBe('News:1')
        ->and($render('taxonomy="tags" collection="nothing"'))->toBe('News:0 Empty:0')
        ->and($render('taxonomy="categories" tree'))->toBe('Local:0[City]')
        ->and($render('taxonomy="nope"'))->toBe('');
});

it('offers live pages as the homepage, searchable, and lets editors set it from the entry', function () {
    $home = codePost('Welcome');
    $draft = app(CreateEntry::class)->handle($this->blog, ['title' => 'Draft page']);
    $homepageField = fn (): array => collect(SiteSettings::form(new Form)->fields('edit'))->first(fn ($field) => $field->name === 'homepage')->toArray();
    $homepage = $homepageField();
    expect($homepage['searchable'])->toBeTrue()
        ->and(array_column($homepage['options'], 'label'))->toBe(['Blog: Welcome']);
    // The homepage as set stays offered (marked), so other settings still save.
    saveSite(['homepage' => $draft->entry_id]);
    $options = array_column($homepageField()['options'], 'label', 'value');
    expect($options[(string) $draft->entry_id])->toBe('Blog: Draft page (not live)');

    // Editors (cms.homepage) set it from the entry: a live page only.
    $budi = adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update');
    actingAs($budi);
    \Pest\Laravel\post("/admin/cms/entries/{$home->entry_id}/homepage")->assertForbidden();
    $budi->givePermissionTo(Permission::findOrCreate('cms.homepage', 'web'));
    \Pest\Laravel\post("/admin/cms/entries/{$draft->entry_id}/homepage")->assertSessionHasErrors('homepage');
    \Pest\Laravel\post("/admin/cms/entries/{$home->entry_id}/homepage")->assertSessionHasNoErrors();
    expect((new SiteSettings)->homepage)->toBe($home->entry_id);
    get('/')->assertOk()->assertSee('Welcome');
    expect(app(Panel::class)->getDefaultRoles()['editor'] ?? [])->toContain('cms.homepage');
});

it('previews titles with the saved separator and site name', function () {
    app(SiteSettings::class)->fill(['name' => 'Lumina Estates', 'separator' => '·'])->save();
    $help = collect(SiteSettings::form(new Form)->fields('edit'))->firstWhere('name', 'appendName')->toArray()['help'];

    expect($help)->toBe('"About · Lumina Estates" in search results and browser tabs.');
});

it('describes the site in each language', function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    $site = app(SiteSettings::class);
    $site->fill(['description' => 'Premium homes.', 'descriptions' => "id: Rumah premium.\nfr: Maisons."])->save();

    expect($site->descriptionIn('id'))->toBe('Rumah premium.')
        ->and($site->descriptionIn('en'))->toBe('Premium homes.')
        ->and($site->descriptionIn('de'))->toBe('Premium homes.')
        ->and(Seo::forPage(null)['description'])->toBe('Premium homes.');
    app()->setLocale('id');
    expect(Seo::forPage(null)['description'])->toBe('Rumah premium.');
});
