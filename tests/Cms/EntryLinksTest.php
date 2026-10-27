<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Access\Authorizable;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Content;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => [['handle' => 'intro', 'type' => 'textarea']]]);
    $this->pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page', 'route' => '/{slug}']);
    $this->entry = app(CreateEntry::class)->handle($this->pages, ['title' => 'About', 'data' => []]);
});

it('gives the entry page the links plugins add, for whoever looks', function () {
    Content::entryLinksUsing(fn (EntryTranslation $entry, Authorizable $user): array => $user->can('cms.entries.update')
        ? [['label' => 'Design this page', 'href' => '/admin/designer?entry='.$entry->entry_id]]
        : []);
    Content::entryLinksUsing(fn (): array => [['label' => 'Sales', 'href' => 'https://shop.example.com/sales']]);

    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update'));
    get(entryEditUrl($this->entry->entry_id))->assertInertia(fn (Assert $page) => $page
        ->component('Cms/Entries/Edit', false)
        ->where('links', [
            ['label' => 'Design this page', 'href' => '/admin/designer?entry='.$this->entry->entry_id],
            ['label' => 'Sales', 'href' => 'https://shop.example.com/sales'],
        ]));

    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view'));
    get(entryEditUrl($this->entry->entry_id))->assertInertia(fn (Assert $page) => $page
        ->where('links', [['label' => 'Sales', 'href' => 'https://shop.example.com/sales']]));
});

it('leaves out links that are not links to follow', function () {
    Content::entryLinksUsing(fn (): array => [
        ['label' => 'Script', 'href' => 'javascript:alert(1)'],
        ['label' => 'Elsewhere', 'href' => '//evil.example.com'],
        ['label' => 'Backslash', 'href' => '/\\evil.example.com'],
        ['label' => '', 'href' => '/admin'],
        ['href' => '/admin'],
        ['label' => 'Fine', 'href' => '/admin/fine'],
    ]);

    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view'));
    get(entryEditUrl($this->entry->entry_id))->assertInertia(fn (Assert $page) => $page
        ->where('links', [['label' => 'Fine', 'href' => '/admin/fine']]));
});

it('has no links without hooks', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view'));
    get(entryEditUrl($this->entry->entry_id))->assertInertia(fn (Assert $page) => $page->where('links', []));
});
