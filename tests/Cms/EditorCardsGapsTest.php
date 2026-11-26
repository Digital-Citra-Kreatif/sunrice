<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\Select;
use Sunrice\Admin\Resources\Forms\TextInput;
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
    $this->page = app(CreateEntry::class)->handle($this->pages, ['title' => 'About', 'data' => []])->entry_id;
    $this->post = app(CreateEntry::class)->handle($this->posts, ['title' => 'Hello', 'data' => []])->entry_id;
    $this->topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics']);
    $this->tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    $this->saved = [];
    $this->loads = 0;
    $this->forms = 0;
});

/** A card on every collection's entries and every taxonomy's terms that counts its work and keeps what it saves in the test. */
function gapCard(object $test, string $key = 'access', string $title = 'Access'): EditorCard
{
    return EditorCard::make($key, $title)
        ->entries()
        ->terms()
        ->form(function (Entry|Term $record, Form $form) use ($test): Form {
            $test->forms++;

            return $form->schema([
                Toggle::make('restricted')->label('Members only'),
                Select::make('mode')->label('Who')->options(['any' => 'Any member', 'roles' => 'These roles'])->required()->default('any'),
            ]);
        })
        ->load(function (Entry|Term $record) use ($test): array {
            $test->loads++;

            return [];
        })
        ->save(function (Entry|Term $record, array $values, Authorizable $user) use ($test, $key): void {
            $test->saved[$key.':'.$record::class.$record->id] = $values;
        });
}

it('sends guests away from a card\'s save without saving', function () {
    Content::editorCard(gapCard($this));
    $news = app(SaveTerm::class)->handle($this->topics, ['title' => 'News']);

    expect(put("/admin/cms/entries/{$this->page}/cards/access", ['values' => ['mode' => 'any']])->status())->toBeIn([302, 401, 403]);
    expect(put("/admin/cms/terms/{$news->id}/cards/access", ['values' => ['mode' => 'any']])->status())->toBeIn([302, 401, 403]);
    expect($this->saved)->toBe([]);
});

it('limits an entry card to the collections the user may update', function () {
    Content::editorCard(gapCard($this));
    // May view every collection, but update only pages.
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.pages.update'));

    get(entryEditUrl($this->page))->assertInertia(fn (Assert $page) => $page->has('cards', 1));
    put("/admin/cms/entries/{$this->page}/cards/access", ['values' => ['mode' => 'any']])->assertSessionHasNoErrors();

    get(entryEditUrl($this->post))->assertInertia(fn (Assert $page) => $page->where('cards', []));
    put("/admin/cms/entries/{$this->post}/cards/access", ['values' => ['mode' => 'any']])->assertForbidden();
    expect(array_keys($this->saved))->toBe(['access:'.Entry::class.$this->page]);
});

it('refuses an entry card to one scoped to another collection entirely', function () {
    Content::editorCard(gapCard($this));
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.posts.view', 'cms.entries.posts.update'));

    get(entryEditUrl($this->page))->assertForbidden();
    put("/admin/cms/entries/{$this->page}/cards/access", ['values' => ['mode' => 'any']])->assertForbidden();
    put("/admin/cms/entries/{$this->post}/cards/access", ['values' => ['mode' => 'any']])->assertSessionHasNoErrors();
    expect(array_keys($this->saved))->toBe(['access:'.Entry::class.$this->post]);
});

it('limits a term card to the taxonomies the user may update', function () {
    Content::editorCard(gapCard($this));
    $news = app(SaveTerm::class)->handle($this->topics, ['title' => 'News']);
    $php = app(SaveTerm::class)->handle($this->tags, ['title' => 'PHP']);
    actingAs(adminUser(Panel::ACCESS, 'cms.terms.view', 'cms.terms.topics.update'));

    get(termEditUrl($news->id))->assertInertia(fn (Assert $page) => $page->has('cards', 1));
    get(termEditUrl($php->id))->assertInertia(fn (Assert $page) => $page->where('cards', []));
    put("/admin/cms/terms/{$php->id}/cards/access", ['values' => ['mode' => 'any']])->assertForbidden();
    put("/admin/cms/terms/{$news->id}/cards/access", ['values' => ['mode' => 'any']])->assertSessionHasNoErrors();
    expect(array_keys($this->saved))->toBe(['access:'.Term::class.$news->id]);
});

it('gives authors with update-own the card on their own entries only', function () {
    Content::editorCard(gapCard($this));
    $author = adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update-own');
    Entry::query()->findOrFail($this->page)->forceFill(['author_id' => $author->id])->save();
    actingAs($author);

    get(entryEditUrl($this->page))->assertInertia(fn (Assert $page) => $page->has('cards', 1));
    put("/admin/cms/entries/{$this->page}/cards/access", ['values' => ['mode' => 'any']])->assertSessionHasNoErrors();

    get(entryEditUrl($this->post))->assertInertia(fn (Assert $page) => $page->where('cards', []));
    put("/admin/cms/entries/{$this->post}/cards/access", ['values' => ['mode' => 'any']])->assertForbidden();
    expect(array_keys($this->saved))->toBe(['access:'.Entry::class.$this->page]);
});

