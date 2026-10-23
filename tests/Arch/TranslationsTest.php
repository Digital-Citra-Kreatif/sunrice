<?php

declare(strict_types=1);

/*
 * The panel speaks English and Indonesian: every word the panel and the
 * CMS show, written in English in their code, has an Indonesian line in
 * packages/{admin,cms}/lang/id.json (the same text where English reads
 * better). It finds the words in t('…') and __('…') calls, and in the
 * definitions said through Say (labels, help and its links and developer notes, change warnings, section and card titles,
 * permission labels and short labels, navigation groups, flash messages).
 */

/** @return list<string> files under the directories with these extensions */
function translatableFiles(array $directories, array $extensions): array
{
    $files = [];
    foreach ($directories as $directory) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof SplFileInfo && in_array($file->getExtension(), $extensions, true)) {
                $files[] = $file->getPathname();
            }
        }
    }
    sort($files);

    return $files;
}

/** A PHP single-quoted string literal's text. */
function phpLiteral(string $raw): string
{
    return str_replace(["\\'", '\\\\'], ["'", '\\'], $raw);
}

/** @return array<string, list<string>> the words found, with the files that use them */
function panelWords(): array
{
    $root = dirname(__DIR__, 2);
    $words = [];
    $add = function (string $word, string $file) use (&$words, $root): void {
        // Words, not addresses ("cms/entries") or namespaced keys ("sunrice-cms::site.search.title").
        // Laravel's own keyed lines ("auth.failed") come from its translation files.
        $code = ! str_contains($word, ' ') && (str_contains($word, '/') || str_contains($word, '::') || preg_match('/^[a-z_]+(\.[a-z_]+)+$/', $word) === 1);
        if (trim($word) !== '' && preg_match('/[A-Za-z]{2}/', $word) === 1 && ! $code) {
            $words[$word][] = str_replace($root.'/', '', $file);
        }
    };
    $q = "'((?:[^'\\\\]|\\\\.)*)'";
    $php = [
        "/(?<![\\w>])__\\(\\s*{$q}/",
        "/->(?:label|help|developerHelp|description|confirm|linkLabel|empty|heading|addLabel|navigationLabel|warnWhenChanged)\\(\\s*{$q}\\s*[,)]/",
        "/(?:Section|Card)::make\\(\\s*{$q}/",
        "/->(?:link|button|notice|short)\\(\\s*{$q}/",
        "/Permission::make\\(\\s*'[^']*',\\s*{$q},\\s*{$q}\\s*\\)/",
        "/->group\\(\\s*{$q}\\s*\\)/",
        "/->with\\(\\s*'(?:success|error)',\\s*{$q}\\s*\\)/",
        "/public static function (?:label|singularLabel|navigationLabel|navigationGroup|description|warning)\\(\\): \\??string\\s*\\{\\s*return {$q};/",
    ];
    foreach (translatableFiles(["{$root}/packages/admin/src", "{$root}/packages/admin/routes", "{$root}/packages/cms/src"], ['php']) as $file) {
        if (str_contains($file, '/Ai/') || str_contains($file, '/Console/') || str_contains($file, '/Mcp/')) {
            continue; // for agents and developers, in English
        }
        $code = (string) file_get_contents($file);
        foreach ($php as $pattern) {
            preg_match_all($pattern, $code, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                foreach (array_slice($match, 1) as $raw) {
                    $add(phpLiteral($raw), $file);
                }
            }
        }
    }
    $ts = ["/(?<![\\w.])t\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", '/(?<![\\w.])t\\(\\s*"((?:[^"\\\\]|\\\\.)*)"/', '/(?<![\\w.])t\\(\\s*`((?:[^`\\\\$]|\\\\.)*)`/'];
    foreach (translatableFiles(["{$root}/packages/admin/resources/js", "{$root}/packages/cms/resources/js"], ['ts', 'tsx']) as $file) {
        $code = (string) file_get_contents($file);
        foreach ($ts as $pattern) {
            preg_match_all($pattern, $code, $matches);
            foreach ($matches[1] as $raw) {
                $add(stripcslashes($raw), $file);
            }
        }
    }
    ksort($words);

    return $words;
}

/** @return array<string, string> */
function indonesian(): array
{
    $lines = [];
    foreach (['admin', 'cms'] as $package) {
        $decoded = json_decode((string) file_get_contents(dirname(__DIR__, 2)."/packages/{$package}/lang/id.json"), true, flags: JSON_THROW_ON_ERROR);
        $lines += is_array($decoded) ? $decoded : [];
    }

    return $lines;
}

it('has an Indonesian line for every word the panel and the CMS show', function () {
    $words = panelWords();
    expect(count($words))->toBeGreaterThan(300);

    $missing = array_diff_key($words, indonesian());

    expect(array_map(fn (array $files) => $files[0], $missing))->toBe([]);
});

it('keeps placeholders in the Indonesian lines', function () {
    $broken = [];
    foreach (indonesian() as $english => $indonesian) {
        preg_match_all('/:[a-zA-Z_]+/', $english, $from);
        preg_match_all('/:[a-zA-Z_]+/', (string) $indonesian, $to);
        $normalise = fn (array $names) => array_values(array_unique(array_map('strtolower', $names)));
        if ($normalise($from[0]) != $normalise($to[0])) {
            $broken[] = $english;
        }
    }

    expect($broken)->toBe([]);
});

it('has an Indonesian verb for every action the activity log records, for its sentences', function () {
    $root = dirname(__DIR__, 2);
    // Actions chosen in code (a ternary, a match) rather than written in the call.
    $actions = ['account locked', 'login blocked', 'two-factor turned on', 'two-factor turned off', 'renamed', 'permanently deleted', 'restored', 'roles changed', 'approved', 'unapproved', 'marked as spam'];
    foreach (translatableFiles(["{$root}/packages/admin/src", "{$root}/packages/cms/src"], ['php']) as $file) {
        foreach (file($file) ?: [] as $line) {
            // Calls with a whole action (not one put together), not the examples in doc comments.
            if (! str_starts_with(ltrim($line), '*') && preg_match_all("/->(?:record|about)\\(\\s*'([^']+)'\\s*[,)]/", $line, $matches) > 0) {
                array_push($actions, ...$matches[1]);
            }
        }
    }
    $actions = array_values(array_unique($actions));
    expect(count($actions))->toBeGreaterThan(40);

    $lines = indonesian();
    $missing = array_values(array_filter($actions, fn (string $action): bool => ! isset($lines["activity: {$action}"])));

    expect($missing)->toBe([]);
});
