<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Preferences\Preferences;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\MoveEntry;
use Sunrice\Cms\Actions\Entries\ReorderEntries;
use Sunrice\Cms\Actions\Entries\TrashEntry;
use Sunrice\Cms\Actions\Terms\ReorderTerms;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Admin\EntryList;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Events\ContentChanged;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\Term;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser());
    $this->pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'ordered' => true, 'hierarchical' => true]);
    $this->categories = Taxonomy::query()->create(['handle' => 'categories', 'title' => 'Categories', 'hierarchical' => true]);
});

function orderedPage(string $title, ?int $parent = null): Entry
{
    $entry = app(CreateEntry::class)->handle(test()->pages, ['title' => $title])->entry;
    if ($parent !== null) {
        $entry->forceFill(['parent_id' => $parent])->save();
    }

    return $entry;
}

/** @return list<string> the collection's entries' titles in order */
function pageOrder(): array
{
    return Entry::query()->withTrashed()->with('translations')->where('collection_id', test()->pages->id)->orderBy('sort_order')->orderBy('id')->get()
        ->map(fn (Entry $entry) => $entry->mainTranslation()?->draft_title)->all();
}

it('puts entries in the order dragged, renumbering the collection', function () {
    [$a, $b, $c] = [orderedPage('A'), orderedPage('B'), orderedPage('C')];
    $changes = [];
    Event::listen(ContentChanged::class, function (ContentChanged $e) use (&$changes) {
        $changes[] = $e->what;
    });

    app(ReorderEntries::class)->handle($this->pages, [$c->id, $a->id, $b->id]);

    expect(pageOrder())->toBe(['C', 'A', 'B'])
        ->and(Entry::query()->orderBy('sort_order')->pluck('sort_order')->all())->toBe([1, 2, 3])
        ->and($changes)->toContain('collection pages order')
        ->and(Activity::query()->latest('id')->first()?->only(['action', 'subject_type', 'subject_label']))
        ->toBe(['action' => 'reordered', 'subject_type' => 'collection', 'subject_label' => 'Pages']);
});

it('puts the entries given first, the rest of the level after them as they were', function () {
    [$a, $b, $c, $d] = [orderedPage('A'), orderedPage('B'), orderedPage('C'), orderedPage('D')];
    app(TrashEntry::class)->handle($b);

    app(ReorderEntries::class)->handle($this->pages, [$c->id, $a->id]);

    expect(pageOrder())->toBe(['C', 'A', 'B', 'D']);
});

it('reorders siblings under one parent in a hierarchical collection', function () {
    $parent = orderedPage('Parent');
    [$x, $y] = [orderedPage('X', $parent->id), orderedPage('Y', $parent->id)];
    $top = orderedPage('Top');

    $level = fn (?int $under) => Entry::query()->where('collection_id', $this->pages->id)->where('parent_id', $under)->orderBy('sort_order')->orderBy('id')->pluck('id')->all();

    app(ReorderEntries::class)->handle($this->pages, [$y->id, $x->id], $parent->id);
    expect($level($parent->id))->toBe([$y->id, $x->id])
        ->and(fn () => app(ReorderEntries::class)->handle($this->pages, [$x->id, $top->id], $parent->id))->toThrow(ValidationException::class, 'at this level')
        ->and(fn () => app(ReorderEntries::class)->handle($this->pages, [$top->id, $x->id]))->toThrow(ValidationException::class, 'at this level');

    app(ReorderEntries::class)->handle($this->pages, [$top->id, $parent->id]);
    expect($level(null))->toBe([$top->id, $parent->id])->and($level($parent->id))->toBe([$y->id, $x->id]);
});

it('refuses entries of another collection, repeated ids, and collections in date order', function () {
    $a = orderedPage('A');
    $news = Collection::query()->create(['handle' => 'news', 'title' => 'News']);
    $other = app(CreateEntry::class)->handle($news, ['title' => 'Elsewhere'])->entry;

    expect(fn () => app(ReorderEntries::class)->handle($this->pages, [$a->id, $other->id]))->toThrow(ValidationException::class)
        ->and(fn () => app(ReorderEntries::class)->handle($this->pages, [$a->id, $a->id]))->toThrow(ValidationException::class, 'each entry id once')
        ->and(fn () => app(ReorderEntries::class)->handle($this->pages, ['x']))->toThrow(ValidationException::class)
        ->and(fn () => app(ReorderEntries::class)->handle($news, [$other->id]))->toThrow(ValidationException::class, 'Manual order');
});

