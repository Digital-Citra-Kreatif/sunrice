<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Terms\DeleteTerm;
use Sunrice\Cms\Actions\Terms\MoveTerm;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Admin\TemplateHelp;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Fields\Field;
use Sunrice\Cms\Fields\HydrationContext;
use Sunrice\Cms\Fields\Schema;
use Sunrice\Cms\Fields\Types\TermsField;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\Term;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    actingAs(adminUser());
    $this->categories = Taxonomy::query()->create(['handle' => 'categories', 'title' => 'Categories', 'hierarchical' => true]);
    $this->tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
});

function term(Taxonomy $taxonomy, string $title, array $input = []): Term
{
    return app(SaveTerm::class)->handle($taxonomy, ['title' => $title] + $input);
}

it('refuses changing a term over someone else\'s newer save', function () {
    $news = term($this->tags, 'News');
    app(SaveTerm::class)->handle($this->tags, ['title' => 'Daily news', 'version' => 1], $news);

    expect($news->translation()?->version)->toBe(2)
        ->and(fn () => app(SaveTerm::class)->handle($this->tags, ['title' => 'Stale', 'version' => 1], $news))->toThrow(ValidationException::class, 'Someone else saved')
        ->and($news->fresh()?->title())->toBe('Daily news');
});

it('creates terms with slugs unique in their taxonomy and language', function () {
    $news = term($this->categories, 'News');

    expect($news->translation()?->only(['locale', 'title', 'slug']))->toBe(['locale' => 'en', 'title' => 'News', 'slug' => 'news'])
        ->and(term($this->categories, 'News')->translation()?->slug)->toBe('news-2')
        ->and(term($this->tags, 'News')->translation()?->slug)->toBe('news')
        ->and(fn () => term($this->categories, 'Other', ['slug' => 'news']))->toThrow(ValidationException::class, 'already uses "news"');
});

it('nests terms only in hierarchical taxonomies, never under themselves', function () {
    $news = term($this->categories, 'News');
    $local = term($this->categories, 'Local', ['parent_id' => $news->id]);
    $city = term($this->categories, 'City', ['parent_id' => $local->id]);

    expect($city->parent?->parent?->is($news))->toBeTrue()
        ->and(fn () => app(SaveTerm::class)->handle($this->categories, ['title' => 'News', 'parent_id' => $city->id], $news))->toThrow(ValidationException::class, 'under itself')
        ->and(fn () => term($this->tags, 'Sub', ['parent_id' => $news->id]))->toThrow(ValidationException::class, 'no parents')
        ->and(fn () => term($this->categories, 'Sub', ['parent_id' => term($this->tags, 'php')->id]))->toThrow(ValidationException::class, 'of this taxonomy');
});

it('translates a term', function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    $news = term($this->categories, 'News');
    app(SaveTerm::class)->handle($this->categories, ['title' => 'Berita', 'locale' => 'id'], $news);

    $news->load('translations');
    expect($news->title('id'))->toBe('Berita')
        ->and($news->title('en'))->toBe('News')
        ->and($news->translation('id')?->slug)->toBe('berita');
});

it('moves children up when a term is deleted', function () {
    $news = term($this->categories, 'News');
    $local = term($this->categories, 'Local', ['parent_id' => $news->id]);
    app(DeleteTerm::class)->handle($news);

    expect($local->fresh()?->parent_id)->toBeNull()
        ->and(Term::query()->count())->toBe(1);
});

it('reorders terms among their siblings', function () {
    $a = term($this->tags, 'A');
    $b = term($this->tags, 'B');
    $c = term($this->tags, 'C');
    app(MoveTerm::class)->handle($c, -1);
    app(MoveTerm::class)->handle($a, -1); // already first: nothing

    expect(Term::query()->orderBy('sort_order')->pluck('id')->all())->toBe([$a->id, $c->id, $b->id]);
});

