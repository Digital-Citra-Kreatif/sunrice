<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Actions\Terms\SaveTermContent;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Content;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Support\Locales;

use function Pest\Laravel\get;

/*
 * <x-sunrice-cms::language-switcher>: the page shown in each language,
 * from Page::localeLinks().
 */
beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [['handle' => 'topics', 'type' => 'terms', 'config' => ['taxonomy' => 'topics', 'multiple' => true]]]]);
    $this->topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'route' => '/topics/{slug}']);
    $this->news = app(SaveTerm::class)->handle($this->topics, ['title' => 'News']);
    app(SaveTermContent::class)->handle($this->news, ['title' => 'Berita', 'slug' => 'berita'], 'id');
    $this->tips = app(SaveTerm::class)->handle($this->topics, ['title' => 'Tips']); // not translated
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}', 'listing_route' => '/blog', 'per_page' => 1]);
    $this->hello = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->blog, ['title' => 'Hello', 'data' => ['topics' => [$this->news->id, $this->tips->id]]]));
    $halo = app(CreateTranslation::class)->handle($this->hello->entry, 'id');
    app(SaveDraft::class)->handle($halo, ['title' => 'Halo', 'slug' => 'halo']);
    app(PublishEntry::class)->handle($halo->refresh());
    // Not translated: the main language's stands in at /id/blog/other.
    app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->blog, ['title' => 'Other', 'data' => ['topics' => [$this->news->id]]]));
});

function switcher(string $attributes = '', string $slot = ''): string
{
    $tag = $slot === '' ? "<x-sunrice-cms::language-switcher {$attributes} />" : "<x-sunrice-cms::language-switcher {$attributes}>{$slot}</x-sunrice-cms::language-switcher>";

    return trim(Blade::render($tag));
}

it('links the page in each language, the current one marked and not a link', function () {
    get('/blog/hello')->assertOk();
    $html = switcher('class="language-switcher"');

    expect($html)->toStartWith('<nav aria-label="Languages" class="language-switcher">')
        ->toContain('<li><span aria-current="true" lang="en">English</span></li>')
        ->toContain('<li><a href="'.url('/id/blog/halo').'" hreflang="id" lang="id">Bahasa Indonesia</a></li>')
        ->not->toContain('data-fallback')
        ->and(strpos($html, 'English'))->toBeLessThan((int) strpos($html, 'Bahasa Indonesia'));

    // On the Indonesian page: its label in Indonesian, English linked.
    get('/id/blog/halo')->assertOk();
    expect(switcher())->toContain('aria-label="Bahasa"')
        ->toContain('<a href="'.url('/blog/hello').'" hreflang="en" lang="en">English</a>')
        ->toContain('<span aria-current="true" lang="id">Bahasa Indonesia</span>');
});

it('follows the order of the site\'s languages and their names as set', function () {
    config(['sunrice-cms.locales' => ['id', 'en'], 'sunrice-cms.locale_names' => ['en' => 'English (US)']]);
    expect(Locales::nativeName('en'))->toBe('English (US)')->and(Locales::nativeName('id'))->toBe('Bahasa Indonesia')
        ->and(Locales::nativeName('xx'))->toBe('xx');
});

it('renders nothing with one language or on the app\'s own pages', function () {
    get('/blog/hello')->assertOk();
    config(['sunrice-cms.locales' => ['en']]);
    expect(switcher())->toBe('');

    config(['sunrice-cms.locales' => ['en', 'id']]);
    app()->instance('request', Request::create('/contact'));
    expect(Content::page())->toBeNull()->and(switcher())->toBe('');
});

