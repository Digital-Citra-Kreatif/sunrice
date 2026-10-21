<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Preferences\Preferences;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Fieldset;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Support\Locales;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Blueprint::query()->create(['handle' => 'product', 'title' => 'Product', 'fields' => [
        ['handle' => 'tagline', 'type' => 'text', 'label' => 'Tagline'],
        ['handle' => 'price', 'type' => 'number', 'label' => 'Price'],
        ['handle' => 'launch', 'type' => 'date', 'label' => 'Launch'],
        ['handle' => 'kind', 'type' => 'select', 'label' => 'Kind', 'config' => ['options' => [['value' => 'tea', 'label' => 'Tea'], ['value' => 'coffee', 'label' => 'Coffee']]]],
        ['handle' => 'featured', 'type' => 'toggle', 'label' => 'Featured'],
        ['handle' => 'related', 'type' => 'entries', 'label' => 'Related', 'config' => ['collections' => ['products'], 'multiple' => true]],
        ['handle' => 'gallery', 'type' => 'repeater', 'label' => 'Gallery', 'fields' => [['handle' => 'caption', 'type' => 'text']]],
    ]]);
    $this->products = Collection::query()->create(['handle' => 'products', 'title' => 'Products', 'blueprint' => 'product', 'route' => '/products/{slug}']);
    $this->topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'hierarchical' => true]);
    $this->products->taxonomies()->attach($this->topics);
});

function listProduct(string $title, array $data = [], ?User $author = null, bool $publish = false): EntryTranslation
{
    if ($author !== null) {
        actingAs($author);
    }
    $translation = app(CreateEntry::class)->handle(test()->products, ['title' => $title, 'data' => $data]);

    return $publish ? app(PublishEntry::class)->handle($translation) : $translation;
}

function lister(string ...$permissions): User
{
    $user = adminUser(Panel::ACCESS, ...($permissions === [] ? ['cms.entries.view'] : $permissions));
    actingAs($user);

    return $user;
}

/** The listed titles for a query string. */
function listed(string $query = ''): array
{
    $titles = [];
    get('/admin/cms/collections/products/entries'.($query === '' ? '' : '?'.$query))->assertOk()
        ->assertInertia(function (Assert $page) use (&$titles) {
            $titles = array_column($page->toArray()['props']['entries']['data'], 'title');
        });

    return $titles;
}

it('filters by status, the trash included', function () {
    travelTo('2026-10-01 10:00');
    listProduct('Live', publish: true);
    listProduct('Draft');
    app(PublishEntry::class)->handle(listProduct('Soon'), new DateTimeImmutable('2026-12-01 10:00'));
    listProduct('Gone')->entry->delete();
    lister();

    expect(listed())->toBe(['Draft', 'Live', 'Soon'])
        ->and(listed('status=draft'))->toBe(['Draft'])
        ->and(listed('status=published'))->toBe(['Live'])
        ->and(listed('status=scheduled'))->toBe(['Soon'])
        ->and(listed('status=trashed'))->toBe(['Gone'])
        ->and(listed('status=trashed'))->toBe(['Gone'])
        ->and(listed('status=nonsense'))->toBe(['Draft', 'Live', 'Soon']);
});

it('filters by author and offers the collection\'s authors', function () {
    $ana = adminUser(Panel::ACCESS);
    $ana->forceFill(['name' => 'Ana'])->save();
    $budi = adminUser(Panel::ACCESS);
    $budi->forceFill(['name' => 'Budi'])->save();
    listProduct('By Ana', author: $ana);
    listProduct('By Budi', author: $budi);
    lister();

    expect(listed("author={$ana->id}"))->toBe(['By Ana'])
        ->and(listed('author=abc'))->toBe(['By Ana', 'By Budi']);
    get('/admin/cms/collections/products/entries')->assertInertia(fn (Assert $page) => $page
        ->where('filters.authors', [['value' => (string) $ana->id, 'label' => 'Ana'], ['value' => (string) $budi->id, 'label' => 'Budi']]));
});