it('gives entries terms through a terms field', function () {
    $php = term($this->tags, 'PHP');
    $laravel = term($this->tags, 'Laravel');
    $news = term($this->categories, 'News');
    $schema = Schema::make([
        Field::make('tags', 'terms', ['taxonomy' => 'tags', 'multiple' => true, 'max' => 2]),
        Field::make('category', 'terms', ['taxonomy' => 'categories']),
    ]);
    $errors = fn (array $data) => Validator::make(['data' => $data], $schema->rules($data))->errors()->keys();

    expect($errors(['tags' => [$php->id, $news->id]]))->toBe(['data.tags.1'])
        ->and($errors(['tags' => [$php->id, $laravel->id, $php->id]]))->toContain('data.tags')
        ->and($errors(['category' => $php->id]))->toBe(['data.category'])
        ->and($errors(['tags' => [$php->id, $laravel->id], 'category' => $news->id]))->toBe([]);

    $data = $schema->normalize(['tags' => [(string) $laravel->id, $php->id], 'category' => (string) $news->id]);
    DB::enableQueryLog();
    $values = $schema->hydrate($data, new HydrationContext('en'));
    expect($values['tags']->map->title()->all())->toBe(['Laravel', 'PHP'])
        ->and($values['category']->title())->toBe('News')
        ->and(DB::getQueryLog())->toHaveCount(6); // three per terms field: the terms, their translations and taxonomy (for url())
    // The editor gets the taxonomy only; the terms come from cms/term-picker.
    expect($schema->toAdmin()[0])->toMatchArray(['taxonomy' => 'tags'])->not->toHaveKey('terms')
        ->and(TermsField::options($this->tags)['items'])->toBe([['id' => $php->id, 'label' => 'PHP', 'depth' => 0], ['id' => $laravel->id, 'label' => 'Laravel', 'depth' => 0]])
        ->and(TermsField::labels([$laravel->id, $news->id]))->toBe([$laravel->id => 'Laravel', $news->id => 'News']);
});

it('keeps published entries\' own terms in a table, for queries, not their terms fields\'', function () {
    $php = term($this->tags, 'PHP');
    $laravel = term($this->tags, 'Laravel');
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [['handle' => 'tags', 'type' => 'terms', 'config' => ['taxonomy' => 'tags', 'multiple' => true]]]]);
    $posts = Collection::query()->create(['handle' => 'posts', 'title' => 'Posts', 'blueprint' => 'post']);
    $this->tags->collections()->attach($posts);
    $post = app(CreateEntry::class)->handle($posts, ['title' => 'Hello', 'data' => ['tags' => [$laravel->id]], 'terms' => ['tags' => [$php->id]]]);

    expect($post->entry->terms()->count())->toBe(0); // drafts don't count

    app(PublishEntry::class)->handle($post);
    // The entry's own terms, not its terms field's.
    expect($post->entry->terms()->pluck('sunrice_terms.id')->all())->toBe([$php->id])
        ->and($php->entries()->count())->toBe(1)
        ->and($laravel->entries()->count())->toBe(0);

    app(SaveDraft::class)->handle($post, ['terms' => ['tags' => [$laravel->id]]]);
    app(PublishEntry::class)->handle($post->fresh());
    expect($post->entry->terms()->pluck('sunrice_terms.id')->all())->toBe([$laravel->id]);
});

