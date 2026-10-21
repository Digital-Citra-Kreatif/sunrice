<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Collection;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    $this->properties = Collection::query()->create(['handle' => 'properties', 'title' => 'Properties']);
});

it('offers the entry search to the command palette on every panel page, not only CMS pages', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'sunrice.media.view'));

    foreach (['/admin', '/admin/media'] as $page) {
        get($page)->assertOk()->assertInertia(fn (Assert $inertia) => $inertia->where('sunrice.commandSearches', [
            ['id' => 'cms.entries', 'url' => route('sunrice.admin.cms.entries.search'), 'minLength' => 2],
        ]));
    }
});

it('finds entries by their title in any language, once per entry, within what the person may view', function () {
    $menteng = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->properties, ['title' => 'Menteng Heritage Residence']));
    $garden = app(CreateEntry::class)->handle($this->properties, ['title' => 'Garden House']);
    $rumah = app(CreateTranslation::class)->handle($garden->entry, 'id');
    app(SaveDraft::class)->handle($rumah, ['title' => 'Rumah Taman Menteng']);
    $trashed = app(CreateEntry::class)->handle($this->properties, ['title' => 'Menteng Gone']);
    $trashed->entry->delete();

    actingAs(adminUser(Panel::ACCESS));
    getJson('/admin/cms/entries/search?q=menteng')->assertForbidden();

    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view'));
    $items = getJson('/admin/cms/entries/search?q=menteng')->assertOk()->json('items');

    expect($items)->toHaveCount(2)
        ->and($items[0])->toBe([
            'id' => $menteng->entry_id,
            'label' => 'Menteng Heritage Residence',
            'description' => 'Properties',
            'group' => 'Entries',
            'icon' => 'file-text',
            'url' => route('sunrice.admin.cms.collections.entries.edit', ['collection' => 'properties', 'entry' => $menteng->entry_id]),
        ])
        ->and($items[1]['label'])->toBe('Rumah Taman Menteng')
        ->and($items[1]['description'])->toBe('Properties · ID · draft')
        ->and($items[1]['url'])->toBe(route('sunrice.admin.cms.collections.entries.edit', ['collection' => $garden->entry->collection->handle, 'entry' => $garden->entry_id, 'locale' => 'id']));

    getJson('/admin/cms/entries/search?q=rumah taman')->assertJsonPath('items.0.id', $garden->entry_id);
    getJson('/admin/cms/entries/search?q=')->assertExactJson(['items' => []]);
});
