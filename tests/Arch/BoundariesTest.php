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
