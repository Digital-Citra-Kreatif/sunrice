<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Redirect;
use Sunrice\Cms\Site\Site;
use Sunrice\Cms\Structure\StructureExporter;
use Sunrice\Cms\Structure\StructureImporter;
use Sunrice\Cms\Structure\StructureImportFailed;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/*
 * Wildcard redirects: a From ending in /* takes every address under it,
 * and a * in To is the rest of the address.
 */
beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
});

function wildcard(string $from, string $to, array $more = []): Redirect
{
    return Redirect::query()->create(['from_path' => $from, 'to_url' => $to, 'status' => 301, ...$more]);
}

it('sends every address under a wildcard on, the rest of it where the * is, query string kept', function () {
    wildcard('/blog/*', '/journal/*');
    wildcard('/old-shop/*', '/products', ['status' => 302]);

    get('/blog/2024/hello?utm=x')->assertStatus(301)->assertRedirect('/journal/2024/hello?utm=x');
    get('/blog/hello')->assertRedirect('/journal/hello');
    get('/old-shop/kettles/blue')->assertStatus(302)->assertRedirect('/products');
    // Only what's under it: not the path itself, nor one that merely starts alike.
    get('/blog')->assertNotFound();
    get('/blogger/x')->assertNotFound();
    expect(app(Site::class)->redirect('/blog/a/b')?->targetFor('/blog/a/b'))->toBe('/journal/a/b');
});

it('prefers an exact redirect, then the longest wildcard', function () {
    wildcard('/blog/*', '/journal/*');
    wildcard('/blog/2024/*', '/archive/2024/*');
    wildcard('/*', '/');
    Redirect::query()->create(['from_path' => '/blog/2024/special', 'to_url' => '/special']);

    get('/blog/2024/special')->assertRedirect('/special');
    get('/blog/2024/hello')->assertRedirect('/archive/2024/hello');
    get('/blog/2023/hello')->assertRedirect('/journal/2023/hello');
    get('/anything/else')->assertRedirect('/');
});

it('never lets a live page or a language\'s own address lose to a wildcard', function () {
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => []]);
    $blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}']);
    app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($blog, ['title' => 'Hello']));
    wildcard('/blog/*', '/journal/*');

    get('/blog/hello')->assertOk();
    get('/blog/gone')->assertRedirect('/journal/gone');
});

it('applies a wildcard in every language when asked, to that language\'s address', function () {
    wildcard('/blog/*', '/journal/*', ['every_language' => true]);
    wildcard('/news/*', '/journal/*');

    get('/id/blog/2024/halo')->assertRedirect('/id/journal/2024/halo');
    get('/blog/2024/hello')->assertRedirect('/journal/2024/hello');
    get('/id/news/x')->assertNotFound(); // the main language's only
    // One for this language's paths before one for every language, at the same length.
    wildcard('/id/blog/*', '/id/arsip/*');
    get('/id/blog/x')->assertRedirect('/id/arsip/x');
});

it('never sends visitors to another host through the rest of the address', function () {
    wildcard('/old/*', '/*');

    get('/old//evil.example/x')->assertRedirect('/evil.example/x');
    expect(Redirect::query()->first()?->targetFor('/old/\\evil.example'))->toBe('/evil.example');
});

it('checks wildcards in the form, and shows them in the list', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.redirects.view', 'cms.redirects.create'));
    $add = fn (string $from, string $to) => post('/admin/cms/redirects', ['from_path' => $from, 'to_url' => $to, 'status' => '301']);

    $add('/blog/*', '/journal/*')->assertSessionHasNoErrors();
    $add('/old-shop/*', 'https://shop.example/*')->assertSessionHasNoErrors();
    $add('/a/*/b', '/x')->assertSessionHasErrors('from_path');
    $add('/a*', '/x')->assertSessionHasErrors('from_path');
    $add('/a/**', '/x')->assertSessionHasErrors('from_path');
    $add('/c/*', '/x/*/*')->assertSessionHasErrors(['to_url' => 'Use * at most once.']);
    $add('/plain', '/x/*')->assertSessionHasErrors(['to_url' => 'A * here needs a From ending in /*, whose rest it stands for.']);
    $add('/d/*', '/d/archive/*')->assertSessionHasErrors(['to_url' => 'This would send visitors round in a loop: From takes this address too.']);
    $add('/e', '/e')->assertSessionHasErrors('to_url');
    $add('/f/*', '/f')->assertSessionHasNoErrors(); // the path itself isn't under it

    get('/admin/cms/redirects/create')->assertInertia(fn (Assert $page) => $page->where('sections.0.fields.0.help', fn (string $help) => str_contains($help, '/blog/*')));
    get('/admin/cms/redirects?sort=from_path')->assertInertia(fn (Assert $page) => $page
        ->where('columns.1.label', 'Match')
        ->where('rows.data.0.cells.from_path', '/blog/*')->where('rows.data.0.cells.match', 'Everything under it')
        ->where('rows.data.2.cells.from_path', '/old-shop/*'));
    Redirect::query()->create(['from_path' => '/aaa', 'to_url' => '/b']);
    get('/admin/cms/redirects?sort=from_path')->assertInertia(fn (Assert $page) => $page->where('rows.data.0.cells.match', 'This address'));
});

it('reads wildcards from the cache, and forgets them when a redirect changes', function () {
    config(['sunrice-cms.cache.enabled' => true]);
    $redirect = wildcard('/blog/*', '/journal/*');
    get('/blog/x')->assertRedirect('/journal/x');

    DB::enableQueryLog();
    get('/blog/y')->assertRedirect('/journal/y');
    expect(collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql) => str_contains($sql, 'like'))->all())->toBe([]);

    $redirect->update(['to_url' => '/archive/*']);
    get('/blog/x')->assertRedirect('/archive/x');
    wildcard('/blog/x/*', '/deep/*');
    get('/blog/x/1')->assertRedirect('/deep/1');
    $redirect->delete();
    get('/blog/z')->assertNotFound();
});

it('exports and imports wildcards, checking them', function () {
    wildcard('/blog/*', '/journal/*', ['every_language' => true]);
    $exported = app(StructureExporter::class)->export();
    expect($exported['redirects'])->toBe([['from' => '/blog/*', 'to' => '/journal/*', 'status' => 301, 'every_language' => true]]);

    Redirect::query()->delete();
    app(StructureImporter::class)->import($exported);
    get('/id/blog/a')->assertRedirect('/id/journal/a');

    $exported['redirects'] = [['from' => '/x', 'to' => '/y/*']];
    expect(fn () => app(StructureImporter::class)->import($exported))->toThrow(StructureImportFailed::class);
});
