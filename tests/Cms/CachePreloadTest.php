<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Tables\ActionFailed;
use Sunrice\Cms\Actions\Cache\ClearCache;
use Sunrice\Cms\Actions\Cache\PreloadCache;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Jobs\PreloadPages;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Settings\CacheSettings;
use Sunrice\Cms\Support\SiteCache;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['sunrice-cms.cache.pages' => true, 'responsecache.debug.enabled' => true, 'sunrice-cms.locales' => ['en', 'id'],
        'sunrice-cms.cache.preload.delay' => 0, 'app.url' => 'http://localhost', 'queue.default' => 'database']);
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [['handle' => 'intro', 'type' => 'text']]]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}', 'listing_route' => '/blog']);
    $this->pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'post', 'route' => '/{slug}']);
});

function preloadPage(Collection $collection, string $title, ?array $seo = null, ?string $indonesian = null): EntryTranslation
{
    $translation = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($collection, ['title' => $title, 'seo' => $seo]));
    if ($indonesian !== null) {
        app(PublishEntry::class)->handle(app(CreateTranslation::class)->handle($translation->entry, 'id', ['title' => $indonesian]));
    }

    return $translation;
}

/** @return list<string> the addresses of the preload under way, batch by batch */
function preloadUrls(): array
{
    $run = app(PreloadCache::class)->status()['run'];
    $urls = [];
    for ($n = 0; is_array($batch = app(SiteCache::class)->store()->get(PreloadCache::BATCH."{$run}:{$n}")); $n++) {
        array_push($urls, ...$batch);
    }

    return $urls;
}

it('preloads the sitemap\'s pages in every language, but those left out or not indexed', function () {
    Queue::fake();
    preloadPage($this->pages, 'About', indonesian: 'Tentang');
    preloadPage($this->pages, 'Contact');
    preloadPage($this->pages, 'Secret', ['noindex' => true]);
    preloadPage($this->blog, 'Hello');
    app(CacheSettings::class)->fill(['exclude' => '/contact'])->save();

    expect(app(PreloadCache::class)->handle())->toBe(5);
    expect(preloadUrls())->toContain('http://localhost/about', 'http://localhost/id/about', 'http://localhost/blog/hello', 'http://localhost/blog', 'http://localhost/id/blog')
        ->not->toContain('http://localhost/contact', 'http://localhost/secret');
    Queue::assertPushed(PreloadPages::class, 1);

    // A collection left out: its pages and listing too.
    PreloadCache::finish(app(PreloadCache::class)->status()['run']);
    app(CacheSettings::class)->fill(['collections' => ['blog']])->save();
    app(PreloadCache::class)->handle();
    expect(preloadUrls())->toEqualCanonicalizing(['http://localhost/about', 'http://localhost/id/about']);

    // One language, and at most so many.
    PreloadCache::finish(app(PreloadCache::class)->status()['run']);
    expect(app(PreloadCache::class)->handle(locale: 'id'))->toBe(1)->and(preloadUrls())->toBe(['http://localhost/id/about']);
    PreloadCache::finish(app(PreloadCache::class)->status()['run']);
    expect(app(PreloadCache::class)->handle(limit: 1))->toBe(1);
});

it('keeps each page it requests, as a visitor, batch after batch', function () {
    Queue::fake();
    config(['sunrice-cms.cache.preload.batch' => 2]);
    preloadPage($this->pages, 'About');
    preloadPage($this->pages, 'Team');
    preloadPage($this->blog, 'Hello');
    actingAs(adminUser(Panel::ACCESS));

    expect(app(PreloadCache::class)->handle())->toBe(5); // 3 pages, the listing in 2 languages
    $jobs = 0;
    while (($job = Queue::pushed(PreloadPages::class)->get($jobs)) !== null) {
        $job->handle();
        $jobs++;
    }
    expect($jobs)->toBe(3);
    $status = app(PreloadCache::class)->status();
    expect($status['warmed'])->toBe(5)->and($status['failed'])->toBe(0)->and($status['finished_at'])->not->toBeNull()
        ->and($status['by'])->toBe('Rina')
        ->and(app(PreloadCache::class)->running())->toBeFalse();

    auth()->logout();
    get('/about')->assertOk()->assertHeader('X-Cache-Status', 'HIT');
    get('/blog/hello')->assertHeader('X-Cache-Status', 'HIT');
    get('/id/blog')->assertHeader('X-Cache-Status', 'HIT');
});

