<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Access\Authorizable;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\CheckboxList;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\Select;
use Sunrice\Admin\Resources\Forms\Toggle;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Content;
use Sunrice\Cms\Editor\EditorCard;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\Term;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => [['handle' => 'intro', 'type' => 'textarea']]]);
    $this->pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page', 'route' => '/{slug}']);
    $this->posts = Collection::query()->create(['handle' => 'posts', 'title' => 'Posts', 'blueprint' => 'page', 'route' => '/blog/{slug}']);
    $this->entry = app(CreateEntry::class)->handle($this->pages, ['title' => 'About', 'data' => []]);
    $this->topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics']);
    $this->saved = [];
});

/** A card that keeps its values in the test: who may view (roles), and whether it's members only. */
function accessCard(object $test): EditorCard
{
    return EditorCard::make('access', 'Access')->description('Who may open it.')
        ->entries(fn (Collection $collection): bool => $collection->handle === 'pages')
        ->terms()
        ->form(fn (Entry|Term $record, Form $form): Form => $form->schema([
            Toggle::make('restricted')->label('Members only'),
            Select::make('mode')->label('Who')->options(['any' => 'Any member', 'roles' => 'These roles'])->required()->default('any'),
            CheckboxList::make('roles')->label('Roles')->options(['gold' => 'Gold', 'silver' => 'Silver']),
        ]))
        ->load(fn (Entry|Term $record): array => $test->saved[$record::class.$record->id] ?? ['restricted' => false])
        ->save(function (Entry|Term $record, array $values, Authorizable $user) use ($test): void {
            $test->saved[$record::class.$record->id] = $values;
        });
}

it('shows a plugin\'s card in the entry editor, with its fields and values', function () {
    Content::editorCard(accessCard($this));
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update'));

    get(entryEditUrl($this->entry->entry_id))->assertInertia(fn (Assert $page) => $page
        ->component('Cms/Entries/Edit', false)
        ->has('cards', 1)
        ->where('cards.0.key', 'access')
        ->where('cards.0.title', 'Access')
        ->where('cards.0.description', 'Who may open it.')
        ->where('cards.0.values', ['restricted' => false, 'mode' => 'any', 'roles' => []])
        ->where('cards.0.url', "/admin/cms/entries/{$this->entry->entry_id}/cards/access")
        ->has('cards.0.sections.0.fields', 3));
});

it('saves a card\'s checked values through the plugin, apart from the draft', function () {
    Content::editorCard(accessCard($this));
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update'));
    $id = $this->entry->entry_id;

    put("/admin/cms/entries/{$id}/cards/access", ['values' => ['restricted' => true, 'mode' => 'roles', 'roles' => ['gold']]])
        ->assertRedirect()->assertSessionHas('success', 'Access saved.');
    expect($this->saved[Entry::class.$id])->toBe(['restricted' => true, 'mode' => 'roles', 'roles' => ['gold']]);

    // A switch left out is off; values not on the form are not passed.
    put("/admin/cms/entries/{$id}/cards/access", ['values' => ['mode' => 'any', 'other' => 'x']])->assertSessionHasNoErrors();
    expect($this->saved[Entry::class.$id])->toBe(['restricted' => false, 'mode' => 'any', 'roles' => []]);

    get(entryEditUrl($id))->assertInertia(fn (Assert $page) => $page->where('cards.0.values.mode', 'any'));
});

it('checks the values with the fields\' rules', function () {
    Content::editorCard(accessCard($this));
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update'));

    put("/admin/cms/entries/{$this->entry->entry_id}/cards/access", ['values' => ['mode' => 'everyone', 'roles' => ['platinum']]])
        ->assertSessionHasErrors(['mode', 'roles.0']);
    expect($this->saved)->toBe([]);
});

it('shows the card only where it belongs, to who may update the entry and passes its check', function () {
    Content::editorCard(accessCard($this)->can(fn (Authorizable $user): bool => $user->can('cms.entries.publish')));
    $post = app(CreateEntry::class)->handle($this->posts, ['title' => 'Hello', 'data' => []]);

    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update'));
    get(entryEditUrl($this->entry->entry_id))->assertInertia(fn (Assert $page) => $page->where('cards', []));
    put("/admin/cms/entries/{$this->entry->entry_id}/cards/access", ['values' => ['mode' => 'any']])->assertNotFound();

    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update', 'cms.entries.publish'));
    get(entryEditUrl($this->entry->entry_id))->assertInertia(fn (Assert $page) => $page->has('cards', 1));
    // Not on posts.
    get(entryEditUrl($post->entry_id))->assertInertia(fn (Assert $page) => $page->where('cards', []));
    put("/admin/cms/entries/{$post->entry_id}/cards/access", ['values' => ['mode' => 'any']])->assertNotFound();

    // Who may only view entries neither sees nor saves it.
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.publish'));
    get(entryEditUrl($this->entry->entry_id))->assertInertia(fn (Assert $page) => $page->where('cards', []));
    put("/admin/cms/entries/{$this->entry->entry_id}/cards/access", ['values' => ['mode' => 'any']])->assertForbidden();
});

it('shows and saves a card on terms', function () {
    Content::editorCard(accessCard($this));
    $news = app(SaveTerm::class)->handle($this->topics, ['title' => 'News']);
    actingAs(adminUser(Panel::ACCESS, 'cms.terms.view', 'cms.terms.update'));

    get("/admin/cms/taxonomies/topics/terms/{$news->id}/edit")->assertInertia(fn (Assert $page) => $page
        ->has('cards', 1)->where('cards.0.url', "/admin/cms/terms/{$news->id}/cards/access"));
    put("/admin/cms/terms/{$news->id}/cards/access", ['values' => ['restricted' => true, 'mode' => 'any']])->assertSessionHasNoErrors();
    expect($this->saved[Term::class.$news->id])->toMatchArray(['restricted' => true]);

    actingAs(adminUser(Panel::ACCESS, 'cms.terms.view'));
    get("/admin/cms/taxonomies/topics/terms/{$news->id}/edit")->assertInertia(fn (Assert $page) => $page->where('cards', []));
    put("/admin/cms/terms/{$news->id}/cards/access", ['values' => ['mode' => 'any']])->assertForbidden();
});

it('refuses a bad key and a key used twice', function () {
    expect(fn () => EditorCard::make('Access Card', 'Access'))->toThrow(InvalidArgumentException::class);
    Content::editorCard(EditorCard::make('access', 'Access'));
    expect(fn () => Content::editorCard(EditorCard::make('access', 'Other')))->toThrow(InvalidArgumentException::class);
});