it('edits taxonomies and their terms in the admin', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages']);
    actingAs(adminUser(Panel::ACCESS, 'cms.taxonomies.view', 'cms.taxonomies.create', 'cms.taxonomies.update'));

    post('/admin/cms/taxonomies', ['title' => 'Topics', 'handle' => 'topics', 'collections' => [(string) $pages->id]])->assertSessionHasNoErrors();
    expect(Taxonomy::query()->where('handle', 'topics')->firstOrFail()->collections->pluck('handle')->all())->toBe(['pages']);
    get('/admin/cms/terms')->assertForbidden();

    actingAs(adminUser(Panel::ACCESS, 'cms.terms.view', 'cms.terms.create', 'cms.terms.update', 'cms.terms.delete'));
    get('/admin/cms/terms')->assertRedirect('/admin/cms/taxonomies/categories/terms');
    post('/admin/cms/taxonomies/categories/terms', ['title' => 'News'])->assertSessionHasNoErrors();
    $news = Term::query()->firstOrFail();
    post('/admin/cms/taxonomies/categories/terms', ['title' => 'Local', 'parent_id' => $news->id])->assertSessionHasNoErrors();
    put("/admin/cms/terms/{$news->id}", ['title' => 'Headlines'])->assertSessionHasNoErrors();

    get('/admin/cms/taxonomies/categories/terms')->assertInertia(fn (Assert $page) => $page
        ->component('Cms/Terms/Index', shouldExist: false)
        ->where('terms.0.title', 'Headlines')
        ->where('terms.1.title', 'Local')
        ->where('terms.1.depth', 1)
        ->where('terms.0.entries', 0)
        ->has('terms.0.createdAt')
        ->where('taxonomy.collections', [])
        // Settings only for those who may change the taxonomy, the blueprint for those who may edit blueprints.
        ->where('taxonomy.settings', null)
        ->where('taxonomy.blueprint', null));

    // To the trash with the term under it, back, and permanently deleted (its child moves up).
    delete("/admin/cms/terms/{$news->id}")->assertSessionHasNoErrors();
    expect(Term::query()->count())->toBe(0)->and(Term::onlyTrashed()->count())->toBe(2);
    post("/admin/cms/terms/{$news->id}/restore")->assertSessionHasNoErrors();
    expect(Term::query()->count())->toBe(2);
    delete("/admin/cms/terms/{$news->id}/force")->assertNotFound();
    delete("/admin/cms/terms/{$news->id}");
    post('/admin/cms/terms/'.Term::onlyTrashed()->where('id', '!=', $news->id)->value('id').'/restore');
    delete("/admin/cms/terms/{$news->id}/force")->assertSessionHasNoErrors();
    expect(Term::query()->count())->toBe(1)->and(Term::query()->value('parent_id'))->toBeNull();
});

it('lists each term with its entry count, and the taxonomy\'s collections and settings', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    $blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog']);
    $this->tags->collections()->attach($blog);
    $laravel = term($this->tags, 'Laravel');
    term($this->tags, 'PHP');
    $post = app(CreateEntry::class)->handle($blog, ['title' => 'Hello']);
    $post->entry->terms()->attach($laravel);

    actingAs(adminUser(Panel::ACCESS, 'cms.terms.view', 'cms.terms.create', 'cms.terms.update', 'cms.terms.delete', 'cms.taxonomies.update'));
    get('/admin/cms/taxonomies/tags/terms')->assertInertia(fn (Assert $page) => $page
        ->where('terms.0.title', 'Laravel')
        ->where('terms.0.entries', 1)
        ->where('terms.1.entries', 0)
        ->where('taxonomy.collections', [['title' => 'Blog', 'url' => url('/admin/cms/collections/blog/entries')]])
        ->where('taxonomy.settings', url("/admin/cms/taxonomies/{$this->tags->handle}/edit")));
});

it('explains a template setting with the files tried in order when it is empty', function () {
    expect(TemplateHelp::files(['cms.taxonomies.{handle}.show', 'cms.taxonomies.show'], 'cms.topic'))->toBe(implode("\n", [
        'The first that exists, under resources/views:',
        '1. `cms/taxonomies/{handle}/show.blade.php`',
        '2. `cms/taxonomies/show.blade.php`',
        '3. the built-in template',
        'A view name is a file there: `cms.topic` is `cms/topic.blade.php`.',
    ]));
    // The editor's "Which file?": the views tried, in order, and which exist.
    expect(TemplateHelp::lookup([null, 'sunrice-cms::site.nope', '', 'sunrice-cms::components.seo']))->toBe([
        ['view' => 'sunrice-cms::site.nope', 'exists' => false],
        ['view' => 'sunrice-cms::components.seo', 'exists' => true],
    ]);
});

