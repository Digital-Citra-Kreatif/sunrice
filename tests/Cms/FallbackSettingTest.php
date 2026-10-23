<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Sunrice\Admin\Actions\Settings\SaveSettings;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Content;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Settings\SiteSettings;
use Sunrice\Cms\Site\Site;
use Sunrice\Cms\Structure\ImportReport;
use Sunrice\Cms\Structure\StructureExporter;
use Sunrice\Cms\Structure\StructureImporter;
use Sunrice\Cms\Support\SiteCache;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['sunrice-cms.locales' => ['en', 'id']]);
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [['handle' => 'intro', 'type' => 'text']]]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}', 'listing_route' => '/blog']);
});

function fallbackPost(Collection $collection, string $title): EntryTranslation
{
    return app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($collection, ['title' => $title]));
}

/** @return array<string, mixed>|null the fallback field on Settings → Site, as the page gets it */
function fallbackField(): ?array
{
    $form = get('/admin/settings/site')->assertOk()->viewData('page')['props']['form'];

    return collect($form)->pluck('fields')->flatten(1)->firstWhere('name', 'fallback');
}

function setFallback(bool $on): void
{
    app(SaveSettings::class)->handle(new SiteSettings, ['fallback' => $on]);
}

it('shows untranslated pages in the main language by default', function () {
    fallbackPost($this->blog, 'Hello');

    expect(config('sunrice-cms.site.fallback'))->toBeNull()
        ->and(Site::fallbackForced())->toBeNull()
        ->and((new SiteSettings)->fallback)->toBeTrue()
        ->and(Site::fallback())->toBeTrue();
    get('/id/blog/hello')->assertOk()->assertSee('Hello');
    expect(Content::entries('blog')->locale('id')->get()->pluck('title')->all())->toBe(['Hello']);
});

it('answers 404 for untranslated pages and lists only what\'s translated with the setting off', function () {
    $hello = fallbackPost($this->blog, 'Hello');
    fallbackPost($this->blog, 'Coffee');
    $halo = app(CreateTranslation::class)->handle($hello->entry, 'id');
    app(SaveDraft::class)->handle($halo, ['title' => 'Halo', 'slug' => 'halo']);
    app(PublishEntry::class)->handle($halo->refresh());

    setFallback(false);

    expect(Site::fallback())->toBeFalse();
    get('/id/blog/coffee')->assertNotFound();
    get('/id/blog/halo')->assertOk();
    get('/blog/coffee')->assertOk();
    expect(Content::entries('blog')->locale('id')->get()->pluck('title')->all())->toBe(['Halo']);
    get('/id/blog')->assertOk()->assertSee('Halo')->assertDontSee('Coffee');

    setFallback(true);
    get('/id/blog/coffee')->assertOk();
});

it('lets SUNRICE_FALLBACK force it whatever the setting says', function () {
    fallbackPost($this->blog, 'Hello');
    setFallback(false);

    config(['sunrice-cms.site.fallback' => true]);
    expect(Site::fallbackForced())->toBeTrue()->and(Site::fallback())->toBeTrue();
    get('/id/blog/hello')->assertOk();

    setFallback(true);
    config(['sunrice-cms.site.fallback' => 'false']); // as env() gives it from a quoted .env value
    expect(Site::fallbackForced())->toBeFalse()->and(Site::fallback())->toBeFalse();
    get('/id/blog/hello')->assertNotFound();

    config(['sunrice-cms.site.fallback' => null]);
    expect(Site::fallback())->toBeTrue();
});

it('edits it on Settings → Site behind its permission, disabled while the config sets it', function () {
    actingAs(adminUser(Panel::ACCESS));
    get('/admin/settings/site')->assertForbidden();
    put('/admin/settings/site', ['fallback' => false])->assertForbidden();
    expect((new SiteSettings)->fallback)->toBeTrue();

    actingAs(adminUser(Panel::ACCESS, 'cms.settings.site'));
    $field = fallbackField();
    expect($field)->not->toBeNull()
        ->and($field['type'])->toBe('toggle')
        ->and($field['label'])->toBe('Show untranslated pages in the main language')
        ->and($field['disabled'])->toBeFalse()
        ->and($field)->not->toHaveKey('developerHelp');
    expect(get('/admin/settings/site')->viewData('page')['props']['values']['fallback'])->toBeTrue();

    put('/admin/settings/site', ['fallback' => false])->assertSessionHasNoErrors();
    expect((new SiteSettings)->fallback)->toBeFalse()->and(Site::fallback())->toBeFalse();

    // Forced: shown disabled, saying why, and saving leaves the setting alone.
    config(['sunrice-cms.site.fallback' => true]);
    $field = fallbackField();
    expect($field['disabled'])->toBeTrue()
        ->and($field['developerHelp']['text'])->toBe('Set by SUNRICE_FALLBACK in the app\'s config.');
    put('/admin/settings/site', ['fallback' => true, 'name' => 'Acme'])->assertSessionHasNoErrors();
    expect((new SiteSettings)->fallback)->toBeFalse()->and((new SiteSettings)->name)->toBe('Acme');
});

it('shows it only when the site has more than one language', function () {
    config(['sunrice-cms.locales' => ['en']]);
    actingAs(adminUser(Panel::ACCESS, 'cms.settings.site'));

    expect(fallbackField())->toBeNull();
});

it('clears the site\'s caches when it changes', function () {
    fallbackPost($this->blog, 'Hello');
    get('/id/blog/hello')->assertOk();
    $versions = fn (): array => array_map(fn (string $area): int => app(SiteCache::class)->version($area), SiteCache::AREAS);
    $before = $versions();

    actingAs(adminUser(Panel::ACCESS, 'cms.settings.site'));
    put('/admin/settings/site', ['fallback' => false])->assertSessionHasNoErrors();

    expect($versions())->toBe(array_map(fn (int $version): int => $version + 1, $before));
    auth()->logout();
    get('/id/blog/hello')->assertNotFound();
});

it('reads the setting once per request', function () {
    Site::fallback();
    DB::enableQueryLog();
    foreach (range(1, 5) as $i) {
        Site::fallback();
    }

    expect(DB::getQueryLog())->toBe([]);
});

it('carries it in the structure export\'s settings', function () {
    setFallback(false);
    $file = json_decode(json_encode(app(StructureExporter::class)->export(settings: true), JSON_THROW_ON_ERROR), true);
    expect($file['settings']['site']['fallback'])->toBeFalse();

    setFallback(true);
    $report = app(StructureImporter::class)->import($file);
    expect($report->status('settings', 'site'))->toBe(ImportReport::UPDATED)
        ->and((new SiteSettings)->fallback)->toBeFalse();
});
