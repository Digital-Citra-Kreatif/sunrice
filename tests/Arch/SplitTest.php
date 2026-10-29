<?php

declare(strict_types=1);

/*
 * Every Composer package in packages/ is published to its own read-only
 * repository by .github/workflows/split.yml, and the packages require each
 * other at `self.version`, since they're tagged together.
 * .github/workflows/release.yml makes a tag from the Actions tab and hands
 * it to split.yml, because a tag pushed by a workflow starts no workflows.
 * Each package also carries the repository's MIT license, which its read-only
 * copy and Packagist show.
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

it('publishes the tag the release workflow makes', function () {
    $root = dirname(__DIR__, 2).'/.github/workflows';
    $release = (string) file_get_contents("{$root}/release.yml");
    $split = (string) file_get_contents("{$root}/split.yml");

    expect($release)->toContain('workflow_dispatch:')
        ->toContain('git push origin "refs/tags/${tag}"')
        ->toContain('uses: ./.github/workflows/split.yml')
        ->toContain('tag: ${{ needs.tag.outputs.tag }}')
        ->toContain('secrets: inherit')
        ->and($split)->toContain("workflow_call:\n    inputs:\n      tag:")
        ->toContain('ref: ${{ inputs.tag }}')
        ->toContain('git push "$remote" "split:refs/tags/${TAG}"');
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

it('ships the MIT license with every package', function () {
    $root = dirname(__DIR__, 2);
    $license = (string) file_get_contents("{$root}/LICENSE");
    $missing = [];
    foreach (glob("{$root}/packages/*", GLOB_ONLYDIR) ?: [] as $package) {
        if (! is_file("{$package}/LICENSE") || file_get_contents("{$package}/LICENSE") !== $license) {
            $missing[] = basename($package);
        }
    }

    expect($license)->toStartWith("MIT License\n\nCopyright (c) 2026 Digital Citra Kreatif")
        ->and($missing)->toBe([]);
});