it('creates a term in the full term editor, with its fields, SEO and place, then opens it there', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser(Panel::ACCESS, 'cms.terms.view', 'cms.terms.create', 'cms.terms.update', 'cms.terms.delete'));
    $news = term($this->categories, 'News');

    get("/admin/cms/taxonomies/categories/terms/create?parent={$news->id}")->assertInertia(fn (Assert $page) => $page
        ->component('Cms/Terms/Edit', shouldExist: false)
        ->where('term', null)
        ->where('taxonomy.hierarchical', true)
        ->where('parentId', $news->id)
        ->where('terms.0.title', 'News')
        // The taxonomy's fields (here the built-in description), as for an existing term.
        ->where('fields.0.handle', 'description')
        ->where('values.description', null));

    // No pages or fields of its own: still to its editor, created with everything given at once.
    post('/admin/cms/taxonomies/categories/terms', ['title' => 'Local', 'parent_id' => $news->id, 'data' => ['description' => 'Around town.'], 'seo' => ['title' => 'Local news'], 'template' => 'cms.local'])
        ->assertRedirectContains('/admin/cms/taxonomies/categories/terms/');
    $local = Term::query()->where('parent_id', $news->id)->with('translations')->firstOrFail();
    expect($local->translations->first()->data)->toMatchArray(['description' => 'Around town.'])
        ->and($local->translations->first()->data['seo']['title'] ?? null)->toBe('Local news')
        ->and($local->template)->toBe('cms.local')
        ->and(session('success'))->toBe('"Local" added.');

    // Content that doesn't pass: nothing is created.
    post('/admin/cms/taxonomies/categories/terms', ['title' => 'Bad', 'template' => 'not a view!'])->assertSessionHasErrors('template');
    expect(Term::query()->count())->toBe(2);

    actingAs(adminUser(Panel::ACCESS));
    get('/admin/cms/taxonomies/categories/terms/create')->assertForbidden();
});

it('links the terms list to its taxonomy\'s blueprint for those who may edit blueprints', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Blueprint::query()->create(['handle' => 'topic', 'title' => 'Topic', 'fields' => []]);
    Taxonomy::query()->where('handle', 'categories')->update(['blueprint' => 'topic']);
    actingAs(adminUser(Panel::ACCESS, 'cms.terms.view', 'cms.blueprints.view', 'cms.blueprints.update'));

    get('/admin/cms/taxonomies/categories/terms')->assertInertia(fn (Assert $page) => $page->where('taxonomy.blueprint', route('sunrice.admin.cms.blueprints.edit', 'topic')));
});

it('says how many terms are in the trash, outside it', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser(Panel::ACCESS, 'cms.terms.view', 'cms.terms.create', 'cms.terms.update', 'cms.terms.delete'));
    post('/admin/cms/taxonomies/categories/terms', ['title' => 'News'])->assertSessionHasNoErrors();
    Term::query()->firstOrFail()->delete();

    get('/admin/cms/taxonomies/categories/terms')->assertInertia(fn (Assert $page) => $page->where('trashedCount', 1)->where('terms', []));
    get('/admin/cms/taxonomies/categories/terms?status=trashed')->assertInertia(fn (Assert $page) => $page->where('trashedCount', 0));
});

it('moves a term under another in its editor, never under itself or a term under it', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser(Panel::ACCESS, 'cms.terms.view', 'cms.terms.update'));
    $news = term($this->categories, 'News');
    $local = term($this->categories, 'Local', ['parent_id' => $news->id]);
    $sport = term($this->categories, 'Sport');

    get("/admin/cms/taxonomies/categories/terms/{$news->id}/edit")->assertInertia(fn (Assert $page) => $page
        ->where('parentId', null)
        ->where('terms', [['id' => $sport->id, 'title' => 'Sport', 'depth' => 0]])
        ->where('term.translatedIn', ['en']));

    put("/admin/cms/terms/{$sport->id}/content", ['title' => 'Sport', 'parent_id' => $news->id, 'locale' => 'en'])->assertSessionHasNoErrors();
    expect($sport->fresh()->parent_id)->toBe($news->id);
    put("/admin/cms/terms/{$news->id}/content", ['title' => 'News', 'parent_id' => $local->id, 'locale' => 'en'])->assertSessionHasErrors('parent_id');
    put("/admin/cms/terms/{$sport->id}/content", ['title' => 'Sport', 'parent_id' => null, 'locale' => 'en'])->assertSessionHasNoErrors();
    expect($sport->fresh()->parent_id)->toBeNull();
});

