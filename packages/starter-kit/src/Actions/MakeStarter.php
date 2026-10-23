<?php

declare(strict_types=1);

namespace Sunrice\StarterKit\Actions;

use FilesystemIterator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Sunrice\Admin\Media\Media;
use Sunrice\Cms\Structure\StructureExporter;
use Sunrice\Cms\Support\Locales;
use Sunrice\StarterKit\Starter;

/**
 * Makes a starter from the site as it is: its structure, content and
 * Settings → Site (StructureExporter, entries and settings included) in
 * site.json, the media files the content and settings use in media/
 * (each referred to by its file name, so another site finds the uploaded
 * copy) with their details in media.json, the templates
 * (resources/views/cms and errors), the public folders named, and the
 * app's translation files (lang/: its own words in every language, and
 * the CMS's site words it overrides, lang/vendor/sunrice-cms; not other
 * packages' published copies). Other settings (languages, mail, keys,
 * cache) and users aren't part of a starter.
 *
 * @api
 */
final class MakeStarter
{
    /** The app's folders a starter takes, relative to resources/views and lang. */
    public const VIEWS = ['cms', 'errors'];

    /** The packages of lang/vendor/ a starter takes: the CMS's site words. Everything else in lang/ is the app's own. */
    public const LANG_VENDOR = ['sunrice-cms'];

    public function __construct(
        private readonly StructureExporter $exporter,
        private readonly Filesystem $files,
    ) {}

    /**
     * @param  list<string>  $public  folders of public/ to take ("site": its CSS, fonts and images)
     *
     * @throws InvalidArgumentException when the name isn't valid, or the folder isn't empty (without $force)
     */
    public function handle(string $name, string $directory, string $title = '', string $description = '', array $public = [], bool $force = false): Starter
    {
        if (preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $name) !== 1) {
            throw new InvalidArgumentException('Name the starter with lowercase letters, digits and dashes (like "blog").');
        }
        $public = array_map(fn (string $folder): string => trim($folder, '/'), $public);
        foreach ($public as $folder) {
            if ($folder === '' || str_contains($folder, '..') || ! is_dir(public_path($folder))) {
                throw new InvalidArgumentException("\"{$folder}\" isn't a folder of public/.");
            }
        }
        $directory = rtrim($directory, '/');
        if (is_dir($directory) && (new FilesystemIterator($directory))->valid() && ! $force) {
            throw new InvalidArgumentException("{$directory} isn't empty: choose another folder, or replace it with --force.");
        }
        if (is_dir($directory)) {
            $this->files->deleteDirectory($directory, preserve: true);
        }
        $this->files->ensureDirectoryExists($directory);

        // The export keeps some maps as objects; as arrays here, to walk them (an empty one is written [], read the same on import).
        $site = json_decode(json_encode($this->exporter->export(content: true, settings: true), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        $site = is_array($site) ? $site : [];
        [$site, $media] = $this->withMedia($site, $directory);
        $this->writeJson("{$directory}/site.json", $site);
        if ($media !== []) {
            $this->writeJson("{$directory}/media.json", $media);
        }
        foreach (self::VIEWS as $folder) {
            $this->copyFolder(resource_path("views/{$folder}"), "{$directory}/views/{$folder}");
        }
        foreach ($public as $folder) {
            $this->copyFolder(public_path($folder), "{$directory}/public/{$folder}");
        }
        $this->copyLang("{$directory}/lang");
        $this->writeJson("{$directory}/starter.json", [
            'name' => $name,
            'title' => $title !== '' ? $title : ucfirst(str_replace('-', ' ', $name)),
            'description' => $description,
            'locales' => Locales::all(),
        ]);

        return Starter::load($directory);
    }

    /**
     * The site with each media reference ({"media": "<path in the library>"})
     * made the file's name in media/, the files copied there, and their details.
     *
     * @param  array<mixed>  $site
     * @return array{0: array<mixed>, 1: array<string, array<string, mixed>>}
     */
    private function withMedia(array $site, string $directory): array
    {
        $paths = [];
        array_walk_recursive($site, function (mixed $value, int|string $key) use (&$paths): void {
            if ($key === 'media' && is_string($value)) {
                $paths[$value] = true;
            }
        });
        $names = [];
        $details = [];
        foreach (Media::withTrashed()->whereIn('path', array_keys($paths))->orderBy('id')->get() as $media) {
            $disk = Storage::disk($media->disk);
            if (! $disk->exists($media->path)) {
                continue;
            }
            // Its name as uploaded ("pool.jpg"), unless another file has it.
            $name = basename($media->filename) !== '' && ! in_array(basename($media->filename), $names, true) ? basename($media->filename) : basename($media->path);
            $this->files->ensureDirectoryExists("{$directory}/media");
            $this->files->put("{$directory}/media/{$name}", (string) $disk->get($media->path));
            $names[$media->path] = $name;
            $details[$name] = array_filter([
                'title' => $media->title,
                'alt' => $media->alt,
                'caption' => $media->caption,
                'translations' => $media->translations ?: null,
            ], fn (mixed $value): bool => $value !== null && $value !== '');
        }

        return [self::renamed($site, $names), $details];
    }

    /**
     * @param  array<mixed>  $data
     * @param  array<string, string>  $names  library path => file name in media/
     * @return array<mixed>
     */
    private static function renamed(array $data, array $names): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = count($value) === 1 && is_string($value['media'] ?? null) && isset($names[$value['media']])
                    ? ['media' => $names[$value['media']]]
                    : self::renamed($value, $names);
            }
        }

        return $data;
    }

    private function copyFolder(string $from, string $to): void
    {
        if (is_dir($from)) {
            $this->files->copyDirectory($from, $to);
        }
    }

    /** The app's lang/ files, without other packages' published copies (lang/vendor/<package> but LANG_VENDOR). */
    private function copyLang(string $to): void
    {
        if (! is_dir(lang_path())) {
            return;
        }
        foreach ($this->files->allFiles(lang_path(), true) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $parts = explode('/', $relative);
            if ($parts[0] === 'vendor' && ! in_array($parts[1] ?? '', self::LANG_VENDOR, true)) {
                continue;
            }
            $this->files->ensureDirectoryExists(dirname("{$to}/{$relative}"));
            $this->files->copy($file->getPathname(), "{$to}/{$relative}");
        }
    }

    /** @param  array<mixed>  $data */
    private function writeJson(string $file, array $data): void
    {
        $this->files->put($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
    }
}
