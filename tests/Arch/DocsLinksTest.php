<?php

declare(strict_types=1);

/*
 * Every relative link in the repository's Markdown (the READMEs, the
 * CHANGELOG, the root docs, each package's docs and guide) leads to a
 * file that exists, and a link with an anchor to a heading of that page,
 * so moving or renaming a page can't leave links behind.
 */

/** @return list<string> the Markdown files to check */
function markdownFiles(string $root): array
{
    $files = glob($root.'/*.md') ?: [];
    foreach (['docs', 'packages'] as $directory) {
        $iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS),
            fn (SplFileInfo $file) => ! in_array($file->getFilename(), ['node_modules', 'vendor', 'dist', '.git'], true),
        ));
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'md') {
                $files[] = $file->getPathname();
            }
        }
    }
    sort($files);

    return $files;
}

/** The text outside fenced and inline code, where links are links. */
function markdownProse(string $markdown): string
{
    $markdown = (string) preg_replace('/^(```|~~~).*?^\1/ms', '', $markdown);

    return (string) preg_replace('/`[^`\n]*`/', '', $markdown);
}

/** @return list<string> GitHub's anchors for the page's headings */
function markdownAnchors(string $file): array
{
    preg_match_all('/^#{1,6}\s+(.+?)\s*#*\s*$/m', markdownProse((string) file_get_contents($file)), $headings);
    $anchors = [];
    foreach ($headings[1] as $heading) {
        $text = strip_tags((string) preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', str_replace('`', '', $heading)));
        $anchor = (string) preg_replace('/[^\p{L}\p{N}\s_-]/u', '', mb_strtolower($text));
        $anchor = str_replace(' ', '-', $anchor);
        $base = $anchor;
        for ($n = 1; in_array($anchor, $anchors, true); $n++) {
            $anchor = "{$base}-{$n}";
        }
        $anchors[] = $anchor;
    }

    return $anchors;
}

it('finds Markdown to check', function () {
    $files = array_map(fn (string $file) => substr($file, strlen(dirname(__DIR__, 2)) + 1), markdownFiles(dirname(__DIR__, 2)));

    expect($files)->toContain('README.md', 'docs/README.md', 'packages/admin/docs/panel.md', 'packages/cms/docs/site.md', 'packages/admin/guide/en/01-welcome.md');
});

it('has no relative links in the docs that lead nowhere', function () {
    $root = dirname(__DIR__, 2);
    $broken = [];

    foreach (markdownFiles($root) as $file) {
        preg_match_all('/\]\(\s*<?([^)\s>]+)>?(?:\s+"[^"]*")?\s*\)/', markdownProse((string) file_get_contents($file)), $links);
        foreach ($links[1] as $href) {
            if (preg_match('#^([a-z][a-z0-9+.-]*:|/|\#)#i', $href) === 1) {
                continue; // a URL, a docs:/guide: link, a site path or an anchor on the same page
            }
            [$path, $anchor] = array_pad(explode('#', $href, 2), 2, '');
            $target = dirname($file).'/'.rawurldecode($path);
            $from = substr($file, strlen($root) + 1);
            if (! file_exists($target)) {
                $broken[] = "{$from} → {$href}";
            } elseif ($anchor !== '' && str_ends_with($path, '.md') && ! in_array($anchor, markdownAnchors($target), true)) {
                $broken[] = "{$from} → {$href} (no such heading)";
            }
        }
    }

    expect($broken)->toBe([]);
});
