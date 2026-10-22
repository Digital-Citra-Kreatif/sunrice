<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Site\Site;
use Sunrice\Cms\Site\TemplateLookup;

use function Pest\Laravel\get;

/*
 * Which view each kind of page renders with: resources/views/cms/collections/…
 * and cms/taxonomies/…, a folder per handle and a file per main-language
 * slug, the templates picked in the CMS first.
 */

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [['handle' => 'tags', 'type' => 'terms', 'config' => ['taxonomy' => 'tags', 'multiple' => true]]]]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}', 'listing_route' => '/blog']);
    $this->tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags', 'route' => '/tags/{slug}', 'index_route' => '/tags']);
    $this->tags->collections()->attach($this->blog->id);
    $this->news = app(SaveTerm::class)->handle($this->tags, ['title' => 'News']);
    app(SaveTerm::class)->handle($this->tags, ['title' => 'Berita', 'slug' => 'berita', 'locale' => 'id'], $this->news);
    $this->hello = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->blog, ['title' => 'Hello', 'data' => ['tags' => [$this->news->id]]]));
    $halo = app(CreateTranslation::class)->handle($this->hello->entry, 'id');
    app(SaveDraft::class)->handle($halo, ['title' => 'Halo', 'slug' => 'halo']);
    app(PublishEntry::class)->handle($halo->refresh());
    app()->forgetInstance(Site::class);

    $this->views = sys_get_temp_dir().'/sunrice-lookup-'.uniqid();
    File::ensureDirectoryExists("{$this->views}/cms");
    View::addLocation($this->views);
    // A template, by its view name: "cms.collections.show" → cms/collections/show.blade.php.
    $this->template = function (string $view): void {
        $file = "{$this->views}/".str_replace('.', '/', $view).'.blade.php';
        File::ensureDirectoryExists(dirname($file));
        File::put($file, "[{$view}]");
    };
});

afterEach(fn () => File::deleteDirectory($this->views));

/** The view the page at $url rendered with, as its body says. */
function renderedWith(string $url): string
{
    $html = (string) get($url)->assertOk()->getContent();

    return preg_match('/^\[([a-z0-9_.-]+)\]$/', trim($html), $m) === 1 ? $m[1] : 'built-in';
}

it('renders an entry with its own template, its slug\'s file, the collection\'s template, then its folder\'s and any entry\'s', function () {
    expect(renderedWith('/blog/hello'))->toBe('built-in');
    foreach (['cms.collections.show', 'cms.collections.blog.show', 'cms.post', 'cms.collections.blog.hello', 'cms.special'] as $view) {
        ($this->template)($view);
        if ($view === 'cms.post') {
            $this->blog->update(['template' => 'cms.post']);
        }
        if ($view === 'cms.special') {
            $this->hello->entry->update(['template' => 'cms.special']);
        }
        expect(renderedWith('/blog/hello'))->toBe($view)
            // One file for every language: the main language's slug picks it.
            ->and(renderedWith('/id/blog/halo'))->toBe($view);
    }
    // Never a view outside the site's template prefixes, whatever is stored.
    $this->hello->entry->update(['template' => 'sunrice-cms::components.seo']);
    expect(renderedWith('/blog/hello'))->toBe('cms.collections.blog.hello');
});

it('never picks a slug file for the reserved slugs, nor one that can\'t name a file', function () {
    ($this->template)('cms.collections.blog.show');
    ($this->template)('cms.collections.blog.index');
    foreach (['index', 'show'] as $slug) {
        app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->blog, ['title' => ucfirst($slug), 'slug' => $slug]));
        expect(renderedWith("/blog/{$slug}"))->toBe('cms.collections.blog.show');
    }

    expect(TemplateLookup::entry($this->blog, 'show'))->toBe(['cms.collections.blog.show', 'cms.collections.show'])
        ->and(TemplateLookup::entry($this->blog, 'a.b'))->toBe(['cms.collections.blog.show', 'cms.collections.show'])
        ->and(TemplateLookup::entry($this->blog, 'Héllo'))->toBe(['cms.collections.blog.show', 'cms.collections.show'])
        ->and(TemplateLookup::entry($this->blog, 'case_study-2', 'cms.mine'))->toBe(['cms.mine', 'cms.collections.blog.case_study-2', 'cms.collections.blog.show', 'cms.collections.show']);
});

it('renders a listing page with the collection\'s listing template, then its folder\'s index, then any listing\'s', function () {
    expect(renderedWith('/blog'))->toBe('built-in');
    ($this->template)('cms.collections.index');
    expect(renderedWith('/blog'))->toBe('cms.collections.index');
    ($this->template)('cms.collections.blog.index');
    expect(renderedWith('/blog'))->toBe('cms.collections.blog.index');
    ($this->template)('cms.blog-home');
    $this->blog->update(['listing_template' => 'cms.blog-home']);
    expect(renderedWith('/blog'))->toBe('cms.blog-home')
        ->and(renderedWith('/id/blog'))->toBe('cms.blog-home');
});

