<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Actions\Terms\SaveTermContent;
use Sunrice\Cms\Actions\Terms\TrashTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Taxonomy;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;
use function Pest\Laravel\put;

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [['handle' => 'topics', 'type' => 'terms', 'config' => ['taxonomy' => 'topics', 'multiple' => true]]]]);
    Blueprint::query()->create(['handle' => 'topic', 'title' => 'Topic', 'fields' => [['handle' => 'about', 'type' => 'text']]]);
    $this->topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'route' => '/topics/{slug}', 'blueprint' => 'topic']);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}']);
    $this->news = app(SaveTerm::class)->handle($this->topics, ['title' => 'News']);
});

it('tells the term editor whether a new slug moves the term\'s page, and where to add a redirect', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.terms.view', 'cms.terms.update', 'cms.redirects.view', 'cms.redirects.create'));
    $edit = "/admin/cms/taxonomies/topics/terms/{$this->news->id}/edit";
    get($edit)->assertOk()->assertInertia(fn (Assert $page) => $page->where('term.slug', 'news')->where('term.hasPage', true)->where('redirectsUrl', route('sunrice.admin.cms.redirects.create')));

    $this->topics->forceFill(['route' => null])->save();
    get($edit)->assertInertia(fn (Assert $page) => $page->where('term.hasPage', false));

    actingAs(adminUser(Panel::ACCESS, 'cms.terms.view', 'cms.terms.update'));
    get($edit)->assertOk()->assertInertia(fn (Assert $page) => $page->where('redirectsUrl', null));
});

it('gives terms pages listing their live entries, with their fields and SEO', function () {
    // The blog's entries have its terms, without term pages of its own.
    $this->topics->collections()->attach($this->blog->id, ['term_pages' => false, 'index_page' => false]);
    app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->blog, ['title' => 'Hello', 'terms' => ['topics' => [$this->news->id]]]));
    app(CreateEntry::class)->handle($this->blog, ['title' => 'Draft', 'terms' => ['topics' => [$this->news->id]]]);
    app(SaveTermContent::class)->handle($this->news, ['data' => ['about' => 'All the <news>'], 'seo' => ['description' => 'News, daily']]);

    get('/topics/news')->assertOk()->assertSee('<h1>News</h1>', false)->assertSee('All the &lt;news&gt;', false)
        ->assertSee('Hello')->assertDontSee('Draft')
        ->assertSee('<meta name="description" content="News, daily">', false)
        ->assertSee('<link rel="canonical" href="'.url('/topics/news').'">', false);
    expect($this->news->fresh()?->url('en'))->toBe(url('/topics/news'));
    get('/sitemap.xml')->assertSee(url('/topics/news'));
    get('/topics/nothing')->assertNotFound();
    config(['sunrice-cms.site.fallback' => false]);
    get('/id/topics/news')->assertNotFound(); // not translated
    config(['sunrice-cms.site.fallback' => true]);
});

it('takes terms in the trash off the site', function () {
    app(TrashTerm::class)->handle($this->news);
    get('/topics/news')->assertNotFound();
    get('/sitemap.xml')->assertDontSee('/topics/news');
});

it('tries the more specific pattern first, collections before taxonomies', function () {
    $this->topics->update(['route' => '/blog/topics/{slug}']);
    app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->blog, ['title' => 'Topics']));

    get('/blog/topics/news')->assertOk()->assertSee('<h1>News</h1>', false);
    get('/blog/topics')->assertOk()->assertSee('Topics');
});

