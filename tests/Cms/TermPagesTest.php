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

it('gives terms pages listing their live entries, with their fields and SEO', function () {
    app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->blog, ['title' => 'Hello', 'data' => ['topics' => [$this->news->id]]]));
    app(CreateEntry::class)->handle($this->blog, ['title' => 'Draft', 'data' => ['topics' => [$this->news->id]]]);
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
    actingAs(adminUser(Panel::ACCESS, 'cms.terms.manage'));

    get("/admin/cms/terms/{$this->news->id}/edit?locale=id")->assertInertia(fn (Assert $page) => $page
        ->component('Cms/Terms/Edit', shouldExist: false)->where('translated', true)->where('term.title', 'Berita'));
    put("/admin/cms/terms/{$this->news->id}/content", ['locale' => 'id', 'data' => ['about' => 'Semua berita'], 'seo' => [], 'template' => 'cms.topic'])->assertSessionHasNoErrors();
    put("/admin/cms/terms/{$this->news->id}/content", ['template' => 'Bad!'])->assertSessionHasErrors('template');
    // Only views under the site's prefixes: no other view of the app becomes a public page.
    put("/admin/cms/terms/{$this->news->id}/content", ['template' => 'layouts.app'])->assertSessionHasErrors('template');
    expect(fn () => app(SaveTermContent::class)->handle($this->news, ['template' => 'emails.invoice']))->toThrow(ValidationException::class, 'Use a view under cms.');

    get('/id/topics/berita')->assertOk()->assertSee('Semua berita');
    expect($this->news->fresh()?->template)->toBe('cms.topic');

    // A save over someone else's newer one is refused, template included.
    $translation = $this->news->translations()->where('locale', 'id')->firstOrFail();
    get("/admin/cms/terms/{$this->news->id}/edit?locale=id")->assertInertia(fn (Assert $page) => $page->where('version', $translation->version));
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
    actingAs(adminUser(Panel::ACCESS, 'cms.terms.tags.manage'));
    postJson("/admin/locks/cms.term/{$this->news->id}", ['editor' => 'tab-a', 'locale' => 'en'])->assertForbidden();

    $rina = adminUser(Panel::ACCESS, 'cms.terms.topics.manage');
    actingAs($rina);
    postJson("/admin/locks/cms.term/{$this->news->id}", ['editor' => 'tab-a', 'locale' => 'en'])->assertJson(['status' => 'ok']);
    postJson('/admin/locks/cms.term/999', ['editor' => 'tab-a', 'locale' => 'en'])->assertForbidden();

    // Someone else opening it meanwhile is told who's editing.
    actingAs(adminUser(Panel::ACCESS, 'cms.terms.manage'));
    postJson("/admin/locks/cms.term/{$this->news->id}", ['editor' => 'tab-b', 'locale' => 'en'])->assertJson(['status' => 'locked']);
    postJson("/admin/locks/cms.term/{$this->news->id}", ['editor' => 'tab-b', 'locale' => 'id'])->assertJson(['status' => 'ok']);
});
