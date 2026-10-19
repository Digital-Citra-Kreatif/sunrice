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
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Fields\Field;
use Sunrice\Cms\Fields\HydrationContext;
use Sunrice\Cms\Fields\Schema;
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
        ->and(DB::getQueryLog())->toHaveCount(4); // two per terms field: the terms, then their translations
    expect($schema->toAdmin()[0]['terms'])->toBe([['value' => (string) $php->id, 'label' => 'PHP'], ['value' => (string) $laravel->id, 'label' => 'Laravel']]);
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

    post('/admin/taxonomies', ['title' => 'Topics', 'handle' => 'topics', 'collections' => [(string) $pages->id]])->assertSessionHasNoErrors();
    expect(Taxonomy::query()->where('handle', 'topics')->firstOrFail()->collections->pluck('handle')->all())->toBe(['pages']);
    get('/admin/cms/terms')->assertForbidden();

    actingAs(adminUser(Panel::ACCESS, 'cms.terms.manage'));
    get('/admin/cms/terms')->assertRedirect('/admin/cms/taxonomies/categories/terms');
    post('/admin/cms/taxonomies/categories/terms', ['title' => 'News'])->assertSessionHasNoErrors();
    $news = Term::query()->firstOrFail();
    post('/admin/cms/taxonomies/categories/terms', ['title' => 'Local', 'parent_id' => $news->id])->assertSessionHasNoErrors();
    put("/admin/cms/terms/{$news->id}", ['title' => 'Headlines'])->assertSessionHasNoErrors();

    get('/admin/cms/taxonomies/categories/terms')->assertInertia(fn (Assert $page) => $page
        ->component('Cms/Terms/Index', shouldExist: false)
        ->where('terms.0.title', 'Headlines')
        ->where('terms.1.title', 'Local')
        ->where('terms.1.depth', 1));

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
