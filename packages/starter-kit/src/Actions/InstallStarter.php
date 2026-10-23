<?php

declare(strict_types=1);

namespace Sunrice\StarterKit\Actions;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Actions\Media\UpdateMedia;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Media\Media;
use Sunrice\Cms\Structure\ImportReport;
use Sunrice\Cms\Structure\StructureImporter;
use Sunrice\Cms\Structure\StructureImportFailed;
use Sunrice\StarterKit\Installation;
use Sunrice\StarterKit\Starter;
use Throwable;

/**
 * Installs a starter into the app: checks its structure first (nothing
 * is saved when it doesn't fit), uploads its media files the library
 * doesn't have yet, imports its structure and sample content through
 * StructureImporter (the admin's own checks and actions; existing items
 * with the same handles are updated as an import does) and copies its
 * views, public files and translations. Files the app has, different,
 * are kept unless `$force`. Running it again adds only what's missing.
 *
 * @api
 */
final class InstallStarter
{
    public function __construct(
        private readonly StructureImporter $importer,
        private readonly UploadMedia $upload,
        private readonly UpdateMedia $updateMedia,
        private readonly Filesystem $files,
    ) {}

    /**
     * @param  bool  $content  false: the structure only, without its entries, terms and global values
     * @param  bool  $skipOtherLanguages  leave out its texts in languages the site doesn't have (else it stops)
     *
     * @throws StructureImportFailed when its structure doesn't fit the site (nothing is saved)
     * @throws ValidationException when a media file isn't one the library takes (nothing is saved)
     */
    public function handle(Starter $starter, bool $dryRun = false, bool $force = false, bool $content = true, bool $skipOtherLanguages = false): Installation
    {
        $site = $content ? $starter->site() : self::withoutContent($starter->site());
        [$toUpload, $reused] = $this->media($starter);
        // Checked as a whole first, a starter that doesn't fit leaving the site as it was: its media files as
        // uploads are, and its structure and content with the files it brings counting as in the library.
        foreach ($toUpload as $name => $path) {
            UploadMedia::validate(new UploadedFile($path, $name, null, null, true));
        }
        $report = $this->importer->import($site, true, $force, false, $skipOtherLanguages, $toUpload);
        $plan = $this->plan($starter, $force);
        if ($dryRun) {
            return new Installation($starter, $report, $plan, array_keys($toUpload), $reused, true);
        }

        $report = $this->save($starter, $site, $toUpload, $force, $skipOtherLanguages);
        foreach ($plan as $relative => $status) {
            if ($status === Installation::COPIED) {
                $target = base_path($relative);
                $this->files->ensureDirectoryExists(dirname($target));
                $this->files->copy(self::source($starter, $relative), $target);
            }
        }

        return new Installation($starter, $report, $plan, array_keys($toUpload), $reused, false);
    }

    /**
     * Uploads its media files and imports its structure and content in one
     * database transaction: all of it or nothing (the files uploaded are
     * deleted again when it fails). Jobs it queues (the images' sizes) go
     * to the queue once it
     * commits, so a worker (on SQLite, one writing to the same file) never
     * takes one while the install holds the database.
     *
     * @param  array<string, mixed>  $site
     * @param  array<string, string>  $toUpload  name => path
     */
    private function save(Starter $starter, array $site, array $toUpload, bool $force, bool $skipOtherLanguages): ImportReport
    {
        $details = $starter->mediaDetails();
        $uploaded = [];
        try {
            return DB::transaction(function () use ($site, $toUpload, $details, $force, $skipOtherLanguages, &$uploaded): ImportReport {
                foreach ($toUpload as $name => $path) {
                    $uploaded[] = $this->uploadMedia($name, $path, $details[$name] ?? []);
                }

                return $this->importer->import($site, false, $force, false, $skipOtherLanguages);
            });
        } catch (Throwable $e) {
            foreach ($uploaded as $media) {
                Storage::disk($media->disk)->delete($media->files());
            }

            throw $e;
        }
    }

