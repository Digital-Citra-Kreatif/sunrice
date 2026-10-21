<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;

use function Pest\Laravel\get;

/*
 * The docs' tables of what templates get name every variable the CMS
 * gives entry and listing views, so a template author can rely on them.
 */

beforeEach(fn () => usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make())));

/** The variables the CMS gave the first view matching `$pattern`, while requesting `$url`. */
function viewVariables(string $pattern, string $url): array
{
    $seen = [];
    View::composer($pattern, function ($view) use (&$seen): void {
        $seen = $seen ?: array_keys(array_diff_key($view->getData(), ['__env' => 1, 'app' => 1, 'errors' => 1]));
    });
    get($url)->assertOk();

    return $seen;
}

/** The variables a docs section's "Views get"-style table names. */
function documentedVariables(string $file, string $after): string
{
    $docs = (string) file_get_contents(dirname(__DIR__, 2)."/packages/cms/docs/{$file}");

    return substr($docs, (int) strpos($docs, $after), 4000);
}

it('documents every variable entry and listing views get', function () {
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [['handle' => 'intro', 'type' => 'text']]]);
    $blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}', 'listing_route' => '/blog']);
    app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($blog, ['title' => 'Hello', 'intro' => 'Hi']));

    $entry = documentedVariables('site.md', 'Views get:');
    foreach (viewVariables('sunrice-cms::entry', '/blog/hello') as $variable) {
        expect($entry)->toContain("`\${$variable}`");
    }
    $listing = documentedVariables('site.md', '## Listing pages');
    foreach (viewVariables('sunrice-cms::listing', '/blog') as $variable) {
        expect($listing)->toContain("`\${$variable}`");
    }
});
