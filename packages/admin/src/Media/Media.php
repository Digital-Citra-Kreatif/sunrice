<?php

declare(strict_types=1);

namespace Sunrice\Admin\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A file in the media library, shared by the panel and its plugins.
 * Images get smaller copies (config `sunrice-admin.media.image_sizes`).
 *
 *     $media->url();             // the original
 *     $media->url('thumbnail');  // a size, or the original if it has none
 *
 * URLs carry the version, so browsers fetch a replaced file again.
 * Deleting moves a file to the trash (soft delete); DeleteMedia removes
 * it and its files for good.
 *
 * @property int $id
 * @property int|null $folder_id
 * @property string $disk
 * @property string $path
 * @property string $filename
 * @property string $mime_type
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property string|null $title
 * @property string|null $alt
 * @property string|null $caption
 * @property array<string, string> $sizes name => path
 * @property int $version
 * @property string|null $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @api
 */
class Media extends Model
{
    use SoftDeletes;

    protected $table = 'sunrice_media';

    protected $guarded = [];

    protected $attributes = ['sizes' => '{}', 'version' => 1];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sizes' => 'array',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<MediaFolder, $this> */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /** Whether smaller copies can be made: images other than SVGs and icons. */
    public function isResizable(): bool
    {
        return $this->isImage() && ! in_array($this->mime_type, ['image/svg+xml', 'image/svg', 'image/x-icon', 'image/vnd.microsoft.icon'], true);
    }

    /** The public URL of the original, or of a size (the original when it has none). */
    public function url(?string $size = null): string
    {
        $path = $size !== null && isset($this->sizes[$size]) ? $this->sizes[$size] : $this->path;
        $url = Storage::disk($this->disk)->url($path);

        return $url.(str_contains($url, '?') ? '&' : '?').'v='.$this->version;
    }

    /**
     * Every file of it on the disk: the original and its sizes.
     *
     * @return list<string>
     */
    public function files(): array
    {
        return array_values(array_unique([$this->path, ...array_values($this->sizes)]));
    }
}