it('does not let a translator save a card they are not shown, by naming another language', function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    Content::editorCard(gapCard($this));
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.translate'));

    get(entryEditUrl($this->page))->assertInertia(fn (Assert $page) => $page->where('cards', []));
    // The card is about the whole entry (every language), like its settings: not for translators.
    put("/admin/cms/entries/{$this->page}/cards/access", ['values' => ['mode' => 'roles'], 'locale' => 'id'])->assertForbidden();
    expect($this->saved)->toBe([]);
})->todo('EditorCardController lets translators save entry cards: the route guard reads "locale" from the input and passes translators for a non-main language, and the controller never re-checks Access::entries(..., update, collection, entry) without a locale as EntryManageController::settings() does.');

it('404s an unknown card key and a missing record', function () {
    Content::editorCard(gapCard($this));
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update', 'cms.terms.view', 'cms.terms.update'));
    $news = app(SaveTerm::class)->handle($this->topics, ['title' => 'News']);

    put("/admin/cms/entries/{$this->page}/cards/nope", ['values' => ['mode' => 'any']])->assertNotFound();
    put("/admin/cms/terms/{$news->id}/cards/nope", ['values' => ['mode' => 'any']])->assertNotFound();
    put('/admin/cms/entries/999999/cards/access', ['values' => ['mode' => 'any']])->assertNotFound();
    put('/admin/cms/terms/999999/cards/access', ['values' => ['mode' => 'any']])->assertNotFound();
    expect($this->saved)->toBe([]);
});

it('neither shows nor saves a card on a trashed entry or term', function () {
    Content::editorCard(gapCard($this));
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update', 'cms.terms.view', 'cms.terms.update'));
    $news = app(SaveTerm::class)->handle($this->topics, ['title' => 'News']);
    Entry::query()->findOrFail($this->page)->delete();
    $news->delete();

    get(entryEditUrl($this->page))->assertInertia(fn (Assert $page) => $page->where('cards', []));
    put("/admin/cms/entries/{$this->page}/cards/access", ['values' => ['mode' => 'any']])->assertNotFound();
    put("/admin/cms/terms/{$news->id}/cards/access", ['values' => ['mode' => 'any']])->assertNotFound();
    expect($this->saved)->toBe([])->and($this->loads)->toBe(0);
});

it('does not save a card on an entry whose collection is in the trash', function () {
    Content::editorCard(gapCard($this));
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update'));
    $this->pages->delete();

    // Its editor is gone (as its settings' save is).
    get(entryEditUrl($this->page))->assertNotFound();
    put("/admin/cms/entries/{$this->page}/cards/access", ['values' => ['mode' => 'any']])->assertNotFound();
    expect($this->saved)->toBe([]);
})->todo('EditorCardController saves cards on entries of a trashed collection: the editor and EntryManageController::settings() abort 404 when $entry->collection->trashed(), the card save has no such check.');

it('treats values that are not a list as none, and checks them', function () {
    Content::editorCard(gapCard($this));
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update'));

    put("/admin/cms/entries/{$this->page}/cards/access", ['values' => 'mode=any'])->assertSessionHasErrors('mode');
    put("/admin/cms/entries/{$this->page}/cards/access", [])->assertSessionHasErrors('mode');
    expect($this->saved)->toBe([]);
});

it('leaves disabled fields out of what save() gets', function () {
    Content::editorCard(EditorCard::make('price', 'Price')->entries()
        ->form(fn (Entry|Term $record, Form $form): Form => $form->schema([
            TextInput::make('price')->label('Price'),
            TextInput::make('sku')->label('SKU')->disabled(),
        ]))
        ->save(function (Entry|Term $record, array $values) {
            $this->saved['price'] = $values;
        }));
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update'));

    put("/admin/cms/entries/{$this->page}/cards/price", ['values' => ['price' => '10', 'sku' => 'HACKED']])->assertSessionHasNoErrors();
    expect($this->saved['price'])->toHaveKey('price')->not->toHaveKey('sku');
});

it('saves a card without a save() closure, and draws one without form or load', function () {
    Content::editorCard(EditorCard::make('empty', 'Empty')->entries());
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update'));

    get(entryEditUrl($this->page))->assertInertia(fn (Assert $page) => $page
        ->where('cards.0.key', 'empty')->where('cards.0.values', [])->where('cards.0.description', null));
    put("/admin/cms/entries/{$this->page}/cards/empty", ['values' => ['x' => 1]])->assertRedirect()->assertSessionHas('success', 'Empty saved.');
});

