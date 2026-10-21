<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Fields\Field;
use Sunrice\Cms\Fields\HydrationContext;
use Sunrice\Cms\Fields\Reference;
use Sunrice\Cms\Fields\Schema;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(function () {
    actingAs(adminUser());
    $this->projects = Collection::query()->create(['handle' => 'projects', 'title' => 'Projects']);
    $this->pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages']);
});

function entryIn(Collection $collection, string $title, bool $publish = true): EntryTranslation
{
    $translation = app(CreateEntry::class)->handle($collection, ['title' => $title]);

    return $publish ? app(PublishEntry::class)->handle($translation) : $translation;
}

function relationSchema(array $config = []): Schema
{
    return Schema::make([Field::make('related', 'entries', ['collections' => ['projects'], ...$config])]);
}

it('accepts only existing entries of the chosen collections', function () {
    $tower = entryIn($this->projects, 'Tower');
    $about = entryIn($this->pages, 'About');
    $gone = entryIn($this->projects, 'Gone');
    $gone->entry->delete();
    $errors = fn (Schema $schema, array $data) => Validator::make(['data' => $data], $schema->rules($data))->errors()->keys();

    expect($errors(relationSchema(), ['related' => $tower->entry_id]))->toBe([])
        ->and($errors(relationSchema(), ['related' => $about->entry_id]))->toBe(['data.related'])
        ->and($errors(relationSchema(), ['related' => $gone->entry_id]))->toBe(['data.related'])
        ->and($errors(relationSchema(['multiple' => true, 'max' => 1]), ['related' => [$tower->entry_id, $about->entry_id]]))->toContain('data.related', 'data.related.1');
});

it('gives templates the linked entries in the page\'s language, published ones only', function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    $tower = entryIn($this->projects, 'Tower');
    $menara = app(CreateTranslation::class)->handle($tower->entry, 'id');
    app(SaveDraft::class)->handle($menara, ['title' => 'Menara']);
    app(PublishEntry::class)->handle($menara);
    $bridge = entryIn($this->projects, 'Bridge');
    $draft = entryIn($this->projects, 'Draft', publish: false);
    $schema = relationSchema(['multiple' => true]);
    $data = $schema->normalize(['related' => [(string) $bridge->entry_id, $tower->entry_id, $draft->entry_id, 999]]);

    DB::enableQueryLog();
    $english = $schema->hydrate($data, new HydrationContext('en'))['related'];
    expect($english->map->title->all())->toBe(['Bridge', 'Tower'])
        ->and(count(DB::getQueryLog()))->toBeLessThanOrEqual(3);

    expect($schema->hydrate($data, new HydrationContext('id'))['related']->map->title->all())->toBe(['Bridge', 'Menara'])
        ->and($schema->hydrate($data, new HydrationContext('en', preview: true))['related']->map->draft_title->all())->toBe(['Bridge', 'Tower', 'Draft'])
        ->and($schema->references($data))->toContainEqual(new Reference('entry', $tower->entry_id, 'related'));
});

it('finds entries for the picker', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    $tower = entryIn($this->projects, 'Tower');
    $bridge = entryIn($this->projects, 'Bridge', publish: false);
    entryIn($this->pages, 'Tower page');

    actingAs(adminUser(Panel::ACCESS));
    getJson('/admin/cms/entry-picker?search=tow')->assertForbidden();

    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view'));
    getJson('/admin/cms/entry-picker?search=TOW&collections[]=projects')->assertOk()
        ->assertExactJson(['items' => [['id' => $tower->entry_id, 'title' => 'Tower', 'collection' => 'Projects', 'status' => 'published']], 'unavailable' => []]);
    getJson("/admin/cms/entry-picker?ids[]={$bridge->entry_id}&ids[]={$tower->entry_id}")
        ->assertJsonPath('items.0.title', 'Bridge')->assertJsonPath('items.0.status', 'draft')->assertJsonPath('items.1.title', 'Tower');
});

it('lets the picker link to live entries of the collections a user\'s own collections link to, titles only', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    $agents = Collection::query()->create(['handle' => 'agents', 'title' => 'Agents']);
    $notes = Collection::query()->create(['handle' => 'notes', 'title' => 'Notes']);
    \Sunrice\Cms\Models\Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [
        ['handle' => 'body', 'type' => 'group', 'fields' => [['handle' => 'author', 'type' => 'entries', 'config' => ['collections' => ['agents']]]]],
    ]]);
    Collection::query()->create(['handle' => 'journal', 'title' => 'Journal', 'blueprint' => 'post']);
    $rafael = entryIn($agents, 'Rafael');
    app(SaveDraft::class)->handle($rafael, ['title' => 'Rafael (draft title)']);
    $hidden = entryIn($agents, 'Rafaela', publish: false);
    entryIn($notes, 'Rafael notes');

    // Citra writes the journal only: she may link to live agents, by their live title, but not to notes.
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.journal.view', 'cms.entries.journal.create', 'cms.entries.update-own'));
    getJson('/admin/cms/entry-picker?search=Rafa&collections[]=agents')->assertOk()
        ->assertExactJson(['items' => [['id' => $rafael->entry_id, 'title' => 'Rafael', 'collection' => 'Agents', 'status' => 'published']], 'unavailable' => []]);
    getJson("/admin/cms/entry-picker?ids[]={$rafael->entry_id}&ids[]={$hidden->entry_id}&collections[]=agents")->assertJsonCount(1, 'items');
    // Not in the command palette, which asks for no collection: its results open the editor.
    getJson('/admin/cms/entry-picker?search=Rafa')->assertExactJson(['items' => [], 'unavailable' => []]);
    // What she can't see is named, so "nothing found" isn't mistaken for "doesn't exist".
    getJson('/admin/cms/entry-picker?search=Rafa&collections[]=notes')->assertOk()
        ->assertExactJson(['items' => [], 'unavailable' => ['Notes']]);

    // Viewing only (no writing) gives nothing more.
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.journal.view'));
    getJson('/admin/cms/entry-picker?search=Rafa&collections[]=agents')->assertExactJson(['items' => [], 'unavailable' => ['Agents']]);
});