    /**
     * The site without its content: entries, terms and global values.
     *
     * @param  array<string, mixed>  $site
     * @return array<string, mixed>
     */
    public static function withoutContent(array $site): array
    {
        unset($site['entries'], $site['terms']);
        if (is_array($site['globals'] ?? null)) {
            $site['globals'] = array_map(function (mixed $set): mixed {
                if (is_array($set)) {
                    unset($set['values']);
                }

                return $set;
            }, $site['globals']);
        }

        return $site;
    }

    /**
     * Each file of its folders, by its path in the app: copied, unchanged or kept.
     *
     * @return array<string, string>
     */
    private function plan(Starter $starter, bool $force): array
    {
        $plan = [];
        foreach (Starter::FOLDERS as $folder) {
            $root = "{$starter->path}/{$folder}";
            if (! is_dir($root)) {
                continue;
            }
            foreach ($this->files->allFiles($root, true) as $file) {
                $relative = self::target($folder, str_replace('\\', '/', $file->getRelativePathname()));
                $target = base_path($relative);
                $plan[$relative] = match (true) {
                    ! is_file($target) => Installation::COPIED,
                    hash_file('xxh128', $target) === hash_file('xxh128', $file->getPathname()) => Installation::UNCHANGED,
                    $force => Installation::COPIED,
                    default => Installation::KEPT,
                };
            }
        }
        ksort($plan);

        return $plan;
    }

    /** A starter file's path in the app, relative to its base path: views/cms/x → resources/views/cms/x. */
    private static function target(string $folder, string $relative): string
    {
        $base = match ($folder) {
            'views' => resource_path('views'),
            'public' => public_path(),
            default => lang_path(),
        };

        return ltrim(substr($base, strlen(base_path())), '/').'/'.$relative;
    }

    /** The starter's file for an app path that plan() gave. */
    private static function source(Starter $starter, string $relative): string
    {
        foreach (Starter::FOLDERS as $folder) {
            $prefix = self::target($folder, '');
            if (str_starts_with($relative, $prefix)) {
                return "{$starter->path}/{$folder}/".substr($relative, strlen($prefix));
            }
        }

        return "{$starter->path}/{$relative}";
    }

    /**
     * Its media files to upload (name => path) and those the library has
     * (a file of the same name: what the import finds its references by).
     *
     * @return array{0: array<string, string>, 1: list<string>}
     */
    private function media(Starter $starter): array
    {
        $files = [];
        foreach ($starter->mediaFiles() as $path) {
            $files[basename($path)] = $path;
        }
        $had = [];
        foreach ($files === [] ? [] : Media::query()->whereIn('filename', array_keys($files))->pluck('filename') as $name) {
            if (is_string($name)) {
                $had[] = $name;
            }
        }
        $reused = array_values(array_intersect(array_keys($files), $had));

        return [array_diff_key($files, array_flip($reused)), $reused];
    }

    /** @param  array<string, mixed>  $details  title, alt, caption, translations */
    private function uploadMedia(string $name, string $path, array $details): Media
    {
        $text = fn (string $key): ?string => is_string($details[$key] ?? null) ? $details[$key] : null;
        $media = $this->upload->handle(new UploadedFile($path, $name, null, null, true), array_filter([
            'title' => $text('title'),
            'alt' => $text('alt'),
        ], fn (?string $value): bool => $value !== null));
        $more = [];
        if ($text('caption') !== null) {
            $more['caption'] = $text('caption');
        }
        // Alt text and captions in the content's other languages: {"id": {"alt": "…", "caption": "…"}}.
        foreach (is_array($details['translations'] ?? null) ? $details['translations'] : [] as $locale => $texts) {
            if (is_string($locale) && is_array($texts)) {
                $more['translations'][$locale] = array_filter([
                    'alt' => is_string($texts['alt'] ?? null) ? $texts['alt'] : null,
                    'caption' => is_string($texts['caption'] ?? null) ? $texts['caption'] : null,
                ], fn (?string $value): bool => $value !== null);
            }
        }
        if ($more !== []) {
            $this->updateMedia->handle($media, $more);
        }

        return $media;
    }
}
