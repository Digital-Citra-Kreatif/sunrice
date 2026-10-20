<?php

declare(strict_types=1);

use Sunrice\Admin\Assets\AdminAssets;
use Sunrice\Admin\Panel;
use Sunrice\Cms\CmsPlugin;

/* The built assets' versions: read from the files once per process, the same on every page. */

it('versions the CMS bundle by its built files, once', function () {
    $panel = usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    $bundle = $panel->getBundles()['cms'] ?? null;
    $stat = stat(dirname(__DIR__, 2).'/packages/cms/dist/admin.js');

    expect($bundle?->getModule())->toBe(asset(CmsPlugin::ASSETS.'/admin.js').'?v='.substr(md5($stat['mtime'].$stat['size']), 0, 12))
        ->and($bundle?->getStyles())->toHaveCount(1)
        ->and($bundle?->getStyles()[0])->toMatch('/\/admin\.css\?v=[0-9a-f]{12}$/');
});

it('hashes the panel\'s manifest once per process', function () {
    $manifest = AdminAssets::manifestPath();

    expect($manifest)->not->toBeNull()
        ->and(AdminAssets::version())->toBe(md5_file((string) $manifest))->toBe(AdminAssets::version());
});
