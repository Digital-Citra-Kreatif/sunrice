<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Media\FileContent;
use Sunrice\Admin\Media\Jobs\GenerateImageSizes;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Media\MediaConfig;
use Sunrice\Admin\Media\SafeSvg;
use Sunrice\Admin\Security\SecurityLog;

/**
 * Adds a file to the media library: checks it (size, allowed type, no
 * scripts or HTML, safe SVG), stores it under a new name on the media
 * disk, reads an image's dimensions and makes its sizes.
 *
 * @api
 */
final class UploadMedia
{
    public function __construct(private ActivityLog $activity) {}

    /**
     * @param  array{folder_id?: int|null, title?: string|null, alt?: string|null, uploaded_by?: string|null}  $attributes
     *
     * @throws ValidationException
     */
    public function handle(UploadedFile $file, array $attributes = []): Media
    {
        self::validate($file);

        $disk = MediaConfig::disk();
        $path = self::newPath($file);
        // Streamed from the temporary upload, not read into memory.
        Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));
        [$width, $height] = self::dimensions($file);

        $media = Media::query()->create([
            'folder_id' => $attributes['folder_id'] ?? null,
            'disk' => $disk,
            'path' => $path,
            'filename' => Str::limit($file->getClientOriginalName(), 250, ''),
            'mime_type' => self::mimeType($file),
            'size' => $file->getSize() ?: 0,
            'width' => $width,
            'height' => $height,
            'title' => $attributes['title'] ?? Str::limit(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 250, ''),
            'alt' => $attributes['alt'] ?? null,
            'sizes' => [],
            'version' => 1,
            'uploaded_by' => $attributes['uploaded_by'] ?? null,
        ]);
        GenerateImageSizes::for($media);
        $this->activity->record('uploaded', 'media', $media, $media->title ?? $media->filename);

        return $media->refresh();
    }

    /**
     * The checks every upload passes (also for replacing a file).
     *
     * @throws ValidationException
     */
    public static function validate(UploadedFile $file): void
    {
        $limit = MediaConfig::maxKilobytes();
        $readable = $limit >= 1024 ? round($limit / 1024, 1).' MB' : "{$limit} KB";
        Validator::make(['file' => $file], ['file' => ['required', 'file', "max:{$limit}"]], [
            'file.max' => __('The file is larger than the :size limit.', ['size' => $readable]),
            'file.uploaded' => __('The file is larger than the server accepts (:size) or didn\'t finish uploading.', ['size' => $readable]),
        ])->validate();

        $extension = strtolower($file->getClientOriginalExtension());
        $allowed = MediaConfig::allowedExtensions();
        if (in_array($extension, MediaConfig::BLOCKED_EXTENSIONS, true)) {
            self::blocked($file, ".{$extension} files are never accepted");
        }
        if (! in_array($extension, $allowed, true)) {
            throw ValidationException::withMessages(['file' => $extension === ''
                ? __('Files without an extension can\'t be uploaded. Allowed: :allowed.', ['allowed' => implode(', ', $allowed)])
                : __('.:extension files can\'t be uploaded. Allowed: :allowed.', ['extension' => $extension, 'allowed' => implode(', ', $allowed)])]);
        }

        // What the content really is, whatever its name says.
        $detected = strtolower((string) $file->getMimeType());
        if (in_array($detected, MediaConfig::BLOCKED_MIME_TYPES, true)) {
            self::blocked($file, "the content is {$detected}");

            throw ValidationException::withMessages(['file' => __('This file contains a web page or a script, so it can\'t be uploaded.')]);
        }
        // An image must be one, and one with PHP code in it (a polyglot) is an attack. (PHP files show as text/x-php above.)
        if (in_array($extension, FileContent::IMAGE_EXTENSIONS, true)) {
            if (FileContent::containsPhp($file)) {
                self::blocked($file, 'an image with PHP code in it');

                throw ValidationException::withMessages(['file' => __('This image contains PHP code, so it can\'t be uploaded.')]);
            }
            if (! FileContent::matchesExtension($file)) {
                throw ValidationException::withMessages(['file' => __('This isn\'t a valid image: the file\'s content doesn\'t match .:extension. Save it again as an image, or upload it under its real extension.', ['extension' => $extension])]);
            }
        }
        if ($extension === 'svg' || str_contains($detected, 'svg')) {
            if ($extension !== 'svg' || ! SafeSvg::isSafe((string) $file->getContent())) {
                self::blocked($file, 'an SVG with scripts or links that run code');

                throw ValidationException::withMessages(['file' => __('This SVG contains scripts or links that could run code, so it can\'t be uploaded. Export it again without scripts, or upload a PNG.')]);
            }
        }
    }

    /** A file that could run code is a security event; an extension that just isn't allowed is a mistake. */
    private static function blocked(UploadedFile $file, string $reason): void
    {
        $name = Str::limit($file->getClientOriginalName(), 120, '');
        $user = auth()->user();
        app(SecurityLog::class)->record('upload blocked', $user instanceof Model ? $user : null, null, ['note' => "{$name}: {$reason}"]);
    }

    /** {directory}/{Y}/{m}/{random}-{slug}.{ext}: unique, and never the uploader's raw name. */
    private static function newPath(UploadedFile $file): string
    {
        $name = Str::slug(Str::limit(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 80, ''));
        $extension = strtolower($file->getClientOriginalExtension());

        return ltrim(MediaConfig::directory().'/'.date('Y/m').'/'.Str::lower(Str::random(10)).'-'.($name !== '' ? $name : 'file').'.'.$extension, '/');
    }

    public static function mimeType(UploadedFile $file): string
    {
        $mime = $file->getMimeType();
        // finfo reports SVGs as XML or plain text at times.
        if (strtolower($file->getClientOriginalExtension()) === 'svg') {
            return 'image/svg+xml';
        }

        return is_string($mime) && $mime !== '' ? $mime : 'application/octet-stream';
    }

    /** @return array{0: int|null, 1: int|null} */
    public static function dimensions(UploadedFile $file): array
    {
        $mime = self::mimeType($file);
        if (str_contains($mime, 'svg')) {
            // From its viewBox: so a wide SVG logo isn't taken for a square icon.
            return SafeSvg::dimensions((string) $file->getContent());
        }
        if (! str_starts_with($mime, 'image/')) {
            return [null, null];
        }
        $info = @getimagesize($file->getRealPath());

        return $info === false ? [null, null] : [$info[0], $info[1]];
    }
}