it('filters by a term of an attached taxonomy, and ignores other taxonomies', function () {
    $tea = app(SaveTerm::class)->handle($this->topics, ['title' => 'Tea']);
    $green = app(SaveTerm::class)->handle($this->topics, ['title' => 'Green', 'parent_id' => $tea->id]);
    listProduct('Sencha')->entry->terms()->attach($green);
    listProduct('Assam')->entry->terms()->attach($tea);
    listProduct('Mug');
    lister();

    expect(listed("terms[topics]={$tea->id}"))->toBe(['Assam'])
        ->and(listed("terms[topics]={$green->id}"))->toBe(['Sencha'])
        ->and(listed("terms[other]={$tea->id}"))->toBe(['Assam', 'Mug', 'Sencha']);
    get('/admin/cms/collections/products/entries')->assertInertia(fn (Assert $page) => $page
        ->where('filters.taxonomies.0.handle', 'topics')
        ->where('filters.taxonomies.0.terms', [['value' => (string) $tea->id, 'label' => 'Tea', 'depth' => 0], ['value' => (string) $green->id, 'label' => 'Green', 'depth' => 1]]));
});

it('filters by a taxonomy the blueprint has a terms field for, without ticking the collection on it', function () {
    $areas = Taxonomy::query()->create(['handle' => 'areas', 'title' => 'Areas']);
    $bali = app(SaveTerm::class)->handle($areas, ['title' => 'Bali']);
    $blueprint = Blueprint::query()->where('handle', 'product')->first();
    $blueprint->update(['fields' => [...$blueprint->fields, ['handle' => 'area', 'type' => 'group', 'fields' => [['handle' => 'where', 'type' => 'terms', 'config' => ['taxonomy' => 'areas']]]]]]);
    listProduct('Villa')->entry->terms()->attach($bali);
    listProduct('Flat');
    lister();

    expect(listed("terms[areas]={$bali->id}"))->toBe(['Villa']);
    get('/admin/cms/collections/products/entries')->assertInertia(fn (Assert $page) => $page
        ->where('filters.taxonomies.0.handle', 'topics')
        ->where('filters.taxonomies.1.handle', 'areas'));
});

it('lists another language\'s translations', function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    $tea = listProduct('Tea');
    listProduct('Mug');
    $teh = app(CreateTranslation::class)->handle($tea->entry, 'id');
    $teh->forceFill(['draft_title' => 'Teh'])->save();
    lister();

    expect(listed())->toBe(['Mug', 'Tea'])
        ->and(listed('locale=id'))->toBe(['Teh'])
        ->and(listed('locale=fr'))->toBe(['Mug', 'Tea']);
    get('/admin/cms/collections/products/entries')->assertInertia(fn (Assert $page) => $page
        ->where('entries.data.1.locales', [['code' => 'en', 'name' => Locales::displayName('en'), 'main' => true, 'status' => 'draft', 'outdated' => false], ['code' => 'id', 'name' => Locales::displayName('id'), 'main' => false, 'status' => 'draft', 'outdated' => false]])
        ->where('filters.locales', ['en', 'id']));
});

it('searches titles and slugs, and finds an id with #', function () {
    $tea = listProduct('Green tea');
    $mug = listProduct('Mug');
    $mug->forceFill(['draft_slug' => 'cup-for-tea'])->save();
    listProduct('Kettle');
    lister();

    expect(listed('search=TEA'))->toBe(['Green tea', 'Mug'])
        ->and(listed('search=%23'.$tea->entry_id))->toBe(['Green tea'])
        ->and(listed('search=%25'))->toBe([]);
});

it('sorts by columns and by number, date and text fields, as numbers and dates', function () {
    $ana = adminUser(Panel::ACCESS);
    $ana->forceFill(['name' => 'Zed'])->save();
    $budi = adminUser(Panel::ACCESS);
    $budi->forceFill(['name' => 'Abe'])->save();
    listProduct('Small', ['price' => 9, 'launch' => '2026-01-15', 'tagline' => 'b'], $ana);
    listProduct('Large', ['price' => 100, 'launch' => '2025-12-01', 'tagline' => 'c'], $budi);
    listProduct('Medium', ['price' => 20.5, 'launch' => '2026-03-01', 'tagline' => 'a'], $budi);
    lister();

    expect(listed())->toBe(['Large', 'Medium', 'Small'])
        ->and(listed('sort=-title'))->toBe(['Small', 'Medium', 'Large'])
        // As text, "100" would come before "20.5" and "9".
        ->and(listed('sort=field.price'))->toBe(['Small', 'Medium', 'Large'])
        ->and(listed('sort=-field.price'))->toBe(['Large', 'Medium', 'Small'])
        ->and(listed('sort=field.launch'))->toBe(['Large', 'Small', 'Medium'])
        ->and(listed('sort=field.tagline'))->toBe(['Medium', 'Small', 'Large'])
        ->and(listed('sort=author'))->toBe(['Large', 'Medium', 'Small'])
        ->and(listed('sort=-author'))->toBe(['Small', 'Medium', 'Large'])
        // Not sortable, or no such column: the collection's order.
        ->and(listed('sort=field.related'))->toBe(['Large', 'Medium', 'Small'])
        ->and(listed('sort=password'))->toBe(['Large', 'Medium', 'Small']);
});