it('links an untranslated page in the main language, marked as such', function () {
    get('/blog/other')->assertOk();
    expect(switcher())->toContain('<a href="'.url('/id/blog/other').'" hreflang="id" lang="id" data-fallback>Bahasa Indonesia</a>')
        ->not->toContain('visually-hidden');
    expect(switcher('fallback-note'))->toContain('data-fallback>Bahasa Indonesia<span class="visually-hidden" lang="en"> (not translated yet)</span></a>');

    // Shown in Indonesian (the main language's page standing in): itself marked.
    get('/id/blog/other')->assertOk();
    expect(switcher())->toContain('<span aria-current="true" lang="id" data-fallback>Bahasa Indonesia</span>')
        ->toContain('<a href="'.url('/blog/other').'" hreflang="en" lang="en">English</a>');
});

it('links the homepage or leaves the language out with the fallback off', function () {
    config(['sunrice-cms.site.fallback' => false]);
    get('/blog/other')->assertOk();

    expect(Content::localeUrls())->toBe(['en' => url('/blog/other')])
        ->and(switcher())->toContain('<a href="'.url('/id').'" hreflang="id" lang="id" data-home>Bahasa Indonesia</a>')
        ->and(switcher('missing="hide"'))->toBe('');
    get('/blog/hello')->assertOk();
    expect(switcher('missing="hide"'))->toContain('href="'.url('/id/blog/halo').'"');
});

it('keeps a listing\'s and a term page\'s query string', function () {
    get('/blog?page=2')->assertOk();
    expect(switcher())->toContain('href="'.url('/id/blog').'?page=2" hreflang="id"');

    get('/topics/news?ref=menu')->assertOk();
    expect(switcher())->toContain('href="'.url('/id/topics/berita').'?ref=menu" hreflang="id"');
    // An untranslated term: the main language's, standing in.
    get('/topics/tips')->assertOk();
    expect(switcher())->toContain('href="'.url('/id/topics/tips').'" hreflang="id" lang="id" data-fallback>');
});

it('shows the links in a details dropdown, without a script', function () {
    get('/blog/hello')->assertOk();
    $html = switcher('variant="dropdown"');

    expect($html)->toContain('<details>')
        ->toContain('<summary><span lang="en">English</span></summary>')
        ->toContain('<a href="'.url('/id/blog/halo').'" hreflang="id" lang="id">Bahasa Indonesia</a>')
        ->not->toContain('<script')->not->toContain('onchange')->not->toContain('<select');
});

it('labels the languages by code, keeping their names', function () {
    get('/blog/hello')->assertOk();

    expect(switcher('labels="code"'))->toContain('<span aria-current="true" lang="en"><abbr title="English">EN</abbr></span>')
        ->toContain('<a href="'.url('/id/blog/halo').'" hreflang="id" lang="id" title="Bahasa Indonesia" aria-label="Bahasa Indonesia">ID</a>');
    expect(fn () => switcher('variant="select"'))->toThrow(Exception::class, 'variant="select"');
});

it('leaves the markup to a slot', function () {
    get('/id/blog/other')->assertOk();
    $html = switcher('', '@foreach ($component->languages as $language)<a href="{{ $language->url }}" data-current="{{ $language->current ? 1 : 0 }}" data-fallback="{{ $language->fallback ? 1 : 0 }}">{{ $language->code }} {{ $language->tag }} {{ $language->name }}</a>@endforeach');

    expect($html)->toBe('<a href="'.url('/blog/other').'" data-current="0" data-fallback="0">en en English</a><a href="'.url('/id/blog/other').'" data-current="1" data-fallback="1">id id Bahasa Indonesia</a>');
});

it('makes no queries of its own, however many languages', function () {
    config(['sunrice-cms.locales' => ['en', 'id', 'de']]);
    get('/blog/hello')->assertOk();
    DB::enableQueryLog();
    DB::flushQueryLog();
    Content::localeUrls();
    $urls = count(DB::getQueryLog());
    DB::flushQueryLog();
    $html = switcher();

    expect(count(DB::getQueryLog()))->toBe($urls)
        ->and($html)->toContain('lang="de" data-fallback>Deutsch</a>');
});
