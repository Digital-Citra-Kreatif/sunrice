<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Globals\SaveGlobals;
use Sunrice\Cms\Admin\RevisionList;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Fieldset;
use Sunrice\Cms\Models\GlobalSet;
use Sunrice\Cms\Support\SearchText;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 * Groups, repeater rows and flexible blocks inside each other, two and
 * three levels deep: validation, conditions, shared fields, hydration,
 * search text and references.
 */

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.create', 'cms.entries.update', 'cms.entries.publish'));
    Fieldset::query()->create(['handle' => 'pricing', 'title' => 'Pricing', 'fields' => [
        ['handle' => 'heading', 'type' => 'text'],
        ['handle' => 'rows', 'type' => 'repeater', 'label' => 'Plans', 'config' => ['min' => 1], 'fields' => [
            ['handle' => 'name', 'type' => 'text', 'label' => 'Plan name'],
            ['handle' => 'price', 'type' => 'number', 'label' => 'Unit price', 'required' => true],
            ['handle' => 'kind', 'type' => 'select', 'config' => ['default' => 'plain', 'options' => [['value' => 'plain', 'label' => 'Plain'], ['value' => 'video', 'label' => 'Video']]]],
            ['handle' => 'video', 'type' => 'text', 'label' => 'Video address', 'required' => true, 'condition' => ['field' => 'kind', 'operator' => 'equals', 'value' => 'video']],
            ['handle' => 'details', 'type' => 'group', 'fields' => [
                ['handle' => 'note', 'type' => 'text'],
                ['handle' => 'photo', 'type' => 'media'],
            ]],
        ]],
    ]]);
    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => [
        ['handle' => 'sections', 'type' => 'flexible', 'config' => ['blocks' => ['pricing']]],
        ['handle' => 'cta', 'type' => 'link'],
    ]]);
    $this->pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page', 'route' => '/{slug}', 'translatable' => true]);
});

function pricingBlock(array $rows, array $block = []): array
{
    return ['id' => 'b1', 'type' => 'pricing', 'values' => ['heading' => 'Prices', 'rows' => $rows]] + $block;
}