it('sorts by dates and status', function () {
    travelTo('2026-10-01');
    listProduct('Old', publish: true);
    travelTo('2026-10-05');
    listProduct('New');
    travelTo('2026-10-03');
    listProduct('Middle', publish: true);
    travelTo('2026-10-06');
    lister();

    expect(listed('sort=created_at'))->toBe(['Old', 'Middle', 'New'])
        ->and(listed('sort=-updated_at'))->toBe(['New', 'Middle', 'Old'])
        ->and(listed('sort=status'))->toBe(['New', 'Old', 'Middle']);
});

it('shows the chosen field columns, kept per user, and the page size', function () {
    $user = lister();
    $first = listProduct('First', ['tagline' => '<p>Hello <b>world</b></p>', 'price' => 12, 'kind' => 'tea', 'featured' => true]);
    listProduct('Second', ['related' => [$first->entry_id], 'featured' => false]);

    get('/admin/cms/collections/products/entries')->assertInertia(fn (Assert $page) => $page
        ->where('query.columns', ['status', 'updated_at'])
        ->where('query.perPage', 25)
        ->where('preferencesKey', 'table.cms.entries.products')
        ->where('entries.data.0.cells', [])
        // Groups and repeaters have no column.
        ->where('columns', fn ($columns) => collect($columns)->pluck('key')->all() === ['slug', 'status', 'author', 'published_at', 'updated_at', 'created_at',
            'field.tagline', 'field.price', 'field.launch', 'field.kind', 'field.featured', 'field.related'])
        ->where('columns.11.sortable', false));

    app(Preferences::class)->set($user, 'table.cms.entries.products', ['columns' => ['field.tagline', 'field.price', 'field.kind', 'field.featured', 'field.related', 'field.gone', 'author'], 'perPage' => 10]);
    get('/admin/cms/collections/products/entries')->assertInertia(fn (Assert $page) => $page
        ->where('query.columns', ['field.tagline', 'field.price', 'field.kind', 'field.featured', 'field.related', 'author'])
        ->where('query.perPage', 10)
        ->where('entries.data.0.author', 'Rina')
        ->where('entries.data.0.cells', ['field.tagline' => 'Hello world', 'field.price' => '12', 'field.kind' => 'Tea', 'field.featured' => 'Yes', 'field.related' => null])
        ->where('entries.data.1.cells', ['field.tagline' => null, 'field.price' => null, 'field.kind' => null, 'field.featured' => 'No', 'field.related' => 'First']));

    // The URL's page size wins; only the offered sizes count.
    get('/admin/cms/collections/products/entries?per_page=50')->assertInertia(fn (Assert $page) => $page->where('query.perPage', 50));
    get('/admin/cms/collections/products/entries?per_page=7')->assertInertia(fn (Assert $page) => $page->where('query.perPage', 10));

    // Another user has their own.
    lister();
    get('/admin/cms/collections/products/entries')->assertInertia(fn (Assert $page) => $page->where('query.columns', ['status', 'updated_at']));
});

it('pages by the page size', function () {
    foreach (range(1, 12) as $i) {
        listProduct(sprintf('Item %02d', $i));
    }
    lister();

    get('/admin/cms/collections/products/entries?per_page=10&page=2')->assertInertia(fn (Assert $page) => $page
        ->where('entries.total', 12)->where('entries.from', 11)->where('entries.lastPage', 2)
        ->where('entries.data.0.title', 'Item 11'));
});

it('offers Move up and down only in the manual order, unfiltered', function () {
    $this->products->forceFill(['ordered' => true])->save();
    listProduct('A');
    lister();

    get('/admin/cms/collections/products/entries')->assertInertia(fn (Assert $page) => $page->where('manualOrder', true));
    get('/admin/cms/collections/products/entries?sort=title')->assertInertia(fn (Assert $page) => $page->where('manualOrder', false));
    get('/admin/cms/collections/products/entries?status=draft')->assertInertia(fn (Assert $page) => $page->where('manualOrder', false));
});