it('edits a term\'s page per language, and checks the template', function () {
    app(SaveTerm::class)->handle($this->topics, ['title' => 'Berita', 'locale' => 'id'], $this->news);
    actingAs(adminUser(Panel::ACCESS, 'cms.terms.view', 'cms.terms.create', 'cms.terms.update', 'cms.terms.delete'));

    get(termEditUrl($this->news->id, 'id'))->assertInertia(fn (Assert $page) => $page
        ->component('Cms/Terms/Edit', shouldExist: false)->where('translated', true)->where('term.title', 'Berita'));
    put("/admin/cms/terms/{$this->news->id}/content", ['locale' => 'id', 'data' => ['about' => 'Semua berita'], 'seo' => [], 'template' => 'cms.topic'])->assertSessionHasNoErrors();
    put("/admin/cms/terms/{$this->news->id}/content", ['template' => 'Bad!'])->assertSessionHasErrors('template');
    // By default only views under cms. and pages.
    put("/admin/cms/terms/{$this->news->id}/content", ['template' => 'topics.special'])->assertSessionHasErrors('template');
    // With no prefixes, the app's own views, not layouts, mails and the like: they don't become public pages.
    config(['sunrice-cms.site.template_prefixes' => null]);
    put("/admin/cms/terms/{$this->news->id}/content", ['template' => 'layouts.app'])->assertSessionHasErrors('template');
    expect(fn () => app(SaveTermContent::class)->handle($this->news, ['template' => 'emails.invoice']))->toThrow(ValidationException::class, 'Use a view of your own, not under layouts, partials');
    put("/admin/cms/terms/{$this->news->id}/content", ['template' => 'topics.special'])->assertSessionHasNoErrors();
    config(['sunrice-cms.site.template_prefixes' => ['cms.']]);
    expect(fn () => app(SaveTermContent::class)->handle($this->news->fresh(), ['template' => 'topics.other']))->toThrow(ValidationException::class, 'Use a view under cms.');
    put("/admin/cms/terms/{$this->news->id}/content", ['template' => 'cms.topic'])->assertSessionHasNoErrors();

    get('/id/topics/berita')->assertOk()->assertSee('Semua berita');
    expect($this->news->fresh()?->template)->toBe('cms.topic');

    // A save over someone else's newer one is refused, template included.
    $translation = $this->news->translations()->where('locale', 'id')->firstOrFail();
    get(termEditUrl($this->news->id, 'id'))->assertInertia(fn (Assert $page) => $page->where('version', $translation->version));
    put("/admin/cms/terms/{$this->news->id}/content", ['locale' => 'id', 'data' => ['about' => 'Berita'], 'version' => $translation->version])->assertSessionHasNoErrors();
    put("/admin/cms/terms/{$this->news->id}/content", ['locale' => 'id', 'data' => ['about' => 'Stale'], 'template' => 'cms.stale', 'version' => $translation->version])->assertSessionHasErrors('version');
    expect($translation->fresh()?->only(['version']))->toBe(['version' => $translation->version + 1])
        ->and($translation->fresh()?->data['about'] ?? null)->toBe('Berita')
        ->and($this->news->fresh()?->template)->toBe('cms.topic');
    put("/admin/cms/terms/{$this->news->id}/content", ['locale' => 'id', 'data' => ['about' => 'Mine anyway']])->assertSessionHasNoErrors();
    expect(fn () => app(SaveTermContent::class)->handle($this->news, ['data' => []], 'fr'))->toThrow(ValidationException::class);
});

it('locks a term\'s page for those who manage its taxonomy', function () {
    actingAs(adminUser(Panel::ACCESS));
    postJson("/admin/locks/cms.term/{$this->news->id}", ['editor' => 'tab-a', 'locale' => 'en'])->assertForbidden();

    Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    actingAs(adminUser(Panel::ACCESS, 'cms.terms.tags.view', 'cms.terms.tags.create', 'cms.terms.tags.update', 'cms.terms.tags.delete'));
    postJson("/admin/locks/cms.term/{$this->news->id}", ['editor' => 'tab-a', 'locale' => 'en'])->assertForbidden();

    $rina = adminUser(Panel::ACCESS, 'cms.terms.topics.view', 'cms.terms.topics.create', 'cms.terms.topics.update', 'cms.terms.topics.delete');
    actingAs($rina);
    postJson("/admin/locks/cms.term/{$this->news->id}", ['editor' => 'tab-a', 'locale' => 'en'])->assertJson(['status' => 'ok']);
    postJson('/admin/locks/cms.term/999', ['editor' => 'tab-a', 'locale' => 'en'])->assertForbidden();

    // Someone else opening it meanwhile is told who's editing.
    actingAs(adminUser(Panel::ACCESS, 'cms.terms.view', 'cms.terms.create', 'cms.terms.update', 'cms.terms.delete'));
    postJson("/admin/locks/cms.term/{$this->news->id}", ['editor' => 'tab-b', 'locale' => 'en'])->assertJson(['status' => 'locked']);
    postJson("/admin/locks/cms.term/{$this->news->id}", ['editor' => 'tab-b', 'locale' => 'id'])->assertJson(['status' => 'ok']);
});