it('renders a shared term page with its own template, its slug\'s file, the taxonomy\'s template, then its folder\'s and any term\'s', function () {
    expect(renderedWith('/tags/news'))->toBe('built-in');
    foreach (['cms.taxonomies.show', 'cms.taxonomies.tags.show', 'cms.topic', 'cms.taxonomies.tags.news', 'cms.special'] as $view) {
        ($this->template)($view);
        match ($view) {
            'cms.topic' => $this->tags->update(['template' => 'cms.topic']),
            'cms.special' => $this->news->update(['template' => 'cms.special']),
            default => null,
        };
        expect(renderedWith('/tags/news'))->toBe($view)
            ->and(renderedWith('/id/tags/berita'))->toBe($view);
    }
});

it('renders a collection\'s term page with the collection\'s folder for the taxonomy first, then as a shared one', function () {
    ($this->template)('cms.taxonomies.tags.news');
    expect(renderedWith('/blog/tags/news'))->toBe('cms.taxonomies.tags.news');
    ($this->template)('cms.collections.blog.tags.show');
    expect(renderedWith('/blog/tags/news'))->toBe('cms.collections.blog.tags.show')
        ->and(renderedWith('/tags/news'))->toBe('cms.taxonomies.tags.news');
    ($this->template)('cms.collections.blog.tags.news');
    expect(renderedWith('/blog/tags/news'))->toBe('cms.collections.blog.tags.news')
        ->and(renderedWith('/id/blog/tags/berita'))->toBe('cms.collections.blog.tags.news');
    ($this->template)('cms.special');
    $this->news->update(['template' => 'cms.special']);
    expect(renderedWith('/blog/tags/news'))->toBe('cms.special');
});

it('renders taxonomy index pages with the taxonomy\'s index template, then the collection\'s folder, the taxonomy\'s and any index\'s', function () {
    expect(renderedWith('/tags'))->toBe('built-in');
    ($this->template)('cms.taxonomies.index');
    expect(renderedWith('/tags'))->toBe('cms.taxonomies.index')->and(renderedWith('/blog/tags'))->toBe('cms.taxonomies.index');
    ($this->template)('cms.taxonomies.tags.index');
    expect(renderedWith('/tags'))->toBe('cms.taxonomies.tags.index')->and(renderedWith('/blog/tags'))->toBe('cms.taxonomies.tags.index');
    ($this->template)('cms.collections.blog.tags.index');
    expect(renderedWith('/tags'))->toBe('cms.taxonomies.tags.index')->and(renderedWith('/blog/tags'))->toBe('cms.collections.blog.tags.index');
    ($this->template)('cms.tag-cloud');
    $this->tags->update(['index_template' => 'cms.tag-cloud']);
    expect(renderedWith('/tags'))->toBe('cms.tag-cloud')->and(renderedWith('/blog/tags'))->toBe('cms.tag-cloud');
});

it('reads the main language\'s slug from the translations already loaded', function () {
    $entry = $this->hello->entry->fresh(['translations']);
    $halo = $entry->translations->firstWhere('locale', 'id')->setRelation('entry', $entry);
    $term = $this->news->fresh(['translations']);
    $berita = $term->translations->firstWhere('locale', 'id')->setRelation('term', $term);
    DB::flushQueryLog();
    DB::enableQueryLog();

    expect(TemplateLookup::entrySlug($halo))->toBe('hello')
        ->and(TemplateLookup::termSlug($berita))->toBe('news')
        ->and(DB::getQueryLog())->toBe([]);
});

it('looks for each view once', function () {
    $lookup = new TemplateLookup;
    expect($lookup->first(['cms.nope', 'cms.nope', 'sunrice-cms::entry'], 'x'))->toBe('sunrice-cms::entry')
        ->and($lookup->exists('cms.nope'))->toBeFalse();
    ($this->template)('cms.nope');
    // Remembered for this lookup; a new one sees the file.
    expect($lookup->exists('cms.nope'))->toBeFalse()
        ->and((new TemplateLookup)->exists('cms.nope'))->toBeTrue();
});

it('ignores a collection\'s or taxonomy\'s stored template outside the site\'s template prefixes', function () {
    ($this->template)('cms.collections.show');
    ($this->template)('cms.collections.index');
    ($this->template)('cms.taxonomies.show');
    ($this->template)('cms.taxonomies.index');
    ($this->template)('layouts.app');
    $this->blog->forceFill(['template' => 'layouts.app', 'listing_template' => 'layouts.app'])->save();
    $this->tags->forceFill(['template' => 'layouts.app', 'index_template' => 'layouts.app'])->save();

    expect(renderedWith('/blog/hello'))->toBe('cms.collections.show')
        ->and(renderedWith('/blog'))->toBe('cms.collections.index')
        ->and(renderedWith('/tags/news'))->toBe('cms.taxonomies.show')
        ->and(renderedWith('/blog/tags/news'))->toBe('cms.taxonomies.show')
        ->and(renderedWith('/tags'))->toBe('cms.taxonomies.index')
        ->and(renderedWith('/blog/tags'))->toBe('cms.taxonomies.index');
});
