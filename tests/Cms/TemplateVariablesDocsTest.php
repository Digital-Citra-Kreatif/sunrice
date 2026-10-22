<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Site\Site;

use function Pest\Laravel\get;

/*
 * The docs' tables of what templates get (site.md, "What templates get")
 * name exactly the variables the CMS gives each kind of page's view: every
 * variable a view gets is documented, and every documented one reaches it.
 */

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [
        ['handle' => 'intro', 'type' => 'text'],
        ['handle' => 'tags', 'type' => 'terms', 'config' => ['taxonomy' => 'tags', 'multiple' => true]],
    ]]);
    $blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}', 'listing_route' => '/blog']);
    $tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags', 'route' => '/tags/{slug}', 'index_route' => '/tags']);
    $tags->collections()->attach($blog->id);
    $news = app(SaveTerm::class)->handle($tags, ['title' => 'News']);
    app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($blog, ['title' => 'Hello', 'data' => ['intro' => 'Hi', 'tags' => [$news->id]]]));
    app()->forgetInstance(Site::class);
});

/**
 * The variables the CMS gave the view rendering `$url`.
 *
 * @return list<string>
 */
function viewVariables(string $url): array
{
    $seen = null;
    View::composer('*', function ($view) use (&$seen): void {
        // The page's own view comes first; its layout and partials after it.
        $seen ??= array_keys(array_diff_key($view->getData(), ['__env' => 1, 'app' => 1, 'errors' => 1]));
    });
    get($url)->assertOk();
    sort($seen);

    return $seen;
}

/**
 * The variables a "… get" table of site.md names, in its first column.
 *
 * @return list<string>
 */
function documentedVariables(string $heading): array
{
    $docs = (string) file_get_contents(dirname(__DIR__, 2).'/packages/cms/docs/site.md');
    $start = strpos($docs, "\n#### {$heading}\n");
    expect($start)->not->toBeFalse();
    $section = substr($docs, (int) $start + 1);
    $section = substr($section, 0, (int) (strpos($section, "\n#", 1) ?: strlen($section)));
    preg_match_all('/^\| ([^|]+) \|/m', $section, $rows);
    preg_match_all('/`\$([a-zA-Z]+)`/', implode(' ', $rows[1]), $names);
    $names = array_values(array_unique($names[1]));
    sort($names);

    return $names;
}

it('documents exactly the variables each kind of page gets', function (string $heading, array $urls) {
    foreach ($urls as $url) {
        expect(viewVariables($url))->toBe(documentedVariables($heading));
    }
})->with([
    'entries' => ['Entry pages get', ['/blog/hello']],
    'listing pages' => ['Listing pages get', ['/blog']],
    'term pages' => ['Term pages get', ['/tags/news', '/blog/tags/news']],
    'taxonomy index pages' => ['Taxonomy index pages get', ['/tags', '/blog/tags']],
    'the search page' => ['The search page gets', ['/search']],
]);

it('gives every page the page shown, its title and language', function () {
    foreach (['/blog/hello', '/blog', '/tags/news', '/blog/tags/news', '/tags', '/blog/tags', '/search'] as $url) {
        expect(viewVariables($url))->toContain('page', 'title', 'locale');
    }
});
