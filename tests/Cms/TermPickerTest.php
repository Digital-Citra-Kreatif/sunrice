<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Menus\SaveMenu;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Fields\Types\TermsField;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Menu;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\Term;
use Sunrice\Cms\Models\TermTranslation;

use function Pest\Laravel\actingAs;

/*
 * The term picker (terms fields, the editor's Taxonomies card) reads one
 * page of a taxonomy's terms at a time: a big taxonomy costs the same as
 * a small one, whatever page is asked for.
 */

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.terms.view'));
});

/**
 * Terms "Term 1"… "Term $n" (sort_order the reverse of creation, so the
 * order isn't the ids'), each third one under the term before it.
 *
 * @return list<int> their ids, in creation order
 */
function manyTerms(Taxonomy $taxonomy, int $n, bool $nested): array
{
    $now = now();
    $ids = [];
    for ($i = 1; $i <= $n; $i++) {
        $parent = $nested && $i % 3 === 0 ? $ids[$i - 2] : null;
        $id = (int) DB::table('sunrice_terms')->insertGetId(['taxonomy_id' => $taxonomy->id, 'parent_id' => $parent, 'sort_order' => $n - $i, 'created_at' => $now, 'updated_at' => $now]);
        $ids[] = $id;
        DB::table('sunrice_term_translations')->insert(['term_id' => $id, 'taxonomy_id' => $taxonomy->id, 'locale' => 'en', 'title' => "Term {$i}", 'slug' => "term-{$i}", 'created_at' => $now, 'updated_at' => $now]);
    }

    return $ids;
}

/** Counts the terms and their translations read as models while running $run. */
function hydrated(Closure $run): array
{
    $counts = ['terms' => 0, 'translations' => 0];
    Term::retrieved(function () use (&$counts) {
        $counts['terms']++;
    });
    TermTranslation::retrieved(function () use (&$counts) {
        $counts['translations']++;
    });
    $result = $run();
    Term::flushEventListeners();
    TermTranslation::flushEventListeners();

    return [$result, $counts];
}

it('pages a flat taxonomy in the database, reading only the page\'s terms and titles', function () {
    $taxonomy = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    $ids = manyTerms($taxonomy, 250, nested: false);

    [$first, $counts] = hydrated(fn () => $this->getJson('/admin/cms/terms/picker?taxonomy=tags')->assertOk()->json());
    expect($counts)->toBe(['terms' => 101, 'translations' => 101])
        ->and($first['more'])->toBeTrue()
        // In the editors' order: the last made first.
        ->and(array_column($first['items'], 'id'))->toBe(array_slice(array_reverse($ids), 0, 100))
        ->and($first['items'][0])->toBe(['id' => $ids[249], 'label' => 'Term 250', 'depth' => 0]);

    [$last, $counts] = hydrated(fn () => $this->getJson('/admin/cms/terms/picker?taxonomy=tags&offset=200')->assertOk()->json());
    expect($counts['translations'])->toBe(50)
        ->and($last['more'])->toBeFalse()
        ->and(array_column($last['items'], 'id'))->toBe(array_slice(array_reverse($ids), 200));
});

it('works out a hierarchy\'s order from ids and parents, and reads only the page\'s titles', function () {
    $taxonomy = Taxonomy::query()->create(['handle' => 'places', 'title' => 'Places', 'hierarchical' => true]);
    manyTerms($taxonomy, 300, nested: true);
    // What the tree should be: each top-level term (in sort order) followed by the terms under it, depth first.
    $all = Term::query()->where('taxonomy_id', $taxonomy->id)->orderBy('sort_order')->orderBy('id')->get(['id', 'parent_id']);
    $walk = function (?int $parent, int $depth) use (&$walk, $all): array {
        $out = [];
        foreach ($all->where('parent_id', $parent) as $term) {
            $out[] = [$term->id, $depth];
            array_push($out, ...$walk($term->id, $depth + 1));
        }

        return $out;
    };
    $expected = $walk(null, 0);

    [$page, $counts] = hydrated(fn () => $this->getJson('/admin/cms/terms/picker?taxonomy=places&offset=100')->assertOk()->json());
    expect($counts)->toBe(['terms' => 100, 'translations' => 100])
        ->and($page['more'])->toBeTrue()
        ->and(array_map(fn (array $item): array => [$item['id'], $item['depth']], $page['items']))->toBe(array_slice($expected, 100, 100))
        ->and(collect($page['items'])->max('depth'))->toBe(1);
    $tail = $this->getJson('/admin/cms/terms/picker?taxonomy=places&offset=200')->json();
    expect($tail['more'])->toBeFalse()->and(count($tail['items']))->toBe(100);
});

