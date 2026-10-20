<?php

declare(strict_types=1);

namespace Sunrice\Admin\Help;

/**
 * The Markdown pages of the Docs or the Guide: the directories the panel
 * and its plugins registered (Panel::docs(), Panel::guide()), each a
 * section of the sidebar, with pages named "{section}/{file}".
 *
 * Guide directories hold a folder per language ("en", "id"); a page
 * missing in the reader's language is shown in English, else in any
 * language that has it. File names may start with a number to set the
 * order ("01-welcome.md" is the page "welcome").
 *
 * @internal
 */
final class HelpPages
{
    /** Names of the languages guides are commonly written in; others show their code. */
    public const LANGUAGE_NAMES = ['en' => 'English', 'id' => 'Bahasa Indonesia', 'nl' => 'Nederlands', 'de' => 'Deutsch', 'fr' => 'Français', 'es' => 'Español', 'ms' => 'Bahasa Melayu'];

    /** A page name: "section/page". Nothing else can name a file. */
    public const PAGE_PATTERN = '[a-z0-9-]+/[a-z0-9_-]+';

    /** @var array<string, array<string, array<string, string>>>|null section => slug => language ('' without) => path */
    private ?array $files = null;

    /**
     * @param  array<string, array{title: string, directory: string}>  $sections  keyed by slug
     * @param  bool  $localized  whether the directories hold a folder per language (the guide)
     */
    public function __construct(private array $sections, private bool $localized = false) {}

    /**
     * The languages the pages are written in, English first.
     *
     * @return list<string>
     */
    public function languages(): array
    {
        $languages = [];
        foreach ($this->files() as $pages) {
            foreach ($pages as $paths) {
                foreach (array_keys($paths) as $language) {
                    if ($language !== '') {
                        $languages[$language] = true;
                    }
                }
            }
        }
        $languages = array_keys($languages);
        usort($languages, fn (string $a, string $b): int => [$a !== 'en', $a] <=> [$b !== 'en', $b]);

        return $languages;
    }

    /**
     * The sidebar: each section with its pages' names and titles, in the language.
     *
     * @return list<array{key: string, title: string, pages: list<array{page: string, title: string}>}>
     */
    public function sections(string $language = ''): array
    {
        $sections = [];
        foreach ($this->files() as $key => $pages) {
            $list = [];
            foreach (array_keys($pages) as $slug) {
                $file = $this->path($key.'/'.$slug, $language);
                if ($file !== null) {
                    $list[] = ['page' => $key.'/'.$slug, 'title' => self::titleOf($file['path']) ?? ucfirst(str_replace('-', ' ', $slug))];
                }
            }
            if ($list !== []) {
                $sections[] = ['key' => $key, 'title' => $this->sections[$key]['title'], 'pages' => $list];
            }
        }

        return $sections;
    }

    /** The first page, or null when there are none. */
    public function first(string $language = ''): ?string
    {
        return $this->sections($language)[0]['pages'][0]['page'] ?? null;
    }

    /**
     * A page's file in the language, else in English, else in any; null
     * when the page doesn't exist.
     *
     * @return array{path: string, language: string, section: string, directory: string}|null
     */
    public function path(string $page, string $language = ''): ?array
    {
        if (preg_match('#^('.'[a-z0-9-]+'.')/([a-z0-9_-]+)$#', $page, $m) !== 1) {
            return null;
        }
        $paths = $this->files()[$m[1]][$m[2]] ?? [];
        foreach ([$language, 'en', ...array_keys($paths)] as $candidate) {
            if (isset($paths[$candidate])) {
                return ['path' => $paths[$candidate], 'language' => $candidate, 'section' => $m[1], 'directory' => $this->sections[$m[1]]['directory']];
            }
        }

        return null;
    }

    /**
     * The page a file is, when it is one of these pages: for links
     * written as relative paths ("../cms/site.md").
     */
    public function pageOf(string $file): ?string
    {
        $real = realpath($file);
        if ($real === false) {
            return null;
        }
        foreach ($this->files() as $key => $pages) {
            foreach ($pages as $slug => $paths) {
                if (in_array($real, $paths, true)) {
                    return $key.'/'.$slug;
                }
            }
        }

        return null;
    }

    /** The section's directory, resolved, when it exists. */
    public function directory(string $section): ?string
    {
        $directory = isset($this->sections[$section]) ? realpath($this->sections[$section]['directory']) : false;

        return $directory === false || ! is_dir($directory) ? null : $directory;
    }

    /** @return array<string, array<string, array<string, string>>> */
    private function files(): array
    {
        if ($this->files !== null) {
            return $this->files;
        }
        $files = [];
        foreach (array_keys($this->sections) as $key) {
            $directory = $this->directory($key);
            if ($directory === null) {
                continue;
            }
            $folders = $this->localized
                ? array_filter(glob($directory.'/*', GLOB_ONLYDIR) ?: [], fn (string $folder): bool => preg_match('/^[a-z]{2}(-[a-z]{2,4})?$/', basename($folder)) === 1)
                : [$directory];
            // English first, so its file names set the order.
            usort($folders, fn (string $a, string $b): int => [basename($a) !== 'en', $a] <=> [basename($b) !== 'en', $b]);
            foreach ($folders as $folder) {
                $language = $this->localized ? basename($folder) : '';
                $found = glob($folder.'/*.md') ?: [];
                sort($found);
                foreach ($found as $path) {
                    $slug = (string) preg_replace('/^\d+-/', '', basename($path, '.md'));
                    if (preg_match('/^[a-z0-9_-]+$/', $slug) === 1) {
                        $files[$key][$slug][$language] = (string) realpath($path);
                    }
                }
            }
        }

        return $this->files = $files;
    }

    private static function titleOf(string $path): ?string
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }
        $start = (string) fread($handle, 4096);
        fclose($handle);

        return Markdown::title($start);
    }
}
