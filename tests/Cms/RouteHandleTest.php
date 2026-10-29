<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Blueprints\SaveBlueprint;
use Sunrice\Cms\Actions\Collections\AttachTaxonomies;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Taxonomies\AttachCollections;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\CollectionTaxonomy;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Structure\StructureImporter;
use Sunrice\Cms\Structure\StructureImportFailed;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

/*
 * {handle} in a URL pattern is the collection's or taxonomy's handle,
 * written into the pattern when it's saved; {slug} and {handle} are kept
 * from blueprints' field handles so they can't mean a field.
 */

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser(Panel::ACCESS, 'cms.collections.view', 'cms.collections.create', 'cms.collections.update', 'cms.taxonomies.view', 'cms.taxonomies.create', 'cms.taxonomies.update'));
    Blueprint::query()->create(['handle' => 'unit', 'title' => 'Unit', 'fields' => [
        ['handle' => 'project', 'type' => 'entries', 'config' => ['collections' => ['projects']]],
        ['handle' => 'others', 'type' => 'entries', 'config' => ['collections' => ['projects'], 'multiple' => true]],
        ['handle' => 'floor', 'type' => 'text'],
    ]]);
});

it('writes the handle into a new collection\'s URLs, each language\'s own too', function () {
    post('/admin/cms/collections', [
        'title' => 'Units', 'handle' => 'units', 'pages' => true, 'listing' => true,
        'route' => '/{handle}/{slug}', 'route_id' => '/unit/{handle}/{slug}',
        'listing_route' => '/all-{handle}', 'listing_route_id' => '/daftar/{handle}',
    ])->assertSessionHasNoErrors();

    $units = Collection::query()->where('handle', 'units')->firstOrFail();
    expect($units->route)->toBe('/units/{slug}')
        ->and($units->listing_route)->toBe('/all-units')
        ->and($units->routes)->toBe(['id' => ['route' => '/unit/units/{slug}', 'listing_route' => '/daftar/units']]);
});

it('writes the handle in on an edit form, and leaves the URLs alone when the handle changes later', function () {
    $units = Collection::query()->create(['handle' => 'units', 'title' => 'Units', 'route' => '/units/{slug}']);

    put('/admin/cms/collections/units', ['title' => 'Units', 'pages' => true, 'route' => '/flats/{handle}/{slug}', 'route_id' => '/{handle}/{slug}'])->assertSessionHasNoErrors();
    expect($units->refresh()->route)->toBe('/flats/units/{slug}')
        ->and($units->ownRoute('id'))->toBe('/units/{slug}');

    put('/admin/cms/collections/units', ['title' => 'Units', 'handle' => 'apartments', 'pages' => true, 'route' => '/flats/units/{slug}', 'route_id' => '/units/{slug}'])->assertSessionHasNoErrors();
    expect($units->refresh()->only(['handle', 'route']))->toBe(['handle' => 'apartments', 'route' => '/flats/units/{slug}']);
});

it('writes the handle into a taxonomy\'s shared URLs and its URLs in a collection', function () {
    $books = Collection::query()->create(['handle' => 'books', 'title' => 'Books', 'route' => '/books/{slug}', 'listing_route' => '/reading']);
    $id = (string) $books->id;

    post('/admin/cms/taxonomies', [
        'title' => 'Tags', 'handle' => 'tags', 'pages' => true, 'index' => true,
        'route' => '/{handle}/{slug}', 'route_id' => '/label-{handle}/{slug}',
        'index_route' => '/{handle}', 'index_route_id' => '/semua-{handle}',
        'collections' => [$id], "term_pages_{$id}" => true, "index_page_{$id}" => true,
        "term_route_{$id}" => '{listing}/{handle}/{slug}', "term_route_{$id}_id" => '/buku/{handle}/{slug}',
        "index_route_{$id}" => '{listing}/all-{handle}', "index_route_{$id}_id" => '{listing}/{handle}',
    ])->assertSessionHasNoErrors();

    $tags = Taxonomy::query()->where('handle', 'tags')->firstOrFail();
    expect($tags->route)->toBe('/tags/{slug}')
        ->and($tags->index_route)->toBe('/tags')
        ->and($tags->routes)->toBe(['id' => ['route' => '/label-tags/{slug}', 'index_route' => '/semua-tags']]);
    $pivot = DB::table('sunrice_collection_taxonomy')->where('collection_id', $books->id)->first();
    // {listing} is the collection's listing page URL in that language.
    expect($pivot->term_route)->toBe('/reading/tags/{slug}')
        ->and(json_decode((string) $pivot->term_routes, true))->toBe(['id' => '/buku/tags/{slug}'])
        ->and($pivot->index_route)->toBe('/reading/all-tags')
        ->and(json_decode((string) $pivot->index_routes, true))->toBe(['id' => '/reading/tags']);

    // On the edit form too, and with a language's own listing page URL.
    $books->setOwnRoute('id', '/bacaan', 'listing_route')->save();
    put('/admin/cms/taxonomies/tags', ['title' => 'Tags', 'pages' => true, 'route' => '/topics/{handle}/{slug}', 'collections' => [$id], "term_pages_{$id}" => true, "term_route_{$id}_id" => '{listing}/{handle}/{slug}'])->assertSessionHasNoErrors();
    expect($tags->refresh()->route)->toBe('/topics/tags/{slug}')
        ->and(json_decode((string) DB::table('sunrice_collection_taxonomy')->where('collection_id', $books->id)->value('term_routes'), true))->toBe(['id' => '/bacaan/tags/{slug}']);
    get('/id/bacaan/tags/x')->assertNotFound();
});

