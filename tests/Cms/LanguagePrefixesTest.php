<?php

declare(strict_types=1);

use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Settings\LocaleSettings;
use Sunrice\Cms\Site\Site;
use Sunrice\Cms\Support\Locales;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['sunrice-cms.locales' => ['en', 'id']]);
    actingAs(adminUser());
    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => []]);
    $this->pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page', 'route' => '/{slug}']);
    $about = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->pages, ['title' => 'About']));
    $tentang = app(SaveDraft::class)->handle(app(CreateTranslation::class)->handle($about->entry, 'id'), ['title' => 'Tentang', 'slug' => 'tentang']);
    $this->about = $about;
    $this->tentang = app(PublishEntry::class)->handle($tentang);
});

function languages(string $lines): void
{
    app(LocaleSettings::class)->fill(['languages' => $lines])->save();
    app()->forgetInstance(LocaleSettings::class);
    app()->forgetInstance(Site::class);
}

it('puts the main language at the plain addresses and the others under their code, by default', function () {
    expect(Locales::segments())->toBe(['en' => '', 'id' => 'id'])
        ->and(Locales::split('/id/tentang'))->toBe(['id', '/tentang', true])
        ->and(Locales::split('/about'))->toBe(['en', '/about', true]);
    get('/about')->assertOk();
    get('/id/tentang')->assertOk();
});

it('gives each language the address set for it, the main one too', function () {
    languages("en: English | en\nid: Bahasa Indonesia | indonesia");

    expect(Locales::prefix('en'))->toBe('/en')->and(Locales::prefix('id'))->toBe('/indonesia')
        ->and($this->about->fresh()?->url())->toBe(url('/en/about'))
        ->and($this->tentang->fresh()?->url())->toBe(url('/indonesia/tentang'));
    get('/en/about')->assertOk();
    get('/indonesia/tentang')->assertOk();
    get('/id/tentang')->assertRedirect('/en/id/tentang');
    // With no language at the plain addresses, they go to the main language's.
    get('/about?x=1')->assertRedirect('/en/about?x=1');
    get('/')->assertRedirect('/en');
});

it('checks the addresses in Settings → Languages', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.settings.locales'));
    put('/admin/settings/locales', ['languages' => "en: English | Bad Address\nid: Indonesia"])->assertSessionHasErrors('languages');
    put('/admin/settings/locales', ['languages' => "en: English | id\nid: Indonesia"])->assertSessionHasErrors(['languages' => 'Two languages have the same address. Give each its own.']);
    put('/admin/settings/locales', ['languages' => "en: English | /en/\nid: Indonesia"])->assertSessionHasNoErrors();
    app()->forgetInstance(LocaleSettings::class);

    expect(Locales::names())->toBe(['en' => 'English', 'id' => 'Indonesia'])
        ->and(Locales::segments())->toBe(['en' => 'en', 'id' => 'id']);
});
