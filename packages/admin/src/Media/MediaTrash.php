<?php

declare(strict_types=1);

namespace Sunrice\Admin\Media;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Keeps the files of media in the trash off the public disk: moving a
 * file to the trash moves its original and sizes to the private trash
 * disk (config `media.trash_disk`, `local`) under `media.trash_directory`,
 * at the same path inside it, and restoring moves them back. A move is
 * all or nothing: every file is copied first, and the originals are
 * deleted only once all copies are there; if anything fails, what was
 * done is undone and MediaFilesNotMoved is thrown.
 *
 * Media::delete() and restore() call it; DeleteMedia forgets both places.
 *
 * @internal
 */
final class MediaTrash
{
    /** Where a file of the media library is kept while it's in the trash. */
    public static function path(string $path): string
    {
        return MediaConfig::trashDirectory().'/'.ltrim($path, '/');
    }

    /**
     * Moves its files off its disk into the trash's. Files already there
     * (or missing) are left as they are.
     *
     * @return int how many files were moved
     *
     * @throws MediaFilesNotMoved
     */
    public function hide(Media $media): int
    {
        return $this->move($media, Storage::disk($media->disk), Storage::disk(MediaConfig::trashDisk()), $this->pairs($media, toTrash: true));
    }

    /**
     * Moves its files from the trash back to their paths on its disk.
     *
     * @return int how many files were moved
     *
     * @throws MediaFilesNotMoved
     */
    public function reveal(Media $media): int
    {
        return $this->move($media, Storage::disk(MediaConfig::trashDisk()), Storage::disk($media->disk), $this->pairs($media, toTrash: false));
    }

    /** Deletes its files from the trash (DeleteMedia deletes them from its disk). */
    public function forget(Media $media): void
    {
        Storage::disk(MediaConfig::trashDisk())->delete(array_map(self::path(...), $media->files()));
    }

    /**
     * @return array<string, string> from => to
     */
    private function pairs(Media $media, bool $toTrash): array
    {
        $pairs = [];
        foreach ($media->files() as $path) {
            if ($toTrash) {
                $pairs[$path] = self::path($path);
            } else {
                $pairs[self::path($path)] = $path;
            }
        }

        return $pairs;
    }

    /**
     * @param  array<string, string>  $pairs  from => to
     *
     * @throws MediaFilesNotMoved
     */
    private function move(Media $media, Filesystem $from, Filesystem $to, array $pairs): int
    {
        // Copy every file first: on a failure, delete the copies, and nothing changed.
        $copied = [];
        try {
            foreach ($pairs as $source => $target) {
                if (! $from->exists($source)) {
                    continue; // moved already (or missing)
                }
                self::copy($from, $to, $source, $target);
                $copied[$source] = $target;
            }
        } catch (Throwable $e) {
            self::quietly(fn () => $to->delete(array_values($copied)));
            throw $this->failed($media, $e);
        }

        // Then delete the originals: on a failure, put back those deleted.
        $deleted = [];
        try {
            foreach ($copied as $source => $target) {
                if (! $from->delete($source) && $from->exists($source)) {
                    throw new MediaFilesNotMoved("Couldn't delete {$source}.");
                }
                $deleted[$source] = $target;
            }
        } catch (Throwable $e) {
            foreach ($deleted as $source => $target) {
                self::quietly(fn () => self::copy($to, $from, $target, $source));
            }
            self::quietly(fn () => $to->delete(array_values($copied)));
            throw $this->failed($media, $e);
        }

        return count($copied);
    }

    private static function copy(Filesystem $from, Filesystem $to, string $source, string $target): void
    {
        $stream = $from->readStream($source);
        if (! is_resource($stream)) {
            throw new MediaFilesNotMoved("Couldn't read {$source}.");
        }
        try {
            $written = $to->writeStream($target, $stream);
        } finally {
            fclose($stream);
        }
        if (! $written || $to->size($target) !== $from->size($source)) {
            throw new MediaFilesNotMoved("Couldn't write {$target}.");
        }
    }

    private static function quietly(callable $undo): void
    {
        try {
            $undo();
        } catch (Throwable) {
            // Undoing as much as can be: the first error is the one reported.
        }
    }

    private function failed(Media $media, Throwable $e): MediaFilesNotMoved
    {
        Log::error('Sunrice: a media file couldn\'t be moved to or from the trash', ['media' => $media->id, 'error' => $e->getMessage()]);

        return $e instanceof MediaFilesNotMoved ? $e : new MediaFilesNotMoved($e->getMessage(), previous: $e);
    }
}
