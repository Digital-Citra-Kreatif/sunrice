<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Entries\TrashEntry;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Actions\Terms\TrashTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\Term;
use Sunrice\Cms\Site\Sitemap;
use Sunrice\Cms\Support\Seo;
use Sunrice\Cms\Support\SiteCache;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['sunrice-cms.locales' => ['en', 'id', 'zh']]);
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [['handle' => 'intro', 'type' => 'text']]]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}']);
    $this->tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags', 'route' => '/tags/{slug}']);
});

/** A live post in English and, published or not, in other languages (slug: title-locale). */
function mapPost(string $title, array $languages = [], array $input = []): EntryTranslation
{
    $main = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle(test()->blog, ['title' => $title, ...$input]));
    foreach ($languages as $locale => $publish) {
        $translation = app(SaveDraft::class)->handle(app(CreateTranslation::class)->handle($main->entry, $locale), ['title' => "{$title} {$locale}", 'slug' => str($title)->slug()."-{$locale}"]);
        if ($publish) {
            app(PublishEntry::class)->handle($translation);
        }
    }

    return $main->fresh() ?? $main;
}

function mapTerm(string $title, array $languages = []): Term
{
    $term = app(SaveTerm::class)->handle(test()->tags, ['title' => $title]);
    foreach ($languages as $locale) {
        app(SaveTerm::class)->handle(test()->tags, ['title' => "{$title} {$locale}", 'locale' => $locale], $term);
    }

    return $term;
}

/** The sitemap's XML, built afresh, and the queries building it ran. */
function freshSitemap(): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $xml = (string) (new Sitemap(app(SiteCache::class)))->part(1);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    return [$xml, $queries];
}

it('builds the sitemap in a number of queries that doesn\'t grow with the site', function () {
    $site = function (int $from, int $to): void {
        foreach (range($from, $to) as $i) {
            mapPost("Post {$i}", ['id' => true, 'zh' => $i % 2 === 0]);
            mapTerm("Tag {$i}", ['id']);
        }
    };
    // The same statements, past the site's routes read once (and kept) on the first build after a change.
    $statements = fn (array $queries): array => array_map(fn (string $sql): string => (string) preg_replace('/ in \([\d, ]+\)/', ' in (…)', $sql), array_column($queries, 'query'));
    $site(1, 2);
    freshSitemap();
    [$small, $few] = freshSitemap();
    $site(3, 30);
    freshSitemap();
    [$large, $many] = freshSitemap();

    expect(substr_count($small, '<url>'))->toBe(2 + 2 + 1 + 2 + 2)
        ->and(substr_count($large, '<url>'))->toBe(30 + 30 + 15 + 30 + 30)
        ->and($statements($many))->toBe($statements($few))
        ->and(count($many))->toBeLessThan(15);
});

it('reads only the columns the sitemap needs: no drafts, fields or search text', function () {
    mapPost('Hello', ['id' => true]);
    mapTerm('News', ['id']);

    [, $queries] = freshSitemap();
    $reads = array_filter(array_map(fn (string $sql): string => str_replace(['"', '`'], '', $sql), array_column($queries, 'query')),
        fn (string $sql): bool => preg_match('/^select .* from sunrice_(entry|term)_translations /', $sql) === 1);

    expect($reads)->not->toBeEmpty();
    foreach ($reads as $sql) {
        // Their fields only to read "noindex", in SQL.
        expect($sql)->not->toStartWith('select *')->not->toContain('_translations.*')->not->toContain('draft_data')->not->toContain('search_text')
            ->and(substr_count($sql, '_translations.data'))->toBe(substr_count($sql, ' as sitemap_noindex'));
    }
});

it('lists every live language of a page with its alternates, in three languages', function () {
    $hello = mapPost('Hello', ['id' => true, 'zh' => true]);
    mapPost('Draft', ['id' => true, 'zh' => false]);

    [$xml] = freshSitemap();
    $links = '<xhtml:link rel="alternate" hreflang="en" href="'.url('/blog/hello').'"/>'
        .'<xhtml:link rel="alternate" hreflang="id" href="'.url('/id/blog/hello-id').'"/>'
        .'<xhtml:link rel="alternate" hreflang="zh" href="'.url('/zh/blog/hello-zh').'"/>'
        .'<xhtml:link rel="alternate" hreflang="x-default" href="'.url('/blog/hello').'"/>';
    $modified = fn (string $locale): string => (string) $hello->entry->translation($locale)?->liveUpdatedAt()?->toAtomString();

    expect($xml)->toContain('<url><loc>'.url('/blog/hello').'</loc><lastmod>'.$modified('en').'</lastmod>'.$links.'</url>')
        ->toContain('<url><loc>'.url('/id/blog/hello-id').'</loc><lastmod>'.$modified('id').'</lastmod>'.$links.'</url>')
        ->toContain('<url><loc>'.url('/zh/blog/hello-zh').'</loc><lastmod>'.$modified('zh').'</lastmod>'.$links.'</url>')
        // A language not live leaves the alternates, and the sitemap.
        ->toContain('<url><loc>'.url('/blog/draft').'</loc>')
        ->toContain('hreflang="id" href="'.url('/id/blog/draft-id').'"/><xhtml:link rel="alternate" hreflang="x-default" href="'.url('/blog/draft').'"/></url>')
        ->not->toContain('draft-zh');
    expect(simplexml_load_string($xml))->not->toBeFalse();
});