it('links to the pages an editor needs next, those they may open', function () {
    $this->products->forceFill(['listing_route' => '/products'])->save();
    lister('cms.entries.view');

    get('/admin/cms/collections/products/entries')->assertInertia(fn (Assert $page) => $page
        ->where('links', [
            ['group' => 'Listing page', 'label' => 'Edit the listing page', 'href' => route('sunrice.admin.cms.collections.listing.edit', 'products')],
            ['group' => 'Listing page', 'label' => 'View the listing page', 'href' => url('/products'), 'external' => true],
        ]));

    lister('cms.entries.view', 'cms.terms.topics.view', 'cms.terms.topics.create', 'cms.terms.topics.update', 'cms.terms.topics.delete', 'cms.collections.update', 'cms.blueprints.view', 'cms.blueprints.create', 'cms.blueprints.update', 'cms.blueprints.delete', 'cms.fieldsets.view', 'cms.fieldsets.create', 'cms.fieldsets.update', 'cms.fieldsets.delete');
    $blueprint = Blueprint::query()->where('handle', 'product')->firstOrFail();
    get('/admin/cms/collections/products/entries')->assertInertia(fn (Assert $page) => $page
        ->where('links.2', ['group' => 'Terms', 'label' => 'Topics', 'href' => route('sunrice.admin.cms.taxonomies.terms.index', 'topics')])
        ->where('links.3', ['group' => 'Structure', 'label' => 'Collection settings', 'href' => route('sunrice.admin.cms.collections.edit', 'products')])
        ->where('links.4', ['group' => 'Structure', 'label' => 'Blueprint: Product', 'href' => route('sunrice.admin.cms.blueprints.edit', $blueprint->handle)]));
});

it('keeps scoped permissions: a collection\'s viewers see its list, nobody else', function () {
    Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'product']);
    listProduct('Tea');
    lister('cms.entries.pages.view');
    get('/admin/cms/collections/products/entries')->assertForbidden();
    get('/admin/cms/collections/products/entries/export')->assertForbidden();

    lister('cms.entries.products.view', 'cms.entries.products.export');
    expect(listed())->toBe(['Tea']);
    get('/admin/cms/collections/products/entries')->assertInertia(fn (Assert $page) => $page
        ->where('can.publish', false)->where('collections', [['handle' => 'products', 'title' => 'Products']]));
    get('/admin/cms/collections/products/entries/export')->assertOk();
});

it('exports what the list shows: filters, search, sort and language, with every field', function () {
    lister('cms.entries.view', 'cms.entries.export');
    $first = listProduct('Assam', ['price' => 20, 'kind' => 'tea'], publish: true);
    listProduct('Sencha', ['price' => 5, 'related' => [$first->entry_id]]);
    listProduct('Mug', ['price' => 1]);
    listProduct('Gone')->entry->delete();

    $csv = get('/admin/cms/collections/products/entries/export?status=draft&search=a&sort=-field.price')->assertOk()->streamedContent();
    $rows = array_map(str_getcsv(...), array_filter(explode("\n", $csv)));

    expect($rows[0])->toBe(['ID', 'Title', 'Slug', 'Status', 'Published', 'URL', 'Updated', 'Author', 'Tagline', 'Price', 'Launch', 'Kind', 'Featured', 'Related', 'Gallery'])
        ->and(array_column(array_slice($rows, 1), 1))->toBe(['Sencha'])
        ->and($rows[1][7])->toBe('Rina')
        ->and($rows[1][13])->toBe('Assam');

    $csv = get('/admin/cms/collections/products/entries/export?sort=-field.price')->streamedContent();
    expect(array_column(array_slice(array_map(str_getcsv(...), array_filter(explode("\n", $csv))), 1), 1))->toBe(['Assam', 'Sencha', 'Mug']);
    $csv = get('/admin/cms/collections/products/entries/export?status=trashed')->streamedContent();
    expect($csv)->toContain('Gone')->toContain('trashed')->not->toContain('Assam');
});

