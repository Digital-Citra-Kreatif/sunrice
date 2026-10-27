<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Globals\SaveGlobals;
use Sunrice\Cms\Actions\Settings\SwitchMainLanguage;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Fields\Block;
use Sunrice\Cms\Globals;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Fieldset;
use Sunrice\Cms\Models\GlobalSet;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Site\Site;
use Sunrice\Cms\Support\Locales;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

/*
 * Shared (not translated) fields inside groups, repeater rows and flexible
 * blocks of globals, listing pages, taxonomy index pages and term pages
 * follow the entries' rules: another language shows the main language's
 * values read-only, may leave them out (or send others) when saving, and
 * stores and renders the main language's.
 */

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id'], 'inertia.testing.ensure_pages_exist' => false]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser(Panel::ACCESS, 'cms.globals.view', 'cms.globals.update', 'cms.entries.view', 'cms.entries.update', 'cms.collections.update',
        'cms.terms.view', 'cms.terms.create', 'cms.terms.update', 'cms.taxonomies.update'));
    Fieldset::query()->create(['handle' => 'stats', 'title' => 'Stats', 'fields' => [
        ['handle' => 'label', 'type' => 'text'],
        ['handle' => 'source', 'type' => 'number', 'required' => true, 'translatable' => false],
    ]]);
    Blueprint::query()->create(['handle' => 'venue', 'title' => 'Venue', 'fields' => [
        ['handle' => 'hero', 'type' => 'group', 'fields' => [
            ['handle' => 'heading', 'type' => 'text'],
            ['handle' => 'capacity', 'type' => 'number', 'required' => true, 'translatable' => false],
        ]],
        ['handle' => 'dishes', 'type' => 'repeater', 'fields' => [['handle' => 'name', 'type' => 'text'], ['handle' => 'price', 'type' => 'number', 'required' => true, 'translatable' => false]]],
        ['handle' => 'blocks', 'type' => 'flexible', 'config' => ['blocks' => ['stats']]],
    ]]);
});

/** The main language's values. */
function venueValues(): array
{
    return [
        'hero' => ['heading' => 'Capacity', 'capacity' => 120],
        'dishes' => [['_id' => 'r1', 'name' => 'Soup', 'price' => 6]],
        'blocks' => [['id' => 'b1', 'type' => 'stats', 'values' => ['label' => 'Staff', 'source' => 3]]],
    ];
}

/** Another language's: its own texts only, or with other values for the shared fields (which are ignored). */
function venueTranslation(bool $withShared = false): array
{
    return [
        'hero' => ['heading' => 'Kapasitas'] + ($withShared ? ['capacity' => 999] : []),
        'dishes' => [['_id' => 'r1', 'name' => 'Sup'] + ($withShared ? ['price' => 99] : [])],
        'blocks' => [['id' => 'b1', 'type' => 'stats', 'values' => ['label' => 'Staf'] + ($withShared ? ['source' => 9] : [])]],
    ];
}

/** Values as another language reads them (stored, or as templates get them): its texts with the main language's shared values. */
function expectVenueTranslation(array $values): void
{
    $block = $values['blocks'][0];
    expect($values['hero'])->toBe(['heading' => 'Kapasitas', 'capacity' => 120])
        ->and($values['dishes'][0]['name'])->toBe('Sup')->and($values['dishes'][0]['price'])->toBe(6)
        ->and($block instanceof Block ? $block->values : $block['values'])->toBe(['label' => 'Staf', 'source' => 3]);
}

/** What another language stores: none of the shared values. */
function expectVenueStored(array $data): void
{
    expect($data['hero'])->toBe(['heading' => 'Kapasitas'])
        ->and($data['dishes'][0])->not->toHaveKey('price')
        ->and($data['blocks'][0]['values'])->toBe(['label' => 'Staf']);
}

/** The editor in another language marks the shared fields inside groups, rows and blocks; the main one doesn't. */
function sharedMarked(Assert $page): Assert
{
    return $page->where('fields.0.fields.1.shared', true)->missing('fields.0.fields.0.shared')
        ->where('fields.1.fields.1.shared', true)
        ->where('fields.2.blocks.0.fields.1.shared', true)
        ->where('values.hero.capacity', 120);
}

