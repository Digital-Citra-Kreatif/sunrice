<?php

declare(strict_types=1);

/*
 * Every Composer package in packages/ is published to its own read-only
 * repository by .github/workflows/split.yml, and the packages require each
 * other at `self.version`, since they're tagged together.
 */

/** @return array<string, string> package folder => its composer.json name */
function composerPackages(): array
{
    $packages = [];
    foreach (glob(dirname(__DIR__, 2).'/packages/*/composer.json') ?: [] as $file) {
        $decoded = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        $packages[basename(dirname($file))] = is_array($decoded) && is_string($decoded['name'] ?? null) ? $decoded['name'] : '';
    }
    ksort($packages);

    return $packages;
}

it('publishes every Composer package to its own repository', function () {
    $workflow = (string) file_get_contents(dirname(__DIR__, 2).'/.github/workflows/split.yml');
    preg_match_all('/- package: (\S+)/', $workflow, $matches);
    $published = $matches[1];
    sort($published);

    expect($published)->toBe(array_keys(composerPackages()));
});

it('requires the other packages at the same version', function () {
    $names = composerPackages();
    $loose = [];
    foreach (array_keys($names) as $folder) {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2)."/packages/{$folder}/composer.json"), true, flags: JSON_THROW_ON_ERROR);
        $require = is_array($composer) && is_array($composer['require'] ?? null) ? $composer['require'] : [];
        foreach ($require as $name => $constraint) {
            if (in_array($name, $names, true) && $constraint !== 'self.version') {
                $loose[] = "{$folder}: {$name} {$constraint}";
            }
        }
    }

    expect($loose)->toBe([]);
});
