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

it('keeps published entries\' terms in a table, for queries', function () {
    $php = term($this->tags, 'PHP');
    $laravel = term($this->tags, 'Laravel');
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [['handle' => 'tags', 'type' => 'terms', 'config' => ['taxonomy' => 'tags', 'multiple' => true]]]]);
    $posts = Collection::query()->create(['handle' => 'posts', 'title' => 'Posts', 'blueprint' => 'post']);
    $post = app(CreateEntry::class)->handle($posts, ['title' => 'Hello', 'data' => ['tags' => [$php->id]]]);

    expect($post->entry->terms()->count())->toBe(0); // drafts don't count

    app(PublishEntry::class)->handle($post);
    expect($post->entry->terms()->pluck('sunrice_terms.id')->all())->toBe([$php->id])
        ->and($php->entries()->count())->toBe(1);

    app(SaveDraft::class)->handle($post, ['data' => ['tags' => [$laravel->id]]]);
    app(PublishEntry::class)->handle($post);
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
        // Settings only for those who may change the taxonomy.
        ->where('taxonomy.settings', null));

    // To the trash with the term under it, back, and deleted for good (its child moves up).
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
    expect(TemplateHelp::text('cms.topic', ['cms.taxonomy-{handle}', 'cms.term']))->toBe(implode("\n", [
        'A Blade view: cms.topic is resources/views/cms/topic.blade.php.',
        'Empty: the first that exists of',
        '1. resources/views/cms/taxonomy-{handle}.blade.php',
        '2. resources/views/cms/term.blade.php',
        '3. the built-in template',
    ]));
});

it('creates a term on its own page, then goes to its page or back to the list', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser(Panel::ACCESS, 'cms.terms.view', 'cms.terms.create', 'cms.terms.update', 'cms.terms.delete'));
    $news = term($this->categories, 'News');

    get("/admin/cms/taxonomies/categories/terms/create?parent={$news->id}")->assertInertia(fn (Assert $page) => $page
        ->component('Cms/Terms/Create', shouldExist: false)
        ->where('taxonomy.hierarchical', true)
        ->where('parent', $news->id)
        ->where('terms.0.title', 'News'));

    // No pages or fields: back to the list.
    post('/admin/cms/taxonomies/categories/terms', ['title' => 'Local', 'parent_id' => $news->id])
        ->assertRedirect('/admin/cms/taxonomies/categories/terms?locale=en');
    expect(Term::query()->where('parent_id', $news->id)->count())->toBe(1);

    // With pages: to the new term's page.
    $this->tags->forceFill(['route' => '/tags/{slug}'])->save();
    post('/admin/cms/taxonomies/tags/terms', ['title' => 'Laravel'])->assertRedirectContains('/admin/cms/taxonomies/tags/terms/');
    $laravel = Term::query()->where('taxonomy_id', $this->tags->id)->firstOrFail();
    expect(session('success'))->toBe('"Laravel" added.');
    get("/admin/cms/taxonomies/tags/terms/{$laravel->id}/edit")->assertOk();

    actingAs(adminUser(Panel::ACCESS));
    get('/admin/cms/taxonomies/categories/terms/create')->assertForbidden();
});