it('writes the handle in whatever saves the pattern: code, attachments and structure imports', function () {
    $units = Collection::query()->create(['handle' => 'units', 'title' => 'Units', 'route' => '/{handle}/{slug}', 'listing_route' => '/{handle}']);
    expect($units->only(['route', 'listing_route']))->toBe(['route' => '/units/{slug}', 'listing_route' => '/units']);
    $units->setOwnRoute('id', '/{handle}-id/{slug}')->save();
    expect($units->refresh()->ownRoute('id'))->toBe('/units-id/{slug}');

    $tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    app(AttachTaxonomies::class)->handle($units, [$tags->id => false]);
    $units->taxonomies()->updateExistingPivot($tags->id, ['term_route' => '{listing}/{handle}/{slug}', 'index_routes' => ['id' => '{listing}/{handle}']]);
    $pivot = $units->taxonomies()->firstOrFail()->getRelation('pivot');
    expect($pivot)->toBeInstanceOf(CollectionTaxonomy::class)
        ->and($pivot->term_route)->toBe('/units/tags/{slug}')
        ->and($pivot->index_routes)->toBe(['id' => '/units/tags']);

    $labels = Taxonomy::query()->create(['handle' => 'labels', 'title' => 'Labels']);
    app(AttachCollections::class)->handle($labels, [$units->id]);
    $labels->collections()->updateExistingPivot($units->id, ['term_route' => '/{handle}-of-units/{slug}']);
    expect(DB::table('sunrice_collection_taxonomy')->where('taxonomy_id', $labels->id)->value('term_route'))->toBe('/labels-of-units/{slug}');

    app(StructureImporter::class)->import(['collections' => [
        ['handle' => 'flats', 'title' => 'Flats', 'route' => '/{handle}/{slug}', 'listing_route' => '/{handle}', 'routes' => ['id' => ['route' => '/{handle}-id/{slug}']]],
    ], 'taxonomies' => [
        ['handle' => 'floors', 'title' => 'Floors', 'route' => '/{handle}/{slug}', 'index_route' => '/{handle}', 'collections' => ['flats'],
            'collection_pages' => ['flats' => ['pages' => true, 'route' => '{listing}/{handle}/{slug}', 'routes' => ['id' => '/x/{handle}/{slug}'], 'index' => true, 'index_route' => '{listing}/all-{handle}']]],
    ]]);
    $flats = Collection::query()->where('handle', 'flats')->firstOrFail();
    $floors = Taxonomy::query()->where('handle', 'floors')->firstOrFail();
    expect($flats->only(['route', 'listing_route', 'routes']))->toBe(['route' => '/flats/{slug}', 'listing_route' => '/flats', 'routes' => ['id' => ['route' => '/flats-id/{slug}']]])
        ->and($floors->only(['route', 'index_route']))->toBe(['route' => '/floors/{slug}', 'index_route' => '/floors']);
    $pivot = DB::table('sunrice_collection_taxonomy')->where('taxonomy_id', $floors->id)->first();
    expect($pivot->term_route)->toBe('/flats/floors/{slug}')
        ->and(json_decode((string) $pivot->term_routes, true))->toBe(['id' => '/x/floors/{slug}'])
        ->and($pivot->index_route)->toBe('/flats/all-floors');
});