it('keeps moving one place up or down', function () {
    [$a, $b] = [orderedPage('A'), orderedPage('B')];
    app(MoveEntry::class)->handle($b, 'up');

    expect(pageOrder())->toBe(['B', 'A']);
});

it('reorders entries from the list behind the update permission', function () {
    [$a, $b] = [orderedPage('A'), orderedPage('B')];
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view'));
    post('/admin/cms/entries/reorder', ['collection' => 'pages', 'ids' => [$b->id, $a->id]])->assertForbidden();

    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update'));
    post('/admin/cms/entries/reorder', ['collection' => 'pages', 'ids' => [$b->id, $a->id]])->assertRedirect()->assertSessionHasNoErrors();
    expect(pageOrder())->toBe(['B', 'A']);
    post('/admin/cms/entries/reorder', ['collection' => 'nope', 'ids' => [$a->id]])->assertNotFound();
    post('/admin/cms/entries/reorder', ['collection' => 'pages', 'ids' => []])->assertSessionHasErrors('ids');

    // Scoped to another collection: not this one.
    Collection::query()->create(['handle' => 'blog', 'title' => 'Blog']);
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.blog.view', 'cms.entries.blog.update'));
    post('/admin/cms/entries/reorder', ['collection' => 'pages', 'ids' => [$a->id, $b->id]])->assertForbidden();
});