it('exports links as their address, rich text as text, and names a field that shares a column\'s label', function () {
    Blueprint::query()->create(['handle' => 'listing', 'title' => 'Listing', 'fields' => [
        ['handle' => 'property_status', 'type' => 'select', 'label' => 'Status', 'config' => ['options' => [['value' => 'available', 'label' => 'Available']]]],
        ['handle' => 'map', 'type' => 'link', 'label' => 'Map'],
        ['handle' => 'agent_page', 'type' => 'link', 'label' => 'Agent page'],
        ['handle' => 'body', 'type' => 'rich_text', 'label' => 'Body'],
    ]]);
    $listings = Collection::query()->create(['handle' => 'listings', 'title' => 'Listings', 'blueprint' => 'listing', 'route' => '/listings/{slug}']);
    lister('cms.entries.view', 'cms.entries.export');
    $agent = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($listings, ['title' => 'Anindya']));
    app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($listings, ['title' => 'Villa', 'data' => [
        'property_status' => 'available',
        'map' => ['type' => 'url', 'url' => 'https://maps.google.com/?q=Canggu', 'label' => null, 'new_tab' => false],
        'agent_page' => ['type' => 'entry', 'entry_id' => $agent->entry_id],
        'body' => '<p>Five <strong>bedrooms</strong>.</p><p>A pool &amp; garden.</p>',
    ]]));

    $rows = csvRows(get('/admin/cms/collections/listings/entries/export?sort=title')->streamedContent());

    expect($rows[0])->toBe(['ID', 'Title', 'Slug', 'Status', 'Published', 'URL', 'Updated', 'Author', 'Status (field)', 'Map', 'Agent page', 'Body'])
        ->and(array_slice($rows[2], 8))->toBe(['Available', 'https://maps.google.com/?q=Canggu', url('/listings/anindya'), "Five bedrooms.\nA pool & garden."]);
});

/** @return list<list<string|null>> a CSV's rows, cells with line breaks kept */
function csvRows(string $csv): array
{
    $handle = fopen('php://memory', 'r+');
    fwrite($handle, $csv);
    rewind($handle);
    $rows = [];
    while (($row = fgetcsv($handle, escape: '')) !== false) {
        $rows[] = $row;
    }
    fclose($handle);

    return $rows;
}

it('exports terms and linked entries by title, repeaters and blocks as readable lines, and rich text with its breaks', function () {
    Fieldset::query()->create(['handle' => 'hero', 'title' => 'Hero', 'fields' => [['handle' => 'heading', 'type' => 'text', 'label' => 'Heading']]]);
    Blueprint::query()->create(['handle' => 'home', 'title' => 'Home', 'fields' => [
        ['handle' => 'type', 'type' => 'terms', 'label' => 'Property type', 'config' => ['taxonomy' => 'topics']],
        ['handle' => 'amenities', 'type' => 'terms', 'label' => 'Amenities', 'config' => ['taxonomy' => 'topics', 'multiple' => true]],
        ['handle' => 'features', 'type' => 'repeater', 'label' => 'Features', 'fields' => [
            ['handle' => 'label', 'type' => 'text', 'label' => 'Label'],
            ['handle' => 'detail', 'type' => 'rich_text', 'label' => 'Detail'],
        ]],
        ['handle' => 'contact', 'type' => 'group', 'label' => 'Contact', 'fields' => [['handle' => 'phone', 'type' => 'text', 'label' => 'Phone']]],
        ['handle' => 'sections', 'type' => 'flexible', 'label' => 'Sections', 'config' => ['blocks' => ['hero']]],
        ['handle' => 'body', 'type' => 'rich_text', 'label' => 'Body'],
    ]]);
    $homes = Collection::query()->create(['handle' => 'homes', 'title' => 'Homes', 'blueprint' => 'home', 'route' => '/homes/{slug}']);
    lister('cms.entries.view', 'cms.entries.export', 'cms.terms.create', 'cms.terms.update');
    $villa = app(SaveTerm::class)->handle($this->topics, ['title' => 'Villa']);
    $pool = app(SaveTerm::class)->handle($this->topics, ['title' => 'Pool']);
    $garden = app(SaveTerm::class)->handle($this->topics, ['title' => 'Garden']);
    app(CreateEntry::class)->handle($homes, ['title' => 'Canggu house', 'data' => [
        'type' => $villa->id,
        'amenities' => [$pool->id, $garden->id],
        'features' => [
            ['label' => 'Freehold', 'detail' => '<p>Clean <em>title</em></p>'],
            ['label' => 'Draft row', '_hidden' => true],
        ],
        'contact' => ['phone' => '+62 361 555'],
        'sections' => [['type' => 'hero', 'values' => ['heading' => 'Homes of quiet distinction']]],
        'body' => '<h2>The space</h2><p>140 m² open floor</p><ul><li>Mezzanine</li><li>Glass&nbsp;frontage</li></ul><p>One<br>Two</p>',
    ]]);

    $rows = csvRows(get('/admin/cms/collections/homes/entries/export')->streamedContent());
    $row = array_combine($rows[0], $rows[1]);

    expect($row['Property type'])->toBe('Villa')
        ->and($row['Amenities'])->toBe('Pool, Garden')
        ->and($row['Features'])->toBe("Label: Freehold; Detail: Clean title\n(hidden) Label: Draft row")
        ->and($row['Contact'])->toBe('Phone: +62 361 555')
        ->and($row['Sections'])->toBe('Hero: Heading: Homes of quiet distinction')
        ->and($row['Body'])->toBe("The space\n140 m² open floor\nMezzanine\nGlass frontage\nOne\nTwo")
        ->and(implode(',', $rows[1]))->not->toContain('_id')->not->toContain('_key');
});