it('leaves out pages that ask not to be indexed, but keeps them in their other languages\' alternates', function () {
    $hidden = mapPost('Hidden', ['id' => true]);
    EntryTranslation::query()->whereKey($hidden->id)->update(['data' => json_encode(['intro' => 'Hi', 'seo' => ['noindex' => true]])]);
    mapPost('Gone', [], ['seo' => ['noindex' => true]]);
    mapPost('Shown', [], ['seo' => ['noindex' => false, 'title' => 'Shown']]);
    $tag = mapTerm('Secret', ['id']);
    $tag->translations()->where('locale', 'en')->update(['data' => json_encode(['seo' => ['noindex' => true]])]);
    // Only true itself hides a page, as Seo::noindex() reads it.
    $odd = mapPost('Odd');
    EntryTranslation::query()->whereKey($odd->id)->update(['data' => json_encode(['seo' => ['noindex' => 1], 'other' => ['noindex' => true]])]);

    [$xml] = freshSitemap();

    expect($xml)->not->toContain('<loc>'.url('/blog/hidden').'</loc>')
        ->toContain('<loc>'.url('/id/blog/hidden-id').'</loc>')
        ->toContain('hreflang="en" href="'.url('/blog/hidden').'"')
        ->not->toContain('<loc>'.url('/blog/gone').'</loc>')
        ->toContain('<loc>'.url('/blog/shown').'</loc>')
        ->toContain('<loc>'.url('/blog/odd').'</loc>')
        ->not->toContain('<loc>'.url('/tags/secret').'</loc>')
        ->toContain('<loc>'.url('/id/tags/secret-id').'</loc>');
    // As the page itself reads it.
    expect(Seo::noindex(EntryTranslation::query()->findOrFail($hidden->id)))->toBeTrue()
        ->and(Seo::noindex(EntryTranslation::query()->findOrFail($odd->id)))->toBeFalse();
});

it('leaves out drafts, scheduled and trashed pages, and pages standing in for a language', function () {
    mapPost('Live');
    app(CreateEntry::class)->handle($this->blog, ['title' => 'Unpublished']);
    app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->blog, ['title' => 'Later']), now()->addDay());
    app(TrashEntry::class)->handle(mapPost('Binned')->entry);
    $gone = Collection::query()->create(['handle' => 'gone', 'title' => 'Gone', 'blueprint' => 'post', 'route' => '/gone/{slug}']);
    app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($gone, ['title' => 'Away']));
    $gone->delete();
    app(TrashTerm::class)->handle(mapTerm('Kept'));
    mapTerm('Shown');

    foreach ([true, false] as $fallback) {
        config(['sunrice-cms.site.fallback' => $fallback]);
        [$xml] = freshSitemap();

        expect($xml)->toContain('<loc>'.url('/blog/live').'</loc>')
            ->toContain('<loc>'.url('/tags/shown').'</loc>')
            ->not->toContain('/id/')->not->toContain('/zh/')
            ->not->toContain('unpublished')->not->toContain('later')->not->toContain('binned')->not->toContain('away')->not->toContain('kept')
            ->not->toContain('xhtml:link');
    }
});

it('gives pages whose address takes another entry\'s slug their fields', function () {
    Blueprint::query()->create(['handle' => 'unit', 'title' => 'Unit', 'fields' => [['handle' => 'project', 'type' => 'entries', 'config' => ['collections' => ['blog']]]]]);
    $units = Collection::query()->create(['handle' => 'units', 'title' => 'Units', 'blueprint' => 'unit', 'route' => '/units/{project}/{slug}']);
    $tower = mapPost('Tower', ['id' => true]);
    $unit = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($units, ['title' => 'Unit 4', 'data' => ['project' => $tower->entry_id]]));
    app(PublishEntry::class)->handle(app(SaveDraft::class)->handle(app(CreateTranslation::class)->handle($unit->entry, 'id'), ['title' => 'Unit 4', 'slug' => 'unit-4']));

    [$xml] = freshSitemap();

    expect($xml)->toContain('<loc>'.url('/units/tower/unit-4').'</loc>')
        ->toContain('<loc>'.url('/id/units/tower-id/unit-4').'</loc>')
        ->toContain('hreflang="id" href="'.url('/id/units/tower-id/unit-4').'"');
});