function nestedErrors(Closure $save): array
{
    try {
        $save();
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
}

it('names a field deep inside rows and blocks by its label in errors', function () {
    $errors = nestedErrors(fn () => app(CreateEntry::class)->handle($this->pages, ['title' => 'Home', 'data' => [
        'sections' => [pricingBlock([['_id' => 'r1', 'name' => 'Basic']])],
    ]]));

    expect($errors)->toHaveKey('data.sections.0.values.rows.0.price')
        ->and($errors['data.sections.0.values.rows.0.price'][0])->toContain('Unit price');
});

it('requires a field in a row of a block only while its condition holds', function () {
    $create = fn (string $kind) => nestedErrors(fn () => app(CreateEntry::class)->handle($this->pages, ['title' => 'Home '.$kind, 'data' => [
        'sections' => [pricingBlock([['_id' => 'r1', 'price' => 5, 'kind' => $kind]])],
    ]]));

    expect($create('plain'))->toBe([])
        ->and($create('video'))->toHaveKey('data.sections.0.values.rows.0.video');
});

it('searches the words of rows and blocks, not their ids, kinds or hidden ones', function () {
    $text = SearchText::of([
        'sections' => [
            pricingBlock([['_id' => 'r1', 'name' => 'Basic', 'price' => 5, '_hidden' => false], ['_id' => 'r2', 'name' => 'Secret', 'price' => 9, '_hidden' => true]], ['key' => 'plans', 'hidden' => false]),
            ['id' => '01JAAAAAAAAAAAAAAAAAAAAAAA', 'type' => 'pricing', 'key' => null, 'hidden' => true, 'values' => ['heading' => 'Draft prices']],
        ],
        'cta' => ['type' => 'url', 'url' => 'https://example.com', 'label' => 'Book now', 'new_tab' => false],
    ], Blueprint::query()->where('handle', 'page')->firstOrFail()->schema());

    expect($text)->toBe('Prices Basic Book now');
});

function nestedMedia(string $name): Media
{
    return Media::query()->create(['disk' => 'public', 'path' => 'media/'.$name, 'filename' => $name, 'mime_type' => 'image/jpeg', 'size' => 1]);
}

it('lets another language add a row of its own to a block, though a shared field in it is required', function () {
    $en = app(CreateEntry::class)->handle($this->pages, ['title' => 'Home', 'data' => [
        'sections' => [pricingBlock([['_id' => 'r1', 'name' => 'Basic', 'price' => 5]])],
    ]]);
    $id = app(CreateTranslation::class)->handle($en->entry, 'id');

    $errors = nestedErrors(fn () => app(SaveDraft::class)->handle($id, ['data' => [
        'sections' => [pricingBlock([['_id' => 'r1', 'name' => 'Dasar'], ['name' => 'Tambahan']])],
    ]]));

    expect($errors)->toBe([]);
    $rows = $id->fresh()?->completeData(draft: true)['sections'][0]['values']['rows'] ?? [];
    expect($rows[0]['name'])->toBe('Dasar')->and($rows[0]['price'])->toBe(5)->and($rows[1]['name'])->toBe('Tambahan');
});

it('hydrates and gives as data what rows inside blocks point at', function () {
    $photo = nestedMedia('a.jpg');
    $en = app(CreateEntry::class)->handle($this->pages, ['title' => 'Home', 'data' => [
        'sections' => [pricingBlock([['_id' => 'r1', 'name' => 'Basic', 'price' => 5, 'details' => ['note' => 'Hi', 'photo' => $photo->id]]])],
    ]]);
    $live = app(PublishEntry::class)->handle($en);
    $id = app(CreateTranslation::class)->handle($en->entry, 'id');
    app(SaveDraft::class)->handle($id, ['data' => ['sections' => [pricingBlock([['_id' => 'r1', 'name' => 'Dasar', 'details' => ['note' => 'Halo']]])]]]);
    $id = app(PublishEntry::class)->handle($id->fresh() ?? $id);

    $row = $id->fresh()?->values()['sections'][0]->rows[0];
    expect($row['name'])->toBe('Dasar')->and($row['price'])->toBe(5)->and($row['details']['note'])->toBe('Halo')
        ->and($row['details']['photo'])->toBeInstanceOf(Media::class);

    $data = $id->fresh()?->toData(['sections']);
    expect($data['fields']['sections'][0]['values']['rows'][0]['details']['photo']['id'] ?? null)->toBe($photo->id);
});

it('compares rows and blocks by what they hold, not by the order of their keys or empty values', function () {
    $live = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->pages, ['title' => 'Home', 'data' => [
        'sections' => [pricingBlock([['_id' => 'r1', 'name' => 'Basic', 'price' => 5, 'details' => ['note' => 'Hi']]])],
    ]]));

    // The same, written in another order (as an AI tool may send it), and a note emptied in a row that had none.
    app(SaveDraft::class)->handle($live, ['data' => ['sections' => [
        ['type' => 'pricing', 'id' => 'b1', 'values' => ['rows' => [['price' => 5, 'details' => ['note' => 'Hi', 'photo' => null], 'name' => 'Basic', '_id' => 'r1', 'kind' => '']], 'heading' => 'Prices']],
    ]]]);

    expect(RevisionList::compareLive($live->fresh() ?? $live))->toBe([]);
});

it('gives the editor the defaults new rows and blocks start with', function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
    $entry = app(CreateEntry::class)->handle($this->pages, ['title' => 'Home']);

    get(entryEditUrl($entry->entry_id))->assertInertia(fn (Assert $page) => $page
        ->where('fields.0.blocks.0.defaults', ['heading' => null, 'rows' => []])
        ->where('fields.0.blocks.0.fields.1.defaults', ['name' => null, 'price' => null, 'kind' => 'plain', 'video' => null, 'details' => ['note' => null, 'photo' => null]]));
});

it('lets another language of a global set add a row of its own to a block, its shared fields still checked', function () {
    $set = GlobalSet::query()->create(['handle' => 'prices', 'title' => 'Prices', 'blueprint' => 'page']);
    $save = fn (array $rows, string $locale) => nestedErrors(fn () => app(SaveGlobals::class)->handle($set->fresh() ?? $set, ['sections' => [pricingBlock($rows)]], $locale));

    expect($save([['_id' => 'r1', 'name' => 'Basic', 'price' => 5]], 'en'))->toBe([])
        ->and($save([['_id' => 'r1', 'name' => 'Dasar'], ['_id' => 'r2', 'name' => 'Tambahan']], 'id'))->toBe([])
        ->and($save([['_id' => 'r1', 'name' => 'Dasar'], ['_id' => 'r2', 'name' => 'Tambahan', 'price' => 'lots']], 'id'))->toHaveKey('data.sections.0.values.rows.1.price')
        // The main language still requires it.
        ->and($save([['_id' => 'r1', 'name' => 'Basic']], 'en'))->toHaveKey('data.sections.0.values.rows.0.price');

    $rows = ($set->fresh() ?? $set)->data('id')['sections'][0]['values']['rows'];
    expect($rows[0]['name'])->toBe('Dasar')->and($rows[0]['price'])->toBe(5)->and($rows[1]['name'])->toBe('Tambahan');
});
