<?php

declare(strict_types=1);

use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Globals\SaveGlobals;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\GlobalSet;
use Sunrice\Cms\Models\Redirect;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Support\SiteCache;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    actingAs(adminUser());
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [
        ['handle' => 'intro', 'type' => 'text'],
        ['handle' => 'rating', 'type' => 'number'],
    ]]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}']);
});

/** An entry live in English and Indonesian; the rating (shared) changed after it was translated. */
function bilingualPost(): EntryTranslation
{
    $en = app(CreateEntry::class)->handle(test()->blog, ['title' => 'Hello', 'data' => ['intro' => 'Hi', 'rating' => 3]]);
    $id = app(CreateTranslation::class)->handle($en->entry, 'id');
    app(SaveDraft::class)->handle($id, ['title' => 'Halo', 'slug' => 'halo', 'data' => ['intro' => 'Hai']]);
    app(SaveDraft::class)->handle($en->fresh() ?? $en, ['data' => ['rating' => 5]]);
    app(PublishEntry::class)->handle($en->fresh() ?? $en);
    app(PublishEntry::class)->handle($id->fresh() ?? $id);

    return $en;
}

it('moves the shared fields and redirects the addresses that change', function () {
    $en = bilingualPost();
    Blueprint::query()->create(['handle' => 'contact', 'title' => 'Contact', 'fields' => [['handle' => 'phone', 'type' => 'text', 'translatable' => false], ['handle' => 'address', 'type' => 'text']]]);
    $contact = GlobalSet::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'contact']);
    app(SaveGlobals::class)->handle($contact, ['phone' => '555', 'address' => 'Main St']);
    Redirect::query()->create(['from_path' => '/blog/old-hello', 'to_url' => '/blog/hello', 'status' => 301]);

    artisan('sunrice:switch-main-language', ['locale' => 'id', '--force' => true])->assertSuccessful()
        ->expectsOutputToContain('SUNRICE_LOCALES=id,en');

    $id = $en->entry->fresh()?->translation('id');
    expect($id?->data['rating'])->toBe(5)->and($id?->draft_data['rating'])->toBe(5)->and($id?->data['intro'])->toBe('Hai')
        ->and($contact->fresh()?->values->firstWhere('locale', 'id')?->data)->toBe(['phone' => '555', 'address' => 'Main St'])
        ->and(Redirect::query()->pluck('to_url', 'from_path')->all())->toBe(['/blog/old-hello' => '/en/blog/hello', '/blog/hello' => '/en/blog/hello', '/id/blog/halo' => '/blog/halo']);

    config(['sunrice-cms.locales' => ['id', 'en']]);
    get('/blog/halo')->assertOk()->assertSee('Halo');
    get('/id/blog/halo')->assertRedirect('/blog/halo');
    get('/blog/hello')->assertRedirect('/en/blog/hello');
    get('/en/blog/hello')->assertOk()->assertSee('Hello');
});

it('stops at content without the language, unless told to copy it', function () {
    $en = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->blog, ['title' => 'Only English', 'data' => ['rating' => 2]]));
    $news = app(SaveTerm::class)->handle(Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']), ['title' => 'News']);

    artisan('sunrice:switch-main-language', ['locale' => 'id', '--force' => true])->assertFailed()
        ->expectsOutputToContain('Only English')->expectsOutputToContain('2 entries and terms have no id version');
    artisan('sunrice:switch-main-language', ['locale' => 'id', '--dry-run' => true, '--copy-missing' => true])->assertSuccessful();
    expect($en->entry->fresh()?->translation('id'))->toBeNull();

    artisan('sunrice:switch-main-language', ['locale' => 'id', '--force' => true, '--copy-missing' => true])->assertSuccessful();
    $copy = $en->entry->fresh()?->translation('id');
    expect($copy?->title)->toBe('Only English')->and($copy?->slug)->toBe('only-english')->and($copy?->isLive())->toBeTrue()
        ->and($copy?->data['rating'])->toBe(2)
        ->and($news->fresh()?->translations->firstWhere('locale', 'id')?->title)->toBe('News');
});

it('refuses a language the site lacks, or the main one', function () {
    artisan('sunrice:switch-main-language', ['locale' => 'fr'])->assertFailed()->expectsOutputToContain('isn\'t one of the site\'s languages');
    artisan('sunrice:switch-main-language', ['locale' => 'en'])->assertFailed()->expectsOutputToContain('main language already');
});

it('keeps no cached page across a change of languages', function () {
    $cache = app(SiteCache::class);
    $before = $cache->key('page');

    config(['sunrice-cms.locales' => ['id', 'en']]);

    expect($cache->key('page'))->not->toBe($before);
});