it('pages a search too, flat, with the titles of what matches only', function () {
    $taxonomy = Taxonomy::query()->create(['handle' => 'places', 'title' => 'Places', 'hierarchical' => true]);
    manyTerms($taxonomy, 300, nested: true);

    // "Term 1" holds Term 1, 10–19, 100–199: 111 terms.
    [$first, $counts] = hydrated(fn () => $this->getJson('/admin/cms/terms/picker?taxonomy=places&q=Term+1')->assertOk()->json());
    expect($counts['translations'])->toBe(101)
        ->and($first['more'])->toBeTrue()
        ->and(collect($first['items'])->every(fn (array $item): bool => $item['depth'] === 0 && str_starts_with($item['label'], 'Term 1')))->toBeTrue();
    $rest = $this->getJson('/admin/cms/terms/picker?taxonomy=places&q=Term+1&offset=100')->json();
    expect($rest['more'])->toBeFalse()->and(count($rest['items']))->toBe(11)
        ->and(array_intersect(array_column($rest['items'], 'id'), array_column($first['items'], 'id')))->toBe([]);
});

it('orders a tree in one pass, leaving out terms whose parent isn\'t there', function () {
    $rows = [(object) ['id' => 5, 'parent_id' => null], (object) ['id' => 3, 'parent_id' => 5], (object) ['id' => 9, 'parent_id' => null],
        (object) ['id' => 4, 'parent_id' => 3], (object) ['id' => 7, 'parent_id' => 5], (object) ['id' => 8, 'parent_id' => 99]];

    expect(TermsField::order($rows))->toBe([[5, 0], [3, 1], [4, 2], [7, 1], [9, 0]]);
});

it('leaves out a term and the terms under it, when asked to (a term\'s own Under)', function () {
    $taxonomy = Taxonomy::query()->create(['handle' => 'places', 'title' => 'Places', 'hierarchical' => true]);
    // Term 3 is under Term 2, Term 6 under Term 5…: Term 2 and Term 3 go, Term 5 stays.
    $ids = manyTerms($taxonomy, 9, nested: true);

    $tree = $this->getJson("/admin/cms/terms/picker?taxonomy=places&except={$ids[1]}")->assertOk()->json();
    $search = $this->getJson("/admin/cms/terms/picker?taxonomy=places&q=Term&except={$ids[1]}")->assertOk()->json();

    $left = array_values(array_diff($ids, [$ids[1], $ids[2]]));
    expect(array_column($tree['items'], 'id'))->toEqualCanonicalizing($left)
        ->and(array_column($search['items'], 'id'))->toEqualCanonicalizing($left);
});

it('lists a small taxonomy\'s terms in the entries list\'s filter, and has a big one\'s searched', function () {
    $collection = Collection::query()->create(['handle' => 'posts', 'title' => 'Posts']);
    $small = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    $big = Taxonomy::query()->create(['handle' => 'places', 'title' => 'Places', 'hierarchical' => true]);
    $collection->taxonomies()->attach([$small->id, $big->id]);
    manyTerms($small, TermsField::LISTED, nested: false);
    manyTerms($big, TermsField::LISTED + 1, nested: true);

    [, $counts] = hydrated(fn () => $this->get('/admin/cms/collections/posts/entries')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('filters.taxonomies.0.handle', 'places')->where('filters.taxonomies.0.search', true)->where('filters.taxonomies.0.terms', [])
        ->where('filters.taxonomies.1.handle', 'tags')->where('filters.taxonomies.1.search', false)
        ->where('filters.taxonomies.1.terms', fn ($terms): bool => count($terms) === TermsField::LISTED)));
    // Read as plain rows, not models.
    expect($counts)->toBe(['terms' => 0, 'translations' => 0]);
    // And whoever lists the entries may search it.
    $this->getJson('/admin/cms/terms/picker?taxonomy=places')->assertOk();
});

