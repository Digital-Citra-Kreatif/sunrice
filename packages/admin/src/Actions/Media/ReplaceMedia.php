<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Media\Jobs\GenerateImageSizes;
use Sunrice\Admin\Media\Media;

/**
 * Replaces a media file's content. The same kind of file keeps its path,
 * so links to it keep working; the version goes up so browsers fetch it
 * again, and the image sizes are made again.
 *
 * @api
 */
final class ReplaceMedia
{
    /** @throws ValidationException */
    public function handle(Media $media, UploadedFile $file): Media
    {
        UploadMedia::validate($file);
        $disk = Storage::disk($media->disk);

        foreach ($media->sizes as $size) {
            $disk->delete($size);
        }
        // Another type (a JPG for a PNG) gets the right extension.
        $path = $media->path;
        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            $disk->delete($path);
            $path = substr($path, 0, -strlen(pathinfo($path, PATHINFO_EXTENSION))).$extension;
        }
        $disk->putFileAs(dirname($path), $file, basename($path));
        [$width, $height] = UploadMedia::dimensions($file);

        $media->forceFill([
            'path' => $path,
            'filename' => $file->getClientOriginalName() !== '' ? mb_substr($file->getClientOriginalName(), 0, 250) : $media->filename,
            'mime_type' => UploadMedia::mimeType($file),
            'size' => $file->getSize() ?: 0,
            'width' => $width,
            'height' => $height,
            'sizes' => [],
            'version' => $media->version + 1,
        ])->save();
        GenerateImageSizes::for($media);

        return $media->refresh();
    }
}