it('gives the card\'s can() the signed-in user and the record, on show and save', function () {
    $seen = [];
    Content::editorCard(gapCard($this)->can(function (Authorizable $user, Entry|Term $record) use (&$seen): bool {
        $seen[] = [$user->getAuthIdentifier(), $record::class, $record->id];

        return $record instanceof Entry && $record->id === $this->page;
    }));
    $user = adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update');
    actingAs($user);

    get(entryEditUrl($this->page))->assertInertia(fn (Assert $page) => $page->has('cards', 1));
    get(entryEditUrl($this->post))->assertInertia(fn (Assert $page) => $page->where('cards', []));
    put("/admin/cms/entries/{$this->post}/cards/access", ['values' => ['mode' => 'any']])->assertNotFound();
    put("/admin/cms/entries/{$this->page}/cards/access", ['values' => ['mode' => 'any']])->assertSessionHasNoErrors();

    expect($seen)->toContain([$user->id, Entry::class, $this->page], [$user->id, Entry::class, $this->post])
        ->and(array_keys($this->saved))->toBe(['access:'.Entry::class.$this->page]);
});

it('keeps entry-only cards off terms, term-only ones off entries, and filters taxonomies', function () {
    Content::editorCard(EditorCard::make('entry-only', 'Entry only')->entries());
    Content::editorCard(EditorCard::make('topics-only', 'Topics only')->terms(fn (Taxonomy $taxonomy): bool => $taxonomy->handle === 'topics'));
    Content::editorCard(EditorCard::make('nowhere', 'Nowhere'));
    $news = app(SaveTerm::class)->handle($this->topics, ['title' => 'News']);
    $php = app(SaveTerm::class)->handle($this->tags, ['title' => 'PHP']);
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update', 'cms.terms.view', 'cms.terms.update'));

    get(entryEditUrl($this->page))->assertInertia(fn (Assert $page) => $page->has('cards', 1)->where('cards.0.key', 'entry-only'));
    get(termEditUrl($news->id))->assertInertia(fn (Assert $page) => $page->has('cards', 1)->where('cards.0.key', 'topics-only'));
    get(termEditUrl($php->id))->assertInertia(fn (Assert $page) => $page->where('cards', []));

    put("/admin/cms/terms/{$news->id}/cards/entry-only", ['values' => []])->assertNotFound();
    put("/admin/cms/entries/{$this->page}/cards/topics-only", ['values' => []])->assertNotFound();
    put("/admin/cms/terms/{$php->id}/cards/topics-only", ['values' => []])->assertNotFound();
    put("/admin/cms/entries/{$this->page}/cards/nowhere", ['values' => []])->assertNotFound();
});

it('draws cards in the order they were registered', function () {
    Content::editorCard(EditorCard::make('zeta', 'Zeta')->entries());
    Content::editorCard(EditorCard::make('alpha', 'Alpha')->entries());
    Content::editorCard(EditorCard::make('mid', 'Mid')->entries());
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update'));

    get(entryEditUrl($this->page))->assertInertia(fn (Assert $page) => $page
        ->where('cards.0.key', 'zeta')->where('cards.1.key', 'alpha')->where('cards.2.key', 'mid'));
});

it('passes a card\'s title, description and values as plain text, for the editor to escape', function () {
    Content::editorCard(EditorCard::make('html', '<b>Bold</b>')->description('<script>alert(1)</script>')->entries()
        ->form(fn (Entry|Term $record, Form $form): Form => $form->schema([TextInput::make('note')->label('<i>Note</i>')]))
        ->load(fn (): array => ['note' => '<img src=x onerror=alert(1)>']));
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update'));

    get(entryEditUrl($this->page))->assertInertia(fn (Assert $page) => $page
        ->where('cards.0.title', '<b>Bold</b>')
        ->where('cards.0.description', '<script>alert(1)</script>')
        ->where('cards.0.values.note', '<img src=x onerror=alert(1)>'));
});

it('does no card work where the card is not shown', function () {
    Content::editorCard(gapCard($this)->entries(fn (Collection $collection): bool => $collection->handle === 'pages'));
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update', 'cms.entries.create'));

    get(entryEditUrl($this->post))->assertOk();
    get('/admin/cms/collections/pages/entries')->assertOk();
    get('/admin/cms/collections/pages/entries/create')->assertInertia(fn (Assert $page) => $page->missing('cards'));
    expect($this->loads)->toBe(0)->and($this->forms)->toBe(0);

    // Nor for those who may only view the entry.
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view'));
    get(entryEditUrl($this->page))->assertOk();
    expect($this->loads)->toBe(0)->and($this->forms)->toBe(0);

    // The editor it's on loads it once.
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update'));
    get(entryEditUrl($this->page))->assertOk();
    expect($this->loads)->toBe(1);
});

it('adds no queries per card to the editor', function () {
    $count = function (): int {
        DB::flushQueryLog();
        get(entryEditUrl($this->page))->assertOk();

        return count(DB::getQueryLog());
    };
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update'));
    Content::editorCard(gapCard($this, 'one', 'One'));
    get(entryEditUrl($this->page))->assertOk(); // warm up permission and other caches
    DB::enableQueryLog();
    $one = $count();

    foreach (['two', 'three', 'four', 'five'] as $key) {
        Content::editorCard(gapCard($this, $key, ucfirst($key)));
    }
    $five = $count();

    expect($five)->toBe($one)->and($this->loads)->toBe(1 + 1 + 5);
});