it('checks an imported pattern with the handle written in', function () {
    Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'route' => '/flats/{slug}']);

    expect(fn () => app(StructureImporter::class)->import(['collections' => [['handle' => 'flats', 'title' => 'Flats', 'route' => '/{handle}/{slug}']]]))
        ->toThrow(StructureImportFailed::class);
    expect(Collection::query()->where('handle', 'flats')->exists())->toBeFalse();
});

it('serves /{project}/{handle}/{slug} at the related entry\'s slug and the handle', function () {
    $projects = Collection::query()->create(['handle' => 'projects', 'title' => 'Projects', 'route' => '/{slug}']);
    post('/admin/cms/collections', ['title' => 'Units', 'handle' => 'units', 'blueprint' => 'unit', 'pages' => true, 'route' => '/{project}/{handle}/{slug}'])->assertSessionHasNoErrors();
    $units = Collection::query()->where('handle', 'units')->firstOrFail();
    expect($units->route)->toBe('/{project}/units/{slug}');

    $tower = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($projects, ['title' => 'Tower']));
    $unit = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($units, ['title' => 'Unit 1', 'data' => ['project' => $tower->entry_id]]));

    expect($unit->url())->toBe(url('/tower/units/unit-1'));
    get('/tower/units/unit-1')->assertOk()->assertSee('Unit 1');
    get('/tower')->assertOk()->assertSee('Tower');
});

it('checks uniqueness and clashes with the handle written in', function () {
    Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'route' => '/units/{slug}', 'listing_route' => '/units']);
    Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags', 'route' => '/flats/{slug}', 'routes' => ['id' => ['route' => '/flats-id/{slug}']]]);

    // Another collection's pattern and listing URL: refused as taken, not as a pattern with braces.
    $taken = fn (string $field) => expect(session('errors')->first($field))->toContain('already');
    post('/admin/cms/collections', ['title' => 'Units', 'handle' => 'units', 'pages' => true, 'route' => '/{handle}/{slug}'])->assertSessionHasErrors('route');
    $taken('route');
    post('/admin/cms/collections', ['title' => 'Units', 'handle' => 'units', 'pages' => true, 'route' => '/u/{slug}', 'listing' => true, 'listing_route' => '/{handle}'])->assertSessionHasErrors('listing_route');
    $taken('listing_route');
    // A taxonomy's term pages, in the main language and in a language's own.
    post('/admin/cms/collections', ['title' => 'Flats', 'handle' => 'flats', 'pages' => true, 'route' => '/{handle}/{slug}'])->assertSessionHasErrors('route');
    $taken('route');
    post('/admin/cms/collections', ['title' => 'Flats', 'handle' => 'flats', 'pages' => true, 'route' => '/f/{slug}', 'route_id' => '/{handle}-id/{slug}'])->assertSessionHasErrors('route_id');
    $taken('route_id');
    expect(Collection::query()->whereIn('handle', ['units', 'flats'])->exists())->toBeFalse();

    // A collection's term pages: checked once written.
    $books = Collection::query()->create(['handle' => 'books', 'title' => 'Books', 'route' => '/books/{slug}']);
    Collection::query()->create(['handle' => 'tagged', 'title' => 'Tagged', 'route' => '/tags/{slug}']);
    $id = (string) $books->id;
    put('/admin/cms/taxonomies/tags', ['title' => 'Tags', 'collections' => [$id], "term_pages_{$id}" => true, "term_route_{$id}" => '/{handle}/{slug}', "index_page_{$id}" => false])
        ->assertSessionHasErrors("term_route_{$id}");
    put('/admin/cms/taxonomies/tags', ['title' => 'Tags', 'collections' => [$id], "term_pages_{$id}" => true, "term_route_{$id}" => '/books/{handle}/{slug}', "index_page_{$id}" => false])
        ->assertSessionHasNoErrors();
    expect(DB::table('sunrice_collection_taxonomy')->where('collection_id', $books->id)->value('term_route'))->toBe('/books/tags/{slug}');
});