it('takes as many queries for many entries as for a few', function () {
    $user = lister();
    app(Preferences::class)->set($user, 'table.cms.entries.products', ['columns' => ['author', 'field.related', 'field.kind', 'status']]);
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        get('/admin/cms/collections/products/entries')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };
    $first = listProduct('First', ['kind' => 'tea']);
    listProduct('Second', ['related' => [$first->entry_id]]);
    $count(); // once, for what's loaded once per process (permissions)
    $few = $count();
    foreach (range(1, 6) as $i) {
        listProduct("More {$i}", ['related' => [$first->entry_id], 'kind' => 'coffee']);
    }

    expect($count())->toBe($few);
});

it('shows the trash with "Trashed" rows, at ?status=trash too, and undoes a trashing from the toast', function () {
    $gone = listProduct('Gone', publish: true);
    $kept = listProduct('Kept', publish: true);
    lister('cms.entries.view', 'cms.entries.delete');

    // From the editor: the toast's Undo restores the entry.
    delete("/admin/cms/entries/{$gone->entry_id}")
        ->assertRedirect('/admin/cms/collections/products/entries')
        ->assertSessionHas('success', 'Moved to the trash.')
        ->assertSessionHas('success_action', ['label' => 'Undo', 'url' => route('sunrice.admin.cms.entries.restore', $gone->entry_id)]);

    get('/admin/cms/collections/products/entries?status=trash')->assertInertia(fn (Assert $page) => $page
        ->where('query.status', 'trashed')
        ->where('entries.data.0.title', 'Gone')
        ->where('entries.data.0.trashed', true)
        ->where('entries.data.0.status', 'published'));
    get('/admin/cms/collections/products/entries')->assertInertia(fn (Assert $page) => $page->where('entries.data.0.trashed', false));

    post('/admin/cms/entries/'.$gone->entry_id.'/restore')->assertSessionHas('success', 'Restored.');
    expect(listed())->toContain('Gone');

    // In bulk: Undo restores the same entries.
    post('/admin/cms/collections/products/entries/bulk', ['action' => 'trash', 'ids' => [$gone->entry_id, $kept->entry_id]])
        ->assertSessionHas('success', '2 entries moved to the trash.')
        ->assertSessionHas('success_action', ['label' => 'Undo', 'url' => route('sunrice.admin.cms.collections.entries.bulk', 'products'), 'data' => ['action' => 'restore', 'ids' => [$gone->entry_id, $kept->entry_id]]]);
    post('/admin/cms/collections/products/entries/bulk', ['action' => 'restore', 'ids' => [$gone->entry_id, $kept->entry_id]])
        ->assertSessionMissing('success_action');
    expect(listed())->toContain('Gone', 'Kept');
});

it('gives each row its address on the site, not only its slug', function () {
    listProduct('Assam Gold');
    $quotes = Collection::query()->create(['handle' => 'quotes', 'title' => 'Quotes']);
    app(CreateEntry::class)->handle($quotes, ['title' => 'Lovely']);
    lister();

    get('/admin/cms/collections/products/entries')->assertInertia(fn (Assert $page) => $page->where('entries.data.0.path', '/products/assam-gold'));
    get('/admin/cms/collections/quotes/entries')->assertInertia(fn (Assert $page) => $page->where('entries.data.0.path', null));
});

it('groups long numbers in the list, and keeps them as typed in the CSV', function () {
    listProduct('Villa', ['price' => 18500000000]);
    $user = lister('cms.entries.view', 'cms.entries.export');
    app(Preferences::class)->set($user, 'table.cms.entries.products', ['columns' => ['field.price']]);

    get('/admin/cms/collections/products/entries')->assertInertia(fn (Assert $page) => $page->where('entries.data.0.cells', ['field.price' => '18,500,000,000']));
    expect(get('/admin/cms/collections/products/entries/export')->streamedContent())->toContain(',18500000000,');
});