it('switches term pages on and off as collections do their pages', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser(Panel::ACCESS, 'cms.taxonomies.view', 'cms.taxonomies.create', 'cms.taxonomies.update'));

    // On without a URL: the default, with the handle in it.
    post('/admin/cms/taxonomies', ['title' => 'Topics', 'handle' => 'topics', 'pages' => true, 'route' => ''])->assertSessionHasNoErrors();
    $topics = Taxonomy::query()->where('handle', 'topics')->firstOrFail();
    expect($topics->route)->toBe('/topics/{slug}');
    $field = fn (Assert $page, string $name): array => collect($page->toArray()['props']['sections'])->flatMap(fn ($section) => $section['fields'])->firstWhere('name', $name);
    get('/admin/cms/taxonomies/topics/edit')->assertInertia(fn (Assert $page) => expect($page->toArray()['props']['values']['pages'])->toBeTrue()
        ->and($field($page, 'route')['showWhen'])->toBe(['field' => 'pages', 'value' => null])
        ->and($field($page, 'route')['placeholder'])->toBe('/{handle}/{slug}')
        ->and($field($page, 'template')['showWhen'])->toBeNull());

    put('/admin/cms/taxonomies/topics', ['title' => 'Topics', 'pages' => true, 'route' => '/themes'])->assertSessionHasErrors('route');
    put('/admin/cms/taxonomies/topics', ['title' => 'Topics', 'pages' => true, 'route' => '/themes/{slug}'])->assertSessionHasNoErrors();
    expect($topics->fresh()->route)->toBe('/themes/{slug}');
    put('/admin/cms/taxonomies/topics', ['title' => 'Topics', 'pages' => false, 'route' => '/themes/{slug}'])->assertSessionHasNoErrors();
    expect($topics->fresh()->route)->toBeNull();
});

it('attaches collections from the taxonomy\'s form, one term only or not, and leaves blueprints alone', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [['handle' => 'topics', 'type' => 'text']]]);
    $blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post']);
    // No blueprint at all: its entries get the taxonomy's terms all the same.
    $notes = Collection::query()->create(['handle' => 'notes', 'title' => 'Notes']);
    actingAs(adminUser(Panel::ACCESS, 'cms.taxonomies.view', 'cms.taxonomies.create', 'cms.taxonomies.update'));

    post('/admin/cms/taxonomies', ['title' => 'Topics', 'handle' => 'topics', 'collections' => [(string) $blog->id, (string) $notes->id]])->assertSessionHasNoErrors();
    $topics = Taxonomy::query()->where('handle', 'topics')->firstOrFail();
    expect($topics->collections()->pluck('handle')->sort()->values()->all())->toBe(['blog', 'notes'])
        ->and(Blueprint::query()->where('handle', 'post')->firstOrFail()->fields)->toBe([['handle' => 'topics', 'type' => 'text']])
        ->and(Blueprint::query()->count())->toBe(1);

    // One term only in Notes; the side card lists the collections it groups.
    put('/admin/cms/taxonomies/topics', ['title' => 'Topics', 'collections' => [(string) $blog->id, (string) $notes->id], "single_{$notes->id}" => true])->assertSessionHasNoErrors();
    expect(DB::table('sunrice_collection_taxonomy')->where('collection_id', $notes->id)->value('single'))->toBeTruthy()
        ->and(DB::table('sunrice_collection_taxonomy')->where('collection_id', $blog->id)->value('single'))->toBeFalsy();
    get('/admin/cms/taxonomies/topics/edit')->assertInertia(fn (Assert $page) => $page
        ->where("values.single_{$notes->id}", true)->where("values.single_{$blog->id}", false)
        ->missing('values.remove_term_fields')
        ->where('aside.0.title', 'Collections')
        ->where('aside.0.blocks', fn ($blocks) => collect($blocks)->firstWhere('type', 'list')['items'] === [
            ['label' => 'Blog', 'meta' => 'Several terms', 'at' => null], ['label' => 'Notes', 'meta' => 'One term only', 'at' => null],
        ]));

    // Detached: the collection's entries keep their live terms; blueprints never change.
    put('/admin/cms/taxonomies/topics', ['title' => 'Topics', 'collections' => [(string) $notes->id]])->assertSessionHasNoErrors();
    expect($topics->collections()->pluck('handle')->all())->toBe(['notes'])
        ->and(Blueprint::query()->where('handle', 'post')->firstOrFail()->fields)->toHaveCount(1);
});