it('refuses handle and slug as a field\'s handle, while a blueprint that has one still loads and saves', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.blueprints.view', 'cms.blueprints.create', 'cms.blueprints.update'));

    foreach (['handle', 'slug'] as $reserved) {
        post('/admin/cms/blueprints', ['handle' => 'flat', 'title' => 'Flat', 'fields' => [['handle' => $reserved, 'type' => 'text']]])
            ->assertSessionHasErrors(['fields.0.handle']);
    }
    expect(Blueprint::RESERVED)->toContain('handle', 'slug');
    get('/admin/cms/blueprints/create')->assertInertia(fn (Assert $page) => $page->where('reserved', fn ($reserved) => in_array('handle', [...$reserved], true)));

    // Made before handle was kept: it loads, the builder doesn't warn about it, and it saves.
    $old = Blueprint::query()->create(['handle' => 'old', 'title' => 'Old', 'fields' => [['handle' => 'handle', 'type' => 'text']]]);
    get('/admin/cms/blueprints/old/edit')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('blueprint.fields.0.handle', 'handle')
        ->where('reserved', fn ($reserved) => ! in_array('handle', [...$reserved], true) && in_array('slug', [...$reserved], true)));
    put('/admin/cms/blueprints/old', ['title' => 'Old', 'fields' => [['handle' => 'handle', 'type' => 'text'], ['handle' => 'more', 'type' => 'text']]])->assertSessionHasNoErrors();
    expect(array_column($old->refresh()->fields, 'handle'))->toBe(['handle', 'more']);
    // Another blueprint can't add one, nor this one a second time under another name.
    expect(fn () => app(SaveBlueprint::class)->handle(['title' => 'Unit', 'fields' => [['handle' => 'handle', 'type' => 'text']]], Blueprint::query()->where('handle', 'unit')->firstOrFail()))
        ->toThrow(ValidationException::class);
});

it('keeps a stored pattern whose {handle} is an Entries field of a blueprint made before', function () {
    $projects = Collection::query()->create(['handle' => 'projects', 'title' => 'Projects', 'route' => '/projects/{slug}']);
    Blueprint::query()->create(['handle' => 'legacy', 'title' => 'Legacy', 'fields' => [['handle' => 'handle', 'type' => 'entries', 'config' => ['collections' => ['projects']]]]]);
    $rooms = Collection::query()->create(['handle' => 'rooms', 'title' => 'Rooms', 'blueprint' => 'legacy', 'route' => '/in/{handle}/{slug}']);
    expect($rooms->route)->toBe('/in/{handle}/{slug}');

    $tower = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($projects, ['title' => 'Tower']));
    app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($rooms, ['title' => 'Room', 'data' => ['handle' => $tower->entry_id]]));
    get('/in/tower/room')->assertOk();

    put('/admin/cms/collections/rooms', ['title' => 'Rooms', 'pages' => true, 'blueprint' => 'legacy', 'route' => '/in/{handle}/{slug}'])->assertSessionHasNoErrors();
    expect($rooms->refresh()->route)->toBe('/in/{handle}/{slug}');
    get('/in/tower/room')->assertOk();
});

it('says what is wrong with an entry URL', function (string $route, string $message) {
    post('/admin/cms/collections', ['title' => 'Flats', 'handle' => 'flats', 'blueprint' => 'unit', 'pages' => true, 'route' => $route])
        ->assertSessionHasErrors(['route' => $message]);
})->with([
    'no slug' => ['/flats', 'Put {slug} in the URL, where each entry\'s slug goes, like /blog/{slug}.'],
    'no slug, a field only' => ['/flats/{project}', 'Put {slug} in the URL, where each entry\'s slug goes, like /blog/{slug}.'],
    'slug twice' => ['/{slug}/{slug}', '{slug} can be in the URL only once.'],
    'a field twice' => ['/{project}/{project}/{slug}', '{project} can be in the URL only once.'],
    'unknown field' => ['/flats/{nope}/{slug}', '{nope} isn\'t a field of the blueprint. In braces go {slug}, {handle} (the collection\'s handle) and Entries fields linking to one entry, like /projects/{project}/{slug}.'],
    'not an entries field' => ['/flats/{floor}/{slug}', '{floor} isn\'t an Entries field. In braces go {slug}, {handle} (the collection\'s handle) and Entries fields linking to one entry, like /projects/{project}/{slug}.'],
    'several entries' => ['/flats/{others}/{slug}', '{others} links to several entries, so it can\'t be in the URL: switch its Several entries off, or use an Entries field linking to one entry.'],
    'capital letter' => ['/Flats/{slug}', '"Flats" can\'t be in the URL: use lowercase letters, digits, dashes and underscores between the slashes.'],
    'a space' => ['/our flats/{slug}', '"our flats" can\'t be in the URL: use lowercase letters, digits, dashes and underscores between the slashes.'],
    'braces in a segment' => ['/flats-{project}/{slug}', '"flats-{project}" can\'t be in the URL: use lowercase letters, digits, dashes and underscores between the slashes.'],
    'no leading slash' => ['flats/{slug}', 'Start the URL with a slash, like /blog/{slug}.'],
    'an empty part' => ['/flats//{slug}', 'Leave out the empty part between two slashes, or the slash at the end.'],
]);