it('shares nested fields of globals with the main language', function () {
    $set = GlobalSet::query()->create(['handle' => 'venue', 'title' => 'Venue', 'blueprint' => 'venue']);
    put('/admin/cms/globals/venue/values', ['locale' => 'en', 'data' => venueValues()])->assertSessionHasNoErrors();

    get('/admin/cms/globals/venue/values?locale=id')->assertInertia(fn (Assert $page) => sharedMarked($page));
    get('/admin/cms/globals/venue/values?locale=en')->assertInertia(fn (Assert $page) => $page->missing('fields.0.fields.1.shared'));

    put('/admin/cms/globals/venue/values', ['locale' => 'id', 'data' => venueTranslation()])->assertSessionHasNoErrors();
    put('/admin/cms/globals/venue/values', ['locale' => 'id', 'data' => venueTranslation(withShared: true)])->assertSessionHasNoErrors();

    expectVenueStored($set->values()->where('locale', 'id')->firstOrFail()->data);
    expectVenueTranslation($set->fresh()?->data('id') ?? []);
    expect(app(Globals::class)->get('venue', 'id')['hero'])->toBe(['heading' => 'Kapasitas', 'capacity' => 120]);

    // The main language's change shows in the other at once.
    app(SaveGlobals::class)->handle($set, ['hero' => ['heading' => 'Capacity', 'capacity' => 150]]);
    expect(app(Globals::class)->get('venue', 'id')['hero'])->toBe(['heading' => 'Kapasitas', 'capacity' => 150]);
});

it('shows the main language\'s values in a global group another language has none for yet', function () {
    Blueprint::query()->create(['handle' => 'banner', 'title' => 'Banner', 'fields' => [
        ['handle' => 'hero', 'type' => 'group', 'fields' => [
            ['handle' => 'heading', 'type' => 'text'],
            ['handle' => 'rank', 'type' => 'number', 'translatable' => true],
            ['handle' => 'dark', 'type' => 'toggle', 'translatable' => true],
        ]],
        ['handle' => 'strapline', 'type' => 'text'],
    ]]);
    $set = GlobalSet::query()->create(['handle' => 'banner', 'title' => 'Banner', 'blueprint' => 'banner']);
    app(SaveGlobals::class)->handle($set, ['hero' => ['heading' => 'Welcome', 'rank' => 5, 'dark' => true], 'strapline' => 'Hello']);

    // As a text at the top level; a toggle's false is a value (as at the top level).
    expect(app(Globals::class)->get('banner', 'id'))->toBe(['hero' => ['heading' => 'Welcome', 'rank' => 5, 'dark' => false], 'strapline' => 'Hello']);
    get('/admin/cms/globals/banner/values?locale=id')->assertInertia(fn (Assert $page) => $page->where('values.hero.heading', 'Welcome'));

    // Its own values, false and 0 included, are its own.
    app(SaveGlobals::class)->handle($set->fresh() ?? $set, ['hero' => ['heading' => 'Selamat datang', 'rank' => 0, 'dark' => false]], 'id');
    expect(app(Globals::class)->get('banner', 'id'))->toBe(['hero' => ['heading' => 'Selamat datang', 'rank' => 0, 'dark' => false], 'strapline' => 'Hello']);
});

it('shares nested fields of a collection\'s listing page with the main language', function () {
    $books = Collection::query()->create(['handle' => 'books', 'title' => 'Books', 'route' => '/books/{slug}', 'listing_route' => '/books', 'listing_blueprint' => 'venue']);
    put('/admin/cms/collections/books/listing', ['locale' => 'en', 'data' => venueValues()])->assertSessionHasNoErrors();

    get('/admin/cms/collections/books/listing/edit?locale=id')->assertInertia(fn (Assert $page) => sharedMarked($page));
    get('/admin/cms/collections/books/listing/edit?locale=en')->assertInertia(fn (Assert $page) => $page->missing('fields.0.fields.1.shared'));

    put('/admin/cms/collections/books/listing', ['locale' => 'id', 'data' => venueTranslation()])->assertSessionHasNoErrors();
    put('/admin/cms/collections/books/listing', ['locale' => 'id', 'data' => venueTranslation(withShared: true)])->assertSessionHasNoErrors();

    expectVenueStored($books->listings()->where('locale', 'id')->firstOrFail()->data);
    expectVenueTranslation(($books->fresh() ?? $books)->listingData('id'));
});

