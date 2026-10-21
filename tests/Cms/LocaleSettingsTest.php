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
    // Rows of a code and a name, starting from the languages in use.
    get('/admin/settings/locales')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('form.0.fields.0.type', 'pairs')->where('form.0.fields.0.firstBadge', 'Main')
        ->where('form.0.fields.0.suggestions.0', ['key' => 'en', 'value' => 'English'])
        // Short hints over the columns and one line under them.
        ->where('form.0.fields.0.hints', ['key' => 'en, id, pt_BR…', 'value' => 'As visitors read it', 'extra' => 'Start of its URLs'])
        ->where('form.0.fields.0.help', 'The first is the main language.')
        ->where('values.languages', "en: English\nid: Bahasa Indonesia"));
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

    // Pointed to the Main language card, not to artisan.
    put('/admin/settings/locales', ['languages' => "en: English\nid: Bahasa Indonesia"])->assertSessionHasErrors(['languages' => 'Bahasa Indonesia (id) is the main language and content is written in it: keep it first and save. Then, to make English the main language, use its button in the Main language card beside the form.']);
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

    get(entryEditUrl($page->entry_id))->assertInertia(fn (Assert $page) => $page
        ->where('locales.0', ['code' => 'en', 'name' => Locales::displayName('en'), 'main' => true, 'status' => 'draft', 'outdated' => false])
        ->where('locales.1.name', 'Bahasa Indonesia'));
    get('/admin/cms/collections/pages/entries')->assertInertia(fn (Assert $page) => $page->where('entries.data.0.locales.1.name', 'Bahasa Indonesia'));
});

it('opens with the languages in use, and switches the main language from the page', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.settings.locales'));
    get('/admin/settings/locales')->assertInertia(fn (Assert $page) => $page
        ->where('values.languages', 'en: '.Locales::displayName('en')."\nid: Bahasa Indonesia")
        ->where('cards.0.title', 'Main language')
        ->where('cards.0.buttons.0.label', 'Make Bahasa Indonesia the main language')
        ->where('cards.0.buttons.0.url', '/admin/cms/languages/id/main'));

    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => []]);
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page']);
    $page = app(CreateEntry::class)->handle($pages, ['title' => 'Hello']);

    // Content without an Indonesian translation: refused with the reason.
    \Pest\Laravel\post('/admin/cms/languages/id/main')->assertSessionHas('error', fn (string $error) => str_contains($error, 'no Bahasa Indonesia translation'));
    expect(Locales::default())->toBe('en');

    app(CreateTranslation::class)->handle($page->entry, 'id');
    \Pest\Laravel\post('/admin/cms/languages/id/main')->assertSessionHas('success', 'Bahasa Indonesia is the main language now.');
    app()->forgetInstance(LocaleSettings::class);
    expect(Locales::default())->toBe('id');
    \Pest\Laravel\post('/admin/cms/languages/fr/main')->assertSessionHas('error');

    actingAs(adminUser(Panel::ACCESS));
    \Pest\Laravel\post('/admin/cms/languages/en/main')->assertForbidden();
});
