<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\MoveEntry;
use Sunrice\Cms\Actions\Entries\ReorderEntries;
use Sunrice\Cms\Actions\Entries\TrashEntry;
use Sunrice\Cms\Actions\Terms\ReorderTerms;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Events\ContentChanged;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\Term;

use function Pest\Laravel\actingAs;
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

    actingAs(adminUser(Panel::ACCESS, 'cms.terms.manage'));
    post('/admin/cms/terms/reorder', ['taxonomy' => 'categories', 'ids' => [$b->id, $a->id], 'parent_id' => null])->assertRedirect()->assertSessionHasNoErrors();
    expect(Term::query()->orderBy('sort_order')->pluck('id')->all())->toBe([$b->id, $a->id]);
    post('/admin/cms/terms/reorder', ['taxonomy' => 'nope', 'ids' => [$a->id]])->assertNotFound();

    Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    actingAs(adminUser(Panel::ACCESS, 'cms.terms.tags.manage'));
    post('/admin/cms/terms/reorder', ['taxonomy' => 'categories', 'ids' => [$a->id, $b->id]])->assertForbidden();
});
