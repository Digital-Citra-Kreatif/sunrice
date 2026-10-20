<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Settings\LocaleSettings;
use Sunrice\Cms\Support\Locales;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['sunrice-cms.locales' => ['en', 'id'], 'sunrice-cms.locale_names' => ['id' => 'Bahasa Indonesia']]);
});

it('reads the languages from config until they are set in Settings', function () {
    expect(Locales::all())->toBe(['en', 'id'])
        ->and(Locales::default())->toBe('en')
        ->and(Locales::name('id'))->toBe('Bahasa Indonesia')
        ->and(Locales::name('fr'))->toBe('fr')
        ->and(Locales::prefix('en'))->toBe('')
        ->and(Locales::prefix('id'))->toBe('/id');

    app(LocaleSettings::class)->fill(['languages' => "id: Indonesia\nen: English\n\nnot a code\nfr"])->save();
    app()->forgetInstance(LocaleSettings::class);

    expect(Locales::names())->toBe(['id' => 'Indonesia', 'en' => 'English', 'fr' => Locales::displayName('fr')])
        ->and(Locales::default())->toBe('id')
        ->and(Locales::has('fr'))->toBeTrue()
        ->and(Locales::prefix('en'))->toBe('/en')
        ->and(Locales::prefix('id'))->toBe('');
});

it('edits the languages behind their permission, and keeps the main one while it holds content', function () {
    actingAs(adminUser(Panel::ACCESS));
    get('/admin/settings/locales')->assertForbidden();

    actingAs(adminUser(Panel::ACCESS, 'cms.settings.locales'));
    get('/admin/settings/locales')->assertOk();
    put('/admin/settings/locales', ['languages' => ''])->assertSessionHasErrors('languages');
    put('/admin/settings/locales', ['languages' => "en: English\nEnglish!"])->assertSessionHasErrors('languages');
    put('/admin/settings/locales', ['languages' => "en: English\nen: Again"])->assertSessionHasErrors('languages');

    // No content yet: any language may be the main one.
    put('/admin/settings/locales', ['languages' => "id: Bahasa Indonesia\nen: English"])->assertSessionHasNoErrors();
    expect(Locales::all())->toBe(['id', 'en']);

    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => []]);
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page']);
    $page = app(CreateEntry::class)->handle($pages, ['title' => 'Halo']);
    app(CreateTranslation::class)->handle($page->entry, 'en');

    put('/admin/settings/locales', ['languages' => "en: English\nid: Bahasa Indonesia"])->assertSessionHasErrors('languages');
    put('/admin/settings/locales', ['languages' => 'en: English'])->assertSessionHasErrors('languages');
    // Renaming, and adding or dropping another language, is fine.
    put('/admin/settings/locales', ['languages' => "id: Indonesia\nfr: Français"])->assertSessionHasNoErrors();
    expect(Locales::names())->toBe(['id' => 'Indonesia', 'fr' => 'Français']);
});

it('names the languages in the editor and the list', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view'));
    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => []]);
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page']);
    $page = app(CreateEntry::class)->handle($pages, ['title' => 'Hello']);

    get("/admin/cms/entries/{$page->entry_id}/edit")->assertInertia(fn (Assert $page) => $page
        ->where('locales.0', ['code' => 'en', 'name' => Locales::displayName('en'), 'main' => true, 'status' => 'draft', 'outdated' => false])
        ->where('locales.1.name', 'Bahasa Indonesia'));
    get('/admin/cms/collections/pages/entries')->assertInertia(fn (Assert $page) => $page->where('entries.data.0.locales.1.name', 'Bahasa Indonesia'));
});