it('puts terms of one level in the order dragged', function () {
    $save = app(SaveTerm::class);
    [$a, $b, $c] = [$save->handle($this->categories, ['title' => 'A']), $save->handle($this->categories, ['title' => 'B']), $save->handle($this->categories, ['title' => 'C'])];
    [$a1, $a2] = [$save->handle($this->categories, ['title' => 'A1', 'parent_id' => $a->id]), $save->handle($this->categories, ['title' => 'A2', 'parent_id' => $a->id])];
    $level = fn (?int $parent) => Term::query()->where('parent_id', $parent)->orderBy('sort_order')->orderBy('id')->pluck('id')->all();

    app(ReorderTerms::class)->handle($this->categories, [$c->id, $a->id, $b->id]);
    app(ReorderTerms::class)->handle($this->categories, [$a2->id, $a1->id], $a->id);

    expect($level(null))->toBe([$c->id, $a->id, $b->id])
        ->and($level($a->id))->toBe([$a2->id, $a1->id])
        ->and(Term::query()->whereNull('parent_id')->orderBy('sort_order')->pluck('sort_order')->all())->toBe([0, 1, 2])
        ->and(Activity::query()->latest('id')->first()?->only(['action', 'subject_type']))->toBe(['action' => 'reordered', 'subject_type' => 'taxonomy'])
        ->and(fn () => app(ReorderTerms::class)->handle($this->categories, [$a1->id, $b->id], $a->id))->toThrow(ValidationException::class, 'at this level')
        ->and(fn () => app(ReorderTerms::class)->handle(Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']), [$a->id]))->toThrow(ValidationException::class);
});

it('reorders terms from the list for those who manage the taxonomy', function () {
    $save = app(SaveTerm::class);
    [$a, $b] = [$save->handle($this->categories, ['title' => 'A']), $save->handle($this->categories, ['title' => 'B'])];
    actingAs(adminUser(Panel::ACCESS));
    post('/admin/cms/terms/reorder', ['taxonomy' => 'categories', 'ids' => [$b->id, $a->id]])->assertForbidden();

    actingAs(adminUser(Panel::ACCESS, 'cms.terms.view', 'cms.terms.create', 'cms.terms.update', 'cms.terms.delete'));
    post('/admin/cms/terms/reorder', ['taxonomy' => 'categories', 'ids' => [$b->id, $a->id], 'parent_id' => null])->assertRedirect()->assertSessionHasNoErrors();
    expect(Term::query()->orderBy('sort_order')->pluck('id')->all())->toBe([$b->id, $a->id]);
    post('/admin/cms/terms/reorder', ['taxonomy' => 'nope', 'ids' => [$a->id]])->assertNotFound();

    Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    actingAs(adminUser(Panel::ACCESS, 'cms.terms.tags.view', 'cms.terms.tags.create', 'cms.terms.tags.update', 'cms.terms.tags.delete'));
    post('/admin/cms/terms/reorder', ['taxonomy' => 'categories', 'ids' => [$a->id, $b->id]])->assertForbidden();
});

/** Many entries in a flat ordered collection, fast: two inserts. @return list<int> their ids in order */
function manyOrdered(Collection $collection, int $count): array
{
    $now = now();
    DB::table('sunrice_entries')->insert(array_map(fn (int $i) => ['collection_id' => $collection->id, 'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now], range(1, $count)));
    $ids = DB::table('sunrice_entries')->where('collection_id', $collection->id)->orderBy('sort_order')->pluck('id')->map(fn ($id) => (int) $id)->all();
    foreach (array_chunk($ids, 200) as $chunk) {
        DB::table('sunrice_entry_translations')->insert(array_map(fn (int $id) => ['entry_id' => $id, 'collection_id' => $collection->id, 'locale' => 'en', 'status' => 'draft',
            'draft_title' => 'Entry '.$id, 'draft_slug' => 'entry-'.$id, 'draft_data' => '{}', 'created_at' => $now, 'updated_at' => $now], $chunk));
    }

    return $ids;
}

function entryList(Collection $collection, array $query = []): EntryList
{
    return new EntryList($collection, Request::create('/', 'GET', $query), auth()->user(), app(Preferences::class));
}

/** Signed in as an editor who may reorder; the CMS's pages are bundled (no page files to find). */
function reorderingEditor(): void
{
    config(['inertia.testing.ensure_pages_exist' => false]);
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update'));
}

it('offers the whole order to Reorder, every page of it, as a lazy prop', function () {
    reorderingEditor();
    $news = Collection::query()->create(['handle' => 'news', 'title' => 'News', 'ordered' => true]);
    $ids = manyOrdered($news, 60);
    $other = app(CreateEntry::class)->handle($this->pages, ['title' => 'Elsewhere'])->entry;
    app(TrashEntry::class)->handle(Entry::query()->findOrFail($ids[5]));

    get('/admin/cms/collections/news/entries')->assertInertia(fn (Assert $page) => $page
        ->where('manualOrder', true)->where('entries.lastPage', 3)->missing('order')
        ->reloadOnly('order', fn (Assert $reload) => $reload
            ->where('order.limit', EntryList::ORDER_LIMIT)
            ->has('order.entries', 59)
            ->where('order.entries.0', ['id' => $ids[0], 'title' => 'Entry '.$ids[0], 'status' => 'draft', 'parentId' => null])
            ->where('order.entries', fn ($rows) => collect($rows)->pluck('id')->all() === array_values(array_diff($ids, [$ids[5]]))
                && ! collect($rows)->contains('id', $other->id))));

    // Filtered, searched or sorted: not the collection's order, nothing to drag.
    get('/admin/cms/collections/news/entries?sort=title')->assertInertia(fn (Assert $page) => $page
        ->where('manualOrder', false)->reloadOnly('order', fn (Assert $reload) => $reload->where('order', null)));
    get('/admin/cms/collections/news/entries?search=Entry')->assertInertia(fn (Assert $page) => $page
        ->reloadOnly('order', fn (Assert $reload) => $reload->where('order', null)));
    get('/admin/cms/collections/news/entries?status=trashed')->assertInertia(fn (Assert $page) => $page
        ->reloadOnly('order', fn (Assert $reload) => $reload->where('order', null)));
});

it('loads the whole order in one query, none per entry, and only for those who may reorder', function () {
    $news = Collection::query()->create(['handle' => 'news', 'title' => 'News', 'ordered' => true]);
    manyOrdered($news, 120);
    reorderingEditor();
    $list = entryList($news);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $order = $list->fullOrder();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($order['entries'] ?? [])->toHaveCount(120)->and($queries)->toBe(1);

    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view'));
    get('/admin/cms/collections/news/entries')->assertInertia(fn (Assert $page) => $page
        ->reloadOnly('order', fn (Assert $reload) => $reload->where('order', null)));
});

it('says when there are too many entries to drag, and writes a long order in a few statements', function () {
    $news = Collection::query()->create(['handle' => 'news', 'title' => 'News', 'ordered' => true]);
    $ids = manyOrdered($news, EntryList::ORDER_LIMIT + 1);

    expect(entryList($news)->fullOrder())->toBe(['entries' => null, 'limit' => EntryList::ORDER_LIMIT]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    app(ReorderEntries::class)->handle($news, array_reverse($ids));
    $updates = collect(DB::getQueryLog())->filter(fn (array $query) => str_starts_with(strtolower($query['query']), 'update'))->count();
    DB::disableQueryLog();

    expect($updates)->toBe(4) // 300 entries a statement
        ->and(Entry::query()->where('collection_id', $news->id)->orderBy('sort_order')->value('id'))->toBe(end($ids));
});

it('gives each entry of a hierarchical collection its parent in the whole order', function () {
    $parent = orderedPage('Parent');
    [$x, $y] = [orderedPage('X', $parent->id), orderedPage('Y', $parent->id)];
    $top = orderedPage('Top');

    $rows = entryList($this->pages)->fullOrder()['entries'] ?? [];

    expect(collect($rows)->mapWithKeys(fn ($row) => [$row['title'] => $row['parentId']])->all())
        ->toBe(['Parent' => null, 'X' => $parent->id, 'Y' => $parent->id, 'Top' => null]);
});

it('moves an entry to the top, the bottom or a position, logged', function () {
    $news = Collection::query()->create(['handle' => 'news', 'title' => 'News', 'ordered' => true]);
    $ids = manyOrdered($news, 60);
    $order = fn () => Entry::query()->where('collection_id', $news->id)->orderBy('sort_order')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    $move = app(MoveEntry::class);

    expect($move->handle(Entry::query()->findOrFail($ids[0]), 50))->toBeTrue()
        ->and(array_search($ids[0], $order(), true))->toBe(49)
        ->and($order()[0])->toBe($ids[1])
        ->and(Activity::query()->latest('id')->first()?->only(['action', 'subject_type', 'properties']))
        ->toBe(['action' => 'reordered', 'subject_type' => 'entry', 'properties' => ['note' => 'moved to position 50']]);

    $move->handle(Entry::query()->findOrFail($ids[0]), 'top');
    expect($order()[0])->toBe($ids[0]);
    $move->handle(Entry::query()->findOrFail($ids[0]), 'bottom');
    expect($order()[59])->toBe($ids[0]);
    // Past the last is the last; where it already is, nothing changes or is logged.
    $logged = Activity::query()->count();
    expect($move->handle(Entry::query()->findOrFail($ids[0]), 500))->toBeFalse()
        ->and(Activity::query()->count())->toBe($logged);
    $move->handle(Entry::query()->findOrFail($ids[0]), 1);
    expect($order())->toBe($ids)
        ->and(Entry::query()->where('collection_id', $news->id)->orderBy('sort_order')->pluck('sort_order')->all())->toBe(range(1, 60))
        ->and(fn () => $move->handle(Entry::query()->findOrFail($ids[0]), 0))->toThrow(ValidationException::class, 'position from 1')
        ->and(fn () => $move->handle(Entry::query()->findOrFail($ids[0]), 'sideways'))->toThrow(ValidationException::class);
});

it('counts positions among the entries editors see, trashed ones keeping their places', function () {
    [$a, $b, $c, $d] = [orderedPage('A'), orderedPage('B'), orderedPage('C'), orderedPage('D')];
    app(TrashEntry::class)->handle($b);

    app(MoveEntry::class)->handle($d, 2);
    expect(pageOrder())->toBe(['A', 'B', 'D', 'C']);
    // Up skips the trashed one.
    app(MoveEntry::class)->handle($d, 'up');
    expect(pageOrder())->toBe(['D', 'B', 'A', 'C']);
});

it('moves to a position within the entry\'s level in a hierarchical collection', function () {
    $parent = orderedPage('Parent');
    [$x, $y, $z] = [orderedPage('X', $parent->id), orderedPage('Y', $parent->id), orderedPage('Z', $parent->id)];
    $top = orderedPage('Top');
    $level = fn (?int $under) => Entry::query()->where('collection_id', $this->pages->id)->where('parent_id', $under)->orderBy('sort_order')->orderBy('id')->pluck('id')->all();

    app(MoveEntry::class)->handle($z, 1);
    app(MoveEntry::class)->handle($parent, 99);

    expect($level($parent->id))->toBe([$z->id, $x->id, $y->id])
        ->and($level(null))->toBe([$top->id, $parent->id]);
});

it('moves an entry to a position from the list, validated, behind the update permission', function () {
    [$a, $b, $c] = [orderedPage('A'), orderedPage('B'), orderedPage('C')];
    reorderingEditor();

    post("/admin/cms/entries/{$a->id}/move", ['position' => 3])->assertRedirect()->assertSessionHasNoErrors();
    expect(pageOrder())->toBe(['B', 'C', 'A']);
    post("/admin/cms/entries/{$a->id}/move", ['direction' => 'top'])->assertSessionHasNoErrors();
    expect(pageOrder())->toBe(['A', 'B', 'C']);
    post("/admin/cms/entries/{$a->id}/move", ['position' => 'x'])->assertSessionHasErrors('position');
    post("/admin/cms/entries/{$a->id}/move", ['position' => 0])->assertSessionHasErrors('position');
    expect(pageOrder())->toBe(['A', 'B', 'C']);

    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view'));
    post("/admin/cms/entries/{$a->id}/move", ['position' => 2])->assertForbidden();
    expect(pageOrder())->toBe(['A', 'B', 'C']);
});
