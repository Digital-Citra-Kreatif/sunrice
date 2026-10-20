<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\DeleteTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Fields\HydrationContext;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Revision;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    actingAs(adminUser());
    Blueprint::query()->create(['handle' => 'product', 'title' => 'Product', 'fields' => [
        ['handle' => 'intro', 'type' => 'textarea', 'required' => true],
        ['handle' => 'price', 'type' => 'number', 'required' => true],
        ['handle' => 'tagline', 'type' => 'text', 'translatable' => false],
    ]]);
    $this->products = Collection::query()->create(['handle' => 'products', 'title' => 'Products', 'blueprint' => 'product']);
    $this->main = app(CreateEntry::class)->handle($this->products, ['title' => 'Coffee', 'data' => ['intro' => 'Strong coffee', 'price' => 30, 'tagline' => 'Brewed daily']]);
});

it('adds a language as a draft copied from the main one', function () {
    $id = app(CreateTranslation::class)->handle($this->main->entry, 'id');

    expect($id->only(['locale', 'status', 'draft_title', 'draft_slug']))->toBe(['locale' => 'id', 'status' => 'draft', 'draft_title' => 'Coffee', 'draft_slug' => 'coffee'])
        ->and($id->draft_data['intro'])->toBe('Strong coffee')
        ->and($id->isMain())->toBeFalse()
        ->and($this->main->isMain())->toBeTrue();
});

it('refuses unknown or existing languages', function (string $locale) {
    app(CreateTranslation::class)->handle($this->main->entry, $locale);
})->with(['fr', 'en'])->throws(ValidationException::class);

it('leaves shared fields to the main language', function () {
    $id = app(CreateTranslation::class)->handle($this->main->entry, 'id');

    // Translating doesn't need the shared, required price, and doesn't change it.
    app(SaveDraft::class)->handle($id, ['title' => 'Kopi', 'data' => ['intro' => 'Kopi kental', 'price' => 99, 'tagline' => 'Diseduh']]);
    app(PublishEntry::class)->handle($id);
    app(PublishEntry::class)->handle($this->main);

    $values = $id->fresh()?->values(new HydrationContext('id'));
    expect($values)->toMatchArray(['intro' => 'Kopi kental', 'price' => 30, 'tagline' => 'Brewed daily'])
        ->and($this->main->fresh()?->values()['price'])->toBe(30);

    // The main language's changes show in every language.
    app(SaveDraft::class)->handle($this->main->fresh() ?? $this->main, ['data' => ['price' => 35]]);
    expect($id->fresh()?->values(new HydrationContext('id', preview: true))['price'])->toBe(35)
        ->and($id->fresh()?->values(new HydrationContext('id'))['price'])->toBe(30);
});

it('stores only the translated fields in other languages, and reads the shared ones from the main one', function () {
    $id = app(CreateTranslation::class)->handle($this->main->entry, 'id');
    expect($id->draft_data)->toBe(['intro' => 'Strong coffee']);

    // A draft that still holds copies of shared fields (saved before) loses them on save.
    $id->forceFill(['draft_data' => ['intro' => 'Kopi', 'price' => 1, 'tagline' => 'Stale']])->save();
    app(SaveDraft::class)->handle($id, ['data' => ['intro' => 'Kopi kental']]);
    expect($id->fresh()?->draft_data)->toBe(['intro' => 'Kopi kental']);

    // Search text and templates read the main language's shared fields, live and in a preview, also after they change.
    app(PublishEntry::class)->handle($this->main);
    app(PublishEntry::class)->handle($id->fresh() ?? $id);
    expect($id->fresh()?->search_text)->toBe('Kopi kental Brewed daily')
        ->and($id->fresh()?->completeData())->toBe(['intro' => 'Kopi kental', 'price' => 30, 'tagline' => 'Brewed daily']);

    app(SaveDraft::class)->handle($this->main->fresh() ?? $this->main, ['data' => ['tagline' => 'Roasted here']]);
    expect($id->fresh()?->completeData(draft: true)['tagline'])->toBe('Roasted here')
        ->and($id->fresh()?->search_text)->toBe('Kopi kental Brewed daily');
    app(PublishEntry::class)->handle($this->main->fresh() ?? $this->main);
    expect($id->fresh()?->search_text)->toBe('Kopi kental Roasted here')
        ->and(Revision::query()->where('entry_translation_id', $id->id)->firstOrFail()->data)->toBe(['intro' => 'Kopi kental']);
});