it('says what is wrong with a term page URL', function () {
    post('/admin/cms/taxonomies', ['title' => 'Tags', 'handle' => 'tags', 'pages' => true, 'route' => '/{slug}/{slug}'])
        ->assertSessionHasErrors(['route' => '{slug} can be in the URL only once.']);
    post('/admin/cms/taxonomies', ['title' => 'Tags', 'handle' => 'tags', 'pages' => true, 'route' => '/topics'])
        ->assertSessionHasErrors(['route' => 'Put {slug} in the URL, where each term\'s slug goes, like /topics/{slug}.']);
    post('/admin/cms/taxonomies', ['title' => 'Tags', 'handle' => 'tags', 'pages' => true, 'route' => '/topics/{group}/{slug}'])
        ->assertSessionHasErrors(['route' => 'Only {slug} and {handle} (the taxonomy\'s handle) can be in braces here.']);
    post('/admin/cms/taxonomies', ['title' => 'Tags', 'handle' => 'tags', 'index' => true, 'index_route' => '/{slug}'])
        ->assertSessionHasErrors(['index_route' => 'Only {handle} (the taxonomy\'s handle) can be in braces here: it\'s the address of one page.']);
});

it('shows the real handle in the URL hints', function () {
    Collection::query()->create(['handle' => 'units', 'title' => 'Units', 'route' => '/units/{slug}', 'listing_route' => '/units']);
    Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags', 'route' => '/tags/{slug}', 'index_route' => '/tags']);
    $fields = fn (Assert $page) => collect($page->toArray()['props']['sections'])->flatMap(fn (array $section) => $section['fields'])->keyBy('name');

    get('/admin/cms/collections/units/edit')->assertOk()->assertInertia(function (Assert $page) use ($fields) {
        $all = $fields($page);
        expect($all['route'])->toMatchArray(['placeholder' => '/units/{slug}', 'placeholderFrom' => ['field' => null, 'fallback' => '/units/{slug}']])
            ->and($all['route']['help'])->toContain('Empty: :default.')
            ->and($all['route']['helpValues'])->toBe(['default' => ['field' => null, 'fallback' => '/units/{slug}']])
            ->and($all['route']['helpDetails']['text'])->toContain('{handle}')->toContain('{slug}')->toContain('/projects/{project}/{slug}')
            ->and($all['route_id']['placeholderFrom'])->toBe(['field' => 'route', 'fallback' => '/units/{slug}'])
            ->and($all['listing_route'])->toMatchArray(['placeholder' => '/units', 'helpValues' => ['default' => ['field' => null, 'fallback' => '/units']]]);
    });
    // Creating: from the Handle field as it's typed (lib/live-help), saying it's filled in on saving.
    get('/admin/cms/collections/create')->assertOk()->assertInertia(function (Assert $page) use ($fields) {
        $all = $fields($page);
        expect($all['route'])->toMatchArray(['placeholder' => '/{handle}/{slug}', 'placeholderFrom' => ['field' => null, 'fallback' => '/{handle}/{slug}']])
            ->and($all['route']['helpValues'])->toBe(['default' => ['field' => null, 'fallback' => '/{handle}/{slug}']])
            ->and($all['route']['helpDetails']['text'])->toContain('filled in when you save');
    });
    get('/admin/cms/taxonomies/tags/edit')->assertOk()->assertInertia(function (Assert $page) use ($fields) {
        $all = $fields($page);
        expect($all['route'])->toMatchArray(['placeholder' => '/tags/{slug}', 'helpValues' => ['default' => ['field' => null, 'fallback' => '/tags/{slug}']]])
            ->and($all['index_route'])->toMatchArray(['placeholder' => '/tags']);
    });
});
