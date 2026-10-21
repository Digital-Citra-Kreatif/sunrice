<?php

declare(strict_types=1);

/*
 * The package boundaries. The panel must work in apps without the CMS,
 * so it may never know the CMS exists; the CMS reaches the panel only
 * through what a third-party plugin could use too.
 */

arch('the panel never uses the CMS')
    ->expect('Sunrice\Admin')
    ->not->toUse('Sunrice\Cms');

arch('every file declares strict types')
    ->expect(['Sunrice\Admin', 'Sunrice\Cms'])
    ->toUseStrictTypes();

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'ddd', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();

/*
 * spatie/laravel-responsecache is optional: the classes built on it
 * (they extend or implement its types) are only named for its config,
 * never called, so nothing loads them in an app without the package.
 */
test('the classes built on the optional responsecache are never called', function () {
    $built = ['PageCacheHasher', 'PageCacheProfile', 'PageCacheSerializer', 'HoneypotReplacer'];
    $calls = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/packages/cms/src')) as $file) {
        if ($file->getExtension() !== 'php' || in_array($file->getBasename('.php'), $built, true)) {
            continue;
        }
        if (preg_match_all('/\b('.implode('|', $built).')::(?!class\b)\w+/', (string) file_get_contents($file->getPathname()), $found) > 0) {
            $calls[] = $file->getBasename().': '.implode(', ', array_unique($found[0]));
        }
    }

    expect($calls)->toBe([]);
});