it('keeps each language\'s slug unique on its own', function () {
    // Another entry already uses "coffee" in Indonesian.
    app(CreateEntry::class)->handle($this->products, ['title' => 'Kopi', 'slug' => 'coffee', 'data' => ['intro' => 'x', 'price' => 1], 'locale' => 'id']);

    expect(app(CreateTranslation::class)->handle($this->main->entry, 'id')->draft_slug)->toBe('coffee-2');
});

it('removes a language but never the main one', function () {
    $id = app(CreateTranslation::class)->handle($this->main->entry, 'id');
    app(DeleteTranslation::class)->handle($id);

    expect(EntryTranslation::query()->pluck('locale')->all())->toBe(['en'])
        ->and(fn () => app(DeleteTranslation::class)->handle($this->main))->toThrow(ValidationException::class, 'main language');
});

it('edits each language in the editor', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.create', 'cms.entries.update', 'cms.entries.publish', 'cms.entries.delete'));
    $entry = $this->main->entry;

    get("/admin/cms/entries/{$entry->id}/edit")->assertInertia(fn (Assert $page) => $page
        ->where('entry.locale', 'en')->where('entry.isMain', true)->where('shared', [])
        ->where('locales', [['code' => 'en', 'main' => true, 'status' => 'draft', 'outdated' => false], ['code' => 'id', 'main' => false, 'status' => null, 'outdated' => false]]));
    get("/admin/cms/entries/{$entry->id}/edit?locale=id")->assertNotFound();

    post("/admin/cms/entries/{$entry->id}/translations", ['locale' => 'id'])->assertRedirect("/admin/cms/entries/{$entry->id}/edit?locale=id");
    get("/admin/cms/entries/{$entry->id}/edit?locale=id")->assertInertia(fn (Assert $page) => $page
        ->where('entry.locale', 'id')->where('entry.isMain', false)
        ->where('shared', ['price', 'tagline'])
        ->where('sharedValues', ['price' => 30, 'tagline' => 'Brewed daily']));

    put("/admin/cms/entries/{$entry->id}", ['locale' => 'id', 'title' => 'Kopi', 'publish' => true])->assertSessionHasNoErrors();
    expect($entry->translations()->where('locale', 'id')->first()?->only(['title', 'status']))->toBe(['title' => 'Kopi', 'status' => 'published'])
        ->and($this->main->fresh()?->status)->toBe('draft');

    get('/admin/cms/collections/products/entries')->assertInertia(fn (Assert $page) => $page
        ->where('entries.data.0.locales.1', ['code' => 'id', 'main' => false, 'status' => 'published', 'outdated' => false]));

    delete("/admin/cms/entries/{$entry->id}/translations/id")->assertRedirect("/admin/cms/entries/{$entry->id}/edit");
    delete("/admin/cms/entries/{$entry->id}/translations/en")->assertSessionHasErrors('locale');
});

it('marks a translation outdated when the main language changes after it', function () {
    $id = app(CreateTranslation::class)->handle($this->main->entry, 'id');
    expect($id->main_version)->toBe($this->main->version)->and($id->fresh()?->isOutdated())->toBeFalse();

    app(SaveDraft::class)->handle($this->main, ['title' => 'Coffee, stronger']);
    $id = EntryTranslation::query()->findOrFail($id->id);
    expect($id->isOutdated())->toBeTrue()
        ->and($this->main->fresh()?->isOutdated())->toBeFalse(); // never the main one

    // Saving the translation brings it up to date with the main one as it is now.
    app(SaveDraft::class)->handle($id, ['title' => 'Kopi']);
    expect($id->fresh()?->isOutdated())->toBeFalse()->and($id->fresh()?->main_version)->toBe($this->main->fresh()?->version);

    // So does publishing it.
    app(PublishEntry::class)->handle($this->main->fresh());
    expect($id->fresh()?->isOutdated())->toBeTrue();
    app(PublishEntry::class)->handle($id->fresh());
    expect($id->fresh()?->isOutdated())->toBeFalse();
});

it('shows outdated translations in the editor and the list', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view'));
    $entry = $this->main->entry;
    app(CreateTranslation::class)->handle($entry, 'id');
    app(SaveDraft::class)->handle($this->main, ['title' => 'Coffee, stronger']);

    get("/admin/cms/entries/{$entry->id}/edit")->assertInertia(fn (Assert $page) => $page
        ->where('locales.0.outdated', false)->where('locales.1.outdated', true));
    get('/admin/cms/collections/products/entries')->assertInertia(fn (Assert $page) => $page
        ->where('entries.data.0.locales.1.outdated', true));
});