it('attaches taxonomies from the collection\'s form, one term only or not, checking their pages\' URLs', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    $blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'listing_route' => '/blog']);
    actingAs(adminUser(Panel::ACCESS, 'cms.collections.view', 'cms.collections.update'));
    $form = fn (array $values) => put('/admin/cms/collections/blog', ['title' => 'Blog', 'handle' => 'blog', ...$values]);

    $form(['taxonomies' => [(string) $this->tags->id, (string) $this->categories->id], "single_taxonomy_{$this->categories->id}" => true])->assertSessionHasNoErrors();
    $pivots = DB::table('sunrice_collection_taxonomy')->where('collection_id', $blog->id)->get()->keyBy('taxonomy_id');
    expect($pivots->keys()->map(fn ($id) => (int) $id)->sort()->values()->all())->toBe(collect([$this->tags->id, $this->categories->id])->sort()->values()->all())
        ->and((bool) $pivots[$this->categories->id]->single)->toBeTrue()
        ->and((bool) $pivots[$this->tags->id]->single)->toBeFalse()
        // New attachments get their term and index pages there, as from the taxonomy.
        ->and((bool) $pivots[$this->tags->id]->term_pages)->toBeTrue();
    get('/admin/cms/collections/blog/edit')->assertInertia(fn (Assert $page) => $page
        ->where('values.taxonomies', fn ($ids) => collect($ids)->sort()->values()->all() === collect([(string) $this->tags->id, (string) $this->categories->id])->sort()->values()->all())
        ->where("values.single_taxonomy_{$this->categories->id}", true));

    // Unticked: detached; ticked again with one term only switched off.
    $form(['taxonomies' => [(string) $this->categories->id], "single_taxonomy_{$this->categories->id}" => false])->assertSessionHasNoErrors();
    expect(DB::table('sunrice_collection_taxonomy')->where('collection_id', $blog->id)->pluck('single', 'taxonomy_id')->map(fn ($single) => (bool) $single)->all())
        ->toBe([$this->categories->id => false]);

    // A taxonomy whose term pages there would have an address something else has: refused, nothing saved.
    Collection::query()->create(['handle' => 'tags_page', 'title' => 'Tags page', 'route' => '/blog/tags/{slug}']);
    $form(['taxonomies' => [(string) $this->categories->id, (string) $this->tags->id]])->assertSessionHasErrors('taxonomies');
    expect(DB::table('sunrice_collection_taxonomy')->where('collection_id', $blog->id)->count())->toBe(1);
});

it('links the term pages card to a term\'s page on the site', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser(Panel::ACCESS, 'cms.taxonomies.view', 'cms.taxonomies.update'));
    $links = fn (Assert $page) => collect($page->toArray()['props']['sections'])->firstWhere('title', 'Shared term pages')['links'] ?? null;

    get('/admin/cms/taxonomies/categories/edit')->assertInertia(fn (Assert $page) => expect($links($page))->toBeNull());
    $this->categories->forceFill(['route' => '/categories/{slug}'])->save();
    term($this->categories, 'News');
    get('/admin/cms/taxonomies/categories/edit')->assertInertia(fn (Assert $page) => expect($links($page))
        ->toBe([['label' => 'View a term page', 'url' => url('/categories/news'), 'newTab' => true, 'showWhen' => ['field' => 'pages', 'value' => null, 'not' => false]]]));
});