it('names a term in a language on its own page, adding the language there', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.terms.view', 'cms.terms.update'));
    get(termEditUrl($this->news->id, 'id'))->assertInertia(fn (Assert $page) => $page
        ->where('translated', false)->where('term.ownTitle', null)->where('term.mainTitle', 'News'));

    // Not translated: the title is needed, and saving it adds the language with its page content.
    put("/admin/cms/terms/{$this->news->id}/content", ['locale' => 'id', 'data' => ['about' => 'Semua berita']])->assertSessionHasErrors('title');
    put("/admin/cms/terms/{$this->news->id}/content", ['locale' => 'id', 'title' => 'Berita', 'slug' => '', 'data' => ['about' => 'Semua berita'], 'seo' => ['description' => 'Berita harian']])
        ->assertSessionHasNoErrors();
    $berita = $this->news->translations()->where('locale', 'id')->firstOrFail();
    expect($berita->only(['title', 'slug']))->toBe(['title' => 'Berita', 'slug' => 'berita'])
        ->and($berita->data['about'] ?? null)->toBe('Semua berita');
    get('/id/topics/berita')->assertOk()->assertSee('Semua berita')->assertSee('Berita harian', false);

    // Renamed with its content, in one save that checks the version once.
    put("/admin/cms/terms/{$this->news->id}/content", ['locale' => 'id', 'title' => 'Kabar', 'slug' => 'kabar', 'data' => ['about' => 'Semua kabar'], 'version' => $berita->version])->assertSessionHasNoErrors();
    expect($berita->fresh()?->only(['title', 'slug', 'version']))->toBe(['title' => 'Kabar', 'slug' => 'kabar', 'version' => $berita->version + 2]);
    put("/admin/cms/terms/{$this->news->id}/content", ['locale' => 'id', 'title' => 'Stale', 'version' => $berita->version])->assertSessionHasErrors('version');
    expect($berita->fresh()?->title)->toBe('Kabar');
});

it('gives terms of a taxonomy without a blueprint a description', function () {
    $tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags', 'route' => '/tags/{slug}']);
    $coffee = app(SaveTerm::class)->handle($tags, ['title' => 'Coffee']);
    app(SaveTermContent::class)->handle($coffee, ['data' => ['description' => 'All about coffee.']]);

    expect(array_map(fn ($field) => $field->handle, $tags->schema()->fields()))->toBe(['description'])
        ->and(array_map(fn ($field) => $field->handle, $this->topics->schema()->fields()))->toBe(['about']);
    get('/tags/coffee')->assertOk()->assertSee('<p>All about coffee.</p>', false);
});

it('knows a term has a page when only its collections give it one', function () {
    $this->topics->forceFill(['route' => null])->save();
    $this->topics->collections()->attach($this->blog->id);
    actingAs(adminUser(Panel::ACCESS, 'cms.terms.view', 'cms.terms.update'));
    $edit = "/admin/cms/taxonomies/topics/terms/{$this->news->id}/edit";

    get($edit)->assertOk()->assertInertia(fn (Assert $page) => $page->where('term.hasPage', true)->where('hasPages', true)
        // The collection's files come first in "Which file?".
        ->where('templates.fallbacks', fn ($views) => collect($views)->pluck('view')->all() === ['cms.collections.blog.topics.news', 'cms.collections.blog.topics.show', 'cms.taxonomies.topics.news', 'cms.taxonomies.topics.show', 'cms.taxonomies.show']));
    get('/admin/cms/taxonomies/topics/terms')->assertOk()->assertInertia(fn (Assert $page) => $page->where('terms.0.url', url('/blog/topics/news')));

    $this->topics->collections()->updateExistingPivot($this->blog->id, ['term_pages' => false]);
    get($edit)->assertInertia(fn (Assert $page) => $page->where('term.hasPage', false)->where('hasPages', false)
        ->where('templates.fallbacks', fn ($views) => collect($views)->pluck('view')->all() === ['cms.taxonomies.topics.news', 'cms.taxonomies.topics.show', 'cms.taxonomies.show']));
    get('/admin/cms/taxonomies/topics/terms')->assertInertia(fn (Assert $page) => $page->where('terms.0.url', null));
    expect($this->topics->fresh()->hasTermPages())->toBeFalse();
});