it('has a big taxonomy\'s term moved by searching for its new parent', function () {
    $small = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags', 'hierarchical' => true]);
    $big = Taxonomy::query()->create(['handle' => 'places', 'title' => 'Places', 'hierarchical' => true]);
    $few = manyTerms($small, 3, nested: true);
    $many = manyTerms($big, TermsField::LISTED + 1, nested: true);
    actingAs(adminUser(Panel::ACCESS, 'cms.terms.view', 'cms.terms.update'));

    $this->get("/admin/cms/taxonomies/tags/terms/{$few[0]}/edit")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('terms', fn ($terms): bool => array_column($terms->all(), 'id') === [$few[1], $few[2]]));
    [, $counts] = hydrated(fn () => $this->get("/admin/cms/taxonomies/places/terms/{$many[0]}/edit")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('terms', null)));
    // The term itself, and its translations: not the taxonomy's.
    expect($counts['terms'])->toBeLessThan(5);
    // Who edits terms (not entries) may search for the new parent.
    $this->getJson("/admin/cms/terms/picker?taxonomy=places&except={$many[0]}")->assertOk();
});

it('offers a menu its terms when few, and has them searched when many, by those who edit menus', function () {
    $collection = Collection::query()->create(['handle' => 'posts', 'title' => 'Posts']);
    $tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags', 'route' => '/tags/{slug}']);
    $places = Taxonomy::query()->create(['handle' => 'places', 'title' => 'Places', 'hierarchical' => true]);
    $collection->taxonomies()->attach($places->id, ['term_pages' => true]);
    // No pages: not offered.
    $hidden = Taxonomy::query()->create(['handle' => 'hidden', 'title' => 'Hidden']);
    manyTerms($hidden, 3, nested: false);
    $menu = Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    $tagIds = manyTerms($tags, 2, nested: false);
    actingAs(adminUser(Panel::ACCESS, 'cms.menus.view', 'cms.menus.update'));

    $this->get('/admin/cms/menus/main/links')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('termTaxonomies', [
            ['id' => $places->id, 'handle' => 'places', 'title' => 'Places', 'pages' => [['collection' => $collection->id, 'title' => 'Posts']]],
            ['id' => $tags->id, 'handle' => 'tags', 'title' => 'Tags', 'pages' => [['collection' => null, 'title' => 'Shared page (all collections)']]],
        ])
        ->where('terms', fn ($terms): bool => array_column($terms->all(), 'id') === [$tagIds[1], $tagIds[0]]));

    // Past the list's size (they were cut at 500 before, silently): searched in their taxonomy.
    manyTerms($places, TermsField::LISTED, nested: true);
    $this->get('/admin/cms/menus/main/links')->assertInertia(fn (AssertableInertia $page) => $page->where('terms', null)->has('termTaxonomies', 2));
    $this->getJson('/admin/cms/terms/picker?taxonomy=places')->assertOk();
    $this->getJson('/admin/cms/terms/picker?taxonomy=tags&ids[]='.$tagIds[0])->assertOk()->assertJsonPath('items.0.label', 'Term 1');
    $this->getJson('/admin/cms/terms/picker?taxonomy=hidden')->assertForbidden();

    // A term link says which taxonomy to search in for another.
    app(SaveMenu::class)->handle($menu, [['term_id' => $tagIds[0]]]);
    $this->get('/admin/cms/menus/main/links')->assertInertia(fn (AssertableInertia $page) => $page->where('items.0.target.taxonomy', $tags->id));
});
