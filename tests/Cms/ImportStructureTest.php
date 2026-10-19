<?php

declare(strict_types=1);

use Spatie\Permission\Models\Permission;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Structure\ImportStructure;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Menus;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Form;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Support\ImportReport;

use function Pest\Laravel\artisan;
use function Pest\Laravel\get;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
});

function siteDefinition(): array
{
    return [
        'fieldsets' => [['handle' => 'hero', 'title' => 'Hero', 'fields' => [['handle' => 'headline', 'type' => 'text']]]],
        'blueprints' => [['handle' => 'post', 'title' => 'Post', 'fields' => [['handle' => 'intro', 'type' => 'text'], ['handle' => 'hero', 'type' => 'fieldset', 'config' => ['fieldset' => 'hero']]]]],
        'collections' => [['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}', 'listing_route' => '/blog', 'dated' => true]],
        'taxonomies' => [['handle' => 'tags', 'title' => 'Tags', 'collections' => ['blog'], 'route' => '/tags/{slug}']],
        'globals' => [['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'post']],
        'forms' => [['handle' => 'contact', 'title' => 'Contact us', 'blueprint' => 'post', 'notify' => ['team@example.com']]],
        'entries' => [['collection' => 'blog', 'slug' => 'hello', 'title' => 'Hello', 'data' => ['intro' => 'Hi'], 'publish' => true]],
        'menus' => [['handle' => 'main', 'title' => 'Main', 'items' => [
            ['entry' => 'blog/hello', 'children' => [['url' => 'https://example.com', 'labels' => ['en' => 'Example']]]],
            ['listing' => 'blog', 'labels' => ['en' => 'Blog']],
        ]]],
    ];
}

it('makes what the definition describes', function () {
    $report = app(ImportStructure::class)->handle(siteDefinition());

    expect(array_column($report->items(), 'status'))->each->toBe(ImportReport::CREATED)
        ->and(Collection::query()->where('handle', 'blog')->first()?->only('blueprint', 'route', 'dated'))->toBe(['blueprint' => 'post', 'route' => '/blog/{slug}', 'dated' => true])
        ->and(Taxonomy::query()->where('handle', 'tags')->first()?->collections()->pluck('handle')->all())->toBe(['blog'])
        ->and(Form::query()->where('handle', 'contact')->first()?->notify)->toBe(['team@example.com'])
        ->and(Permission::query()->where('name', 'cms.entries.blog.view')->exists())->toBeTrue()
        ->and(collect(app(Menus::class)->get('main'))->pluck('label')->all())->toBe(['Hello', 'Blog']);
    get('/blog/hello')->assertOk()->assertSee('Hi');
});

it('leaves what matches, reports what differs, and overwrites it when forced', function () {
    app(ImportStructure::class)->handle(siteDefinition());
    Collection::query()->where('handle', 'blog')->update(['title' => 'Journal']);

    $again = app(ImportStructure::class)->handle(siteDefinition());
    expect($again->status('collection', 'blog'))->toBe(ImportReport::CHANGED)
        ->and($again->items(ImportReport::CHANGED)[0]['differences'])->toBe(['title'])
        ->and($again->status('blueprint', 'post'))->toBe(ImportReport::UNCHANGED)
        ->and($again->status('taxonomy', 'tags'))->toBe(ImportReport::UNCHANGED)
        ->and($again->status('form', 'contact'))->toBe(ImportReport::UNCHANGED)
        ->and($again->status('entry', 'blog/hello'))->toBe(ImportReport::UNCHANGED)
        ->and(Collection::query()->where('handle', 'blog')->value('title'))->toBe('Journal');

    expect(app(ImportStructure::class)->handle(siteDefinition(), force: true)->status('collection', 'blog'))->toBe(ImportReport::UPDATED)
        ->and(Collection::query()->where('handle', 'blog')->value('title'))->toBe('Blog')
        ->and(EntryTranslation::query()->count())->toBe(1);
});

it('saves nothing in a dry run, or when an item is wrong', function () {
    $report = app(ImportStructure::class)->handle(siteDefinition(), dryRun: true);
    expect($report->dryRun)->toBeTrue()->and(Blueprint::query()->count())->toBe(0)->and(Collection::query()->count())->toBe(0);

    $broken = siteDefinition();
    $broken['taxonomies'][0]['collections'] = ['nope'];
    expect(fn () => app(ImportStructure::class)->handle($broken))->toThrow(InvalidArgumentException::class, 'taxonomies.tags: No collection "nope".');
    expect(Blueprint::query()->count())->toBe(0);

    $broken = siteDefinition();
    $broken['collections'][0]['route'] = 'no slug';
    expect(fn () => app(ImportStructure::class)->handle($broken))->toThrow(InvalidArgumentException::class, 'collections.blog:');
    expect(fn () => app(ImportStructure::class)->handle(['pages' => []]))->toThrow(InvalidArgumentException::class, 'Unknown sections: pages');
});

it('imports from a file', function () {
    $file = sys_get_temp_dir().'/structure-'.uniqid().'.json';
    file_put_contents($file, json_encode(['blueprints' => siteDefinition()['blueprints'], 'fieldsets' => siteDefinition()['fieldsets']]));

    artisan('sunrice:import-structure', ['file' => $file])->assertSuccessful()->expectsOutputToContain('created');
    artisan('sunrice:import-structure', ['file' => $file.'.missing'])->assertFailed();
    expect(Blueprint::query()->where('handle', 'post')->exists())->toBeTrue();
    unlink($file);
});
