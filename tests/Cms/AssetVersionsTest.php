<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Sunrice\Admin\Assets\AdminAssets;
use Sunrice\Admin\Panel;
use Sunrice\Cms\CmsPlugin;

use function Pest\Laravel\artisan;

/* The built assets' versions: from the files the browser gets, the same on every page. */

it('versions the CMS bundle by the copy served, its build until it\'s published', function () {
    $public = sys_get_temp_dir().'/sunrice-public-'.uniqid();
    mkdir($public);
    app()->usePublicPath($public);
    $panel = usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    $bundle = $panel->getBundles()['cms'] ?? null;
    $dist = dirname(__DIR__, 2).'/packages/cms/dist';

    expect($bundle?->publishedPath())->toBe($public.'/'.CmsPlugin::ASSETS)
        ->and($bundle?->getModule())->toBe(asset(CmsPlugin::ASSETS.'/admin.js').'?v='.substr((string) md5_file($dist.'/admin.js'), 0, 12))
        ->and($bundle?->getStyles())->toBe([asset(CmsPlugin::ASSETS.'/admin.css').'?v='.substr((string) md5_file($dist.'/admin.css'), 0, 12)]);

    // sunrice:assets publishes it; then the published copy decides.
    artisan('sunrice:assets')->expectsOutputToContain('Published Sunrice CMS to public/vendor/sunrice-cms.')->assertSuccessful();
    file_put_contents($public.'/'.CmsPlugin::ASSETS.'/admin.js', 'export {};');
    app()->forgetScopedInstances();
    expect($bundle?->getModule())->toBe(asset(CmsPlugin::ASSETS.'/admin.js').'?v='.substr(md5('export {};'), 0, 12));

    (new Filesystem)->deleteDirectory($public);
});

it('hashes the panel\'s manifest', function () {
    $manifest = AdminAssets::manifestPath();

    expect($manifest)->not->toBeNull()
        ->and(AdminAssets::version())->toBe(md5_file((string) $manifest))->toBe(AdminAssets::version());
});
