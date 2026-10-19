<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Media\Jobs\GenerateImageSizes;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Media\MediaConfig;
use Sunrice\Admin\Media\SafeSvg;

/**
 * Adds a file to the media library: checks it (size, allowed type, no
 * scripts or HTML, safe SVG), stores it under a new name on the media
 * disk, reads an image's dimensions and makes its sizes.
 *
 * @api
 */
final class UploadMedia
{
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
            'file.max' => "The file is larger than the {$readable} limit.",
            'file.uploaded' => "The file is larger than the server accepts ({$readable}) or didn't finish uploading.",
        ])->validate();

        $extension = strtolower($file->getClientOriginalExtension());
        $allowed = MediaConfig::allowedExtensions();
        if (! in_array($extension, $allowed, true)) {
            throw ValidationException::withMessages(['file' => ($extension === '' ? 'Files without an extension' : ".{$extension} files")." can't be uploaded. Allowed: ".implode(', ', $allowed).'.']);
        }

        // What the content really is, whatever its name says.
        $detected = strtolower((string) $file->getMimeType());
        if (in_array($detected, MediaConfig::BLOCKED_MIME_TYPES, true)) {
            throw ValidationException::withMessages(['file' => 'This file contains a web page or a script, so it can\'t be uploaded.']);
        }
        if ($extension === 'svg' || str_contains($detected, 'svg')) {
            if ($extension !== 'svg' || ! SafeSvg::isSafe((string) $file->getContent())) {
                throw ValidationException::withMessages(['file' => 'This SVG contains scripts or links that could run code, so it can\'t be uploaded. Export it again without scripts, or upload a PNG.']);
            }
        }
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
        if (! str_starts_with($mime, 'image/') || str_contains($mime, 'svg')) {
            return [null, null];
        }
        $info = @getimagesize($file->getRealPath());

        return $info === false ? [null, null] : [$info[0], $info[1]];
    }
}
