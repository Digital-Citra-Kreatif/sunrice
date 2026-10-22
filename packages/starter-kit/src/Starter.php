<?php

declare(strict_types=1);

namespace Sunrice\StarterKit;

use InvalidArgumentException;

/**
 * A starter site: a folder with a `starter.json` manifest (name, title,
 * description, the languages its texts are in), its structure and sample
 * content in `site.json` (sunrice:export-structure's format, with
 * entries), and the files it copies into the app: `views/` (into
 * resources/views), `public/` (into public), `lang/` (into lang) and the
 * media library's files in `media/` (with their details in `media.json`).
 * See docs/starters.md.
 *
 * @api
 */
final class Starter
{
    /** The folders copied into the app, and where to. */
    public const FOLDERS = ['views', 'public', 'lang'];

    /** @param  list<string>  $locales */
    public function __construct(
        public readonly string $name,
        public readonly string $title,
        public readonly string $description,
        public readonly string $path,
        public readonly array $locales = [],
    ) {}

    /** @throws InvalidArgumentException when the folder has no valid starter.json */
    public static function load(string $path): self
    {
        $path = rtrim($path, '/');
        $json = is_file("{$path}/starter.json") ? file_get_contents("{$path}/starter.json") : false;
        $manifest = $json === false ? null : json_decode($json, true);
        if (! is_array($manifest)) {
            throw new InvalidArgumentException("{$path} has no starter.json (a JSON object with name, title and description).");
        }
        $name = $manifest['name'] ?? null;
        if (! is_string($name) || preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $name) !== 1) {
            throw new InvalidArgumentException("{$path}/starter.json needs a name of lowercase letters, digits and dashes (like \"blog\").");
        }
        $locales = is_array($manifest['locales'] ?? null) ? array_values(array_filter($manifest['locales'], 'is_string')) : [];

        return new self(
            $name,
            is_string($manifest['title'] ?? null) && $manifest['title'] !== '' ? $manifest['title'] : $name,
            is_string($manifest['description'] ?? null) ? $manifest['description'] : '',
            $path,
            $locales,
        );
    }

    /**
     * Its structure and sample content (empty without a site.json).
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException when site.json isn't JSON
     */
    public function site(): array
    {
        $file = "{$this->path}/site.json";
        if (! is_file($file)) {
            return [];
        }
        $site = json_decode((string) file_get_contents($file), true);
        if (! is_array($site)) {
            throw new InvalidArgumentException("{$file} isn't a structure file (JSON from sunrice:export-structure).");
        }

        /** @var array<string, mixed> $site */
        return $site;
    }

    /**
     * The media files' details from media.json, by file name: title, alt,
     * caption and their other languages' (`translations`).
     *
     * @return array<string, array<string, mixed>>
     */
    public function mediaDetails(): array
    {
        $file = "{$this->path}/media.json";
        $details = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $byName = [];
        foreach (is_array($details) ? $details : [] as $name => $item) {
            if (is_string($name) && is_array($item)) {
                /** @var array<string, mixed> $item */
                $byName[$name] = $item;
            }
        }

        return $byName;
    }

    /** @return list<string> the paths of the media library files it brings */
    public function mediaFiles(): array
    {
        $files = glob("{$this->path}/media/*") ?: [];

        return array_values(array_filter($files, is_file(...)));
    }
}
