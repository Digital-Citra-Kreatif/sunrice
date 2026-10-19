<?php

declare(strict_types=1);

/*
 * Plugins (the CMS included) may only use panel classes marked @api:
 * everything else is internal to the panel and may change. If the CMS
 * needs something internal, make it a deliberate, documented API.
 */

it('lets the CMS use only the panel\'s public API', function () {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/packages/cms/src'));
    $offenders = [];

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        preg_match_all('/^use\s+(Sunrice\\\\Admin\\\\[A-Za-z0-9_\\\\]+)/m', (string) file_get_contents($file->getPathname()), $matches);
        foreach ($matches[1] as $class) {
            $doc = (new ReflectionClass($class))->getDocComment();
            if ($doc === false || ! str_contains($doc, '@api')) {
                $offenders[] = basename($file->getPathname()).' uses '.$class;
            }
        }
    }

    expect($offenders)->toBe([]);
});