it('runs one preload at a time, and stops when whole pages stop being kept', function () {
    Queue::fake();
    preloadPage($this->pages, 'About');

    app(PreloadCache::class)->handle();
    expect(app(PreloadCache::class)->running())->toBeTrue()
        ->and(fn () => app(PreloadCache::class)->handle())->toThrow(ActionFailed::class, 'A preload is running already.');

    config(['sunrice-cms.cache.pages' => false]);
    Queue::pushed(PreloadPages::class)->first()->handle();
    expect(app(PreloadCache::class)->running())->toBeFalse()
        ->and(app(PreloadCache::class)->status()['stopped'])->toBe('The whole-page cache was turned off.')
        ->and(fn () => app(PreloadCache::class)->handle())->toThrow(ActionFailed::class, 'nothing to preload');
});

it('preloads from the command line, queued or now', function () {
    Queue::fake();
    preloadPage($this->pages, 'About', indonesian: 'Tentang');
    preloadPage($this->pages, 'Team');

    artisan('sunrice:cache-preload --limit=0')->assertFailed();
    artisan('sunrice:cache-preload --locale=fr')->assertFailed();
    artisan('sunrice:cache-preload --locale=id')->expectsOutputToContain('Preloading 2 pages in queued jobs')->assertSuccessful();
    Queue::assertPushed(PreloadPages::class, 1);
    artisan('sunrice:cache-preload')->expectsOutputToContain('A preload is running already.')->assertFailed();
    PreloadCache::finish(app(PreloadCache::class)->status()['run']);

    artisan('sunrice:cache-preload --limit=2 --now')->expectsOutputToContain('Preloaded 2 pages: 2 kept, 0 failed.')->assertSuccessful();
    expect(app(PreloadCache::class)->running())->toBeFalse();
    get('/about')->assertHeader('X-Cache-Status', 'HIT');
    expect(Activity::query()->where('action', 'preloaded')->count())->toBe(2);
});

it('preloads after clearing or content changes when the settings say so', function () {
    Queue::fake();
    preloadPage($this->pages, 'About');
    $runs = fn (): int => Queue::pushed(PreloadPages::class)->count();

    app(ClearCache::class)->handle();
    expect($runs())->toBe(0);
    app(CacheSettings::class)->fill(['preloadAfterClear' => true])->save();
    app(ClearCache::class)->handle(ClearCache::PAGES);
    expect($runs())->toBe(1);
    PreloadCache::finish(app(PreloadCache::class)->status()['run']);

    // After content changes: a minute later, once for several changes.
    preloadPage($this->pages, 'Team');
    expect($runs())->toBe(1);
    app(CacheSettings::class)->fill(['preloadAfterChanges' => true])->save();
    preloadPage($this->pages, 'Jobs');
    preloadPage($this->pages, 'Press');
    expect($runs())->toBe(2);
    Queue::assertPushed(PreloadPages::class, fn (PreloadPages $job) => $job->delay === 60);
});

it('preloads from Settings → Cache, logged, and shows how far it got', function () {
    Queue::fake();
    preloadPage($this->pages, 'About');
    actingAs($user = adminUser(Panel::ACCESS, 'cms.settings.cache'));
    $card = fn (): array => CacheSettings::cards($user)[0]->toArray();
    expect(collect($card()['buttons'])->pluck('label'))->toContain('Preload cache');

    post('/admin/cms/cache/preload')->assertRedirect()->assertSessionHas('success', 'Preloading 3 pages in the background.');
    expect(Activity::query()->where('action', 'preloaded')->firstOrFail()->sentence())->toBe('Rina preloaded the page cache')
        ->and(collect($card()['buttons'])->pluck('label'))->not->toContain('Preload cache')
        ->and(collect($card()['blocks'])->where('type', 'notice')->pluck('text')->implode(' '))->toContain('Preloading now: 0 of 3 pages so far.');
    post('/admin/cms/cache/preload')->assertSessionHas('error', 'A preload is running already.');

    Queue::pushed(PreloadPages::class)->first()->handle();
    $rows = collect(collect($card()['blocks'])->firstWhere('type', 'rows')['rows'])->pluck('value', 'label');
    expect($rows['Pages preloaded'])->toBe('3 of 3, 0 failed')->and($rows)->toHaveKeys(['Last preload', 'Preload finished']);

    actingAs(adminUser(Panel::ACCESS, 'cms.settings.site'));
    post('/admin/cms/cache/preload')->assertForbidden();
    config(['sunrice-cms.cache.pages' => false]);
    actingAs($user);
    post('/admin/cms/cache/preload')->assertSessionHas('error', 'The whole-page cache is off: there\'s nothing to preload.');
});