it('shares nested fields of a taxonomy\'s index page with the main language', function () {
    $tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags', 'index_route' => '/tags', 'index_blueprint' => 'venue']);
    app()->forgetInstance(Site::class);
    put('/admin/cms/taxonomies/tags/index-page', ['locale' => 'en', 'data' => venueValues()])->assertSessionHasNoErrors();

    get('/admin/cms/taxonomies/tags/index-page/edit?locale=id')->assertInertia(fn (Assert $page) => sharedMarked($page));
    get('/admin/cms/taxonomies/tags/index-page/edit?locale=en')->assertInertia(fn (Assert $page) => $page->missing('fields.0.fields.1.shared'));

    put('/admin/cms/taxonomies/tags/index-page', ['locale' => 'id', 'data' => venueTranslation()])->assertSessionHasNoErrors();
    put('/admin/cms/taxonomies/tags/index-page', ['locale' => 'id', 'data' => venueTranslation(withShared: true)])->assertSessionHasNoErrors();

    expectVenueStored($tags->listings()->where('locale', 'id')->firstOrFail()->data);
    expectVenueTranslation(($tags->fresh() ?? $tags)->indexData('id'));
});

it('shares nested fields of a term\'s page with the main language', function () {
    $topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'route' => '/topics/{slug}', 'blueprint' => 'venue']);
    $term = app(SaveTerm::class)->handle($topics, ['title' => 'News']);
    app(SaveTerm::class)->handle($topics, ['title' => 'Berita', 'locale' => 'id'], $term);
    put("/admin/cms/terms/{$term->id}/content", ['locale' => 'en', 'data' => venueValues()])->assertSessionHasNoErrors();

    get(termEditUrl($term->id, 'id'))->assertInertia(fn (Assert $page) => sharedMarked($page));
    get(termEditUrl($term->id, 'en'))->assertInertia(fn (Assert $page) => $page->missing('fields.0.fields.1.shared'));

    put("/admin/cms/terms/{$term->id}/content", ['locale' => 'id', 'data' => venueTranslation()])->assertSessionHasNoErrors();
    put("/admin/cms/terms/{$term->id}/content", ['locale' => 'id', 'data' => venueTranslation(withShared: true)])->assertSessionHasNoErrors();

    $id = $term->translations()->where('locale', 'id')->firstOrFail();
    expectVenueStored($id->data ?? []);
    $term->load('translations');
    expectVenueTranslation($term->translations->firstWhere('locale', 'id')?->values() ?? []);
});

it('keeps all the fields of a term not in the main language yet as its own', function () {
    $topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'route' => '/topics/{slug}', 'blueprint' => 'venue']);
    $term = app(SaveTerm::class)->handle($topics, ['title' => 'Berita', 'locale' => 'id']);

    get(termEditUrl($term->id, 'id'))->assertInertia(fn (Assert $page) => $page->missing('fields.0.fields.1.shared')->where('shared', []));
    put("/admin/cms/terms/{$term->id}/content", ['locale' => 'id', 'data' => ['hero' => ['heading' => 'Kapasitas']]])->assertSessionHasErrors('data.hero.capacity');
    put("/admin/cms/terms/{$term->id}/content", ['locale' => 'id', 'data' => ['hero' => ['heading' => 'Kapasitas', 'capacity' => 80]]])->assertSessionHasNoErrors();

    expect($term->translations()->where('locale', 'id')->firstOrFail()->data['hero'] ?? null)->toBe(['heading' => 'Kapasitas', 'capacity' => 80]);
});

it('moves the shared values inside groups, rows and blocks to a new main language', function () {
    $set = GlobalSet::query()->create(['handle' => 'venue', 'title' => 'Venue', 'blueprint' => 'venue']);
    app(SaveGlobals::class)->handle($set, venueValues());
    app(SaveGlobals::class)->handle($set->fresh() ?? $set, venueTranslation(), 'id');

    app(SwitchMainLanguage::class)->handle('id');

    expect(Locales::default())->toBe('id');
    $stored = fn (string $locale): array => $set->values()->where('locale', $locale)->firstOrFail()->data;
    expect($stored('id')['hero'])->toBe(['heading' => 'Kapasitas', 'capacity' => 120])
        ->and($stored('id')['dishes'][0]['price'])->toBe(6)
        ->and($stored('id')['blocks'][0]['values'])->toBe(['label' => 'Staf', 'source' => 3])
        ->and($stored('en')['hero'])->toBe(['heading' => 'Capacity'])
        ->and($stored('en')['dishes'][0])->not->toHaveKey('price')
        ->and(($set->fresh() ?? $set)->data('en')['hero'])->toBe(['heading' => 'Capacity', 'capacity' => 120]);
});
