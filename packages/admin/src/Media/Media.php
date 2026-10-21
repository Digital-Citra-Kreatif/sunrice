<?php

declare(strict_types=1);

namespace Sunrice\Admin\Media;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A file in the media library, shared by the panel and its plugins.
 * Images get smaller copies (config `sunrice-admin.media.image_sizes`).
 *
 *     $media->url();             // the original
 *     $media->url('thumbnail');  // a size, or the original if it has none
 *     $media->thumbnail();       // the thumbnail, the smallest size, or the original
 *     $media->srcset();          // "…-medium.jpg?v=1 800w, …-large.jpg?v=1 1600w, ….jpg?v=1 2400w"
 *
 * URLs carry the version, so browsers fetch a replaced file again.
 * Deleting moves a file to the trash (soft delete); DeleteMedia permanently
 * removes it and its files.
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
 * @property array<string, array{alt?: string, caption?: string}>|null $translations alt text and caption in the content's other languages (Panel::contentLocales())
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
            'translations' => 'array',
        ];
    }

    /**
     * The alternative text in a language (null: the app's), else its own:
     * `alt="{{ $image->altIn() }}"` on a page in any of the content's
     * languages (Panel::contentLocales()).
     */
    public function altIn(?string $locale = null): ?string
    {
        return $this->translatedText('alt', $locale) ?? $this->alt;
    }

    /** The caption in a language (null: the app's), else its own. */
    public function captionIn(?string $locale = null): ?string
    {
        return $this->translatedText('caption', $locale) ?? $this->caption;
    }

    private function translatedText(string $key, ?string $locale): ?string
    {
        $text = ($this->translations ?? [])[$locale ?? app()->getLocale()][$key] ?? null;

        return is_string($text) && trim($text) !== '' ? $text : null;
    }

    /** @return HasMany<MediaUsage, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(MediaUsage::class);
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

    /** The kinds of file a query can be limited to. */
    public const TYPES = ['image', 'video', 'audio', 'document'];

    /**
     * Limit a query to some kinds of file: image, video, audio, document
     * (anything else). No types: no limit.
     *
     * @param  Builder<Media>|QueryBuilder  $query
     * @param  list<string>  $types
     */
    public static function whereTypes(Builder|QueryBuilder $query, array $types): void
    {
        if ($types === []) {
            return;
        }
        $query->where(function (Builder|QueryBuilder $any) use ($types): void {
            foreach ($types as $type) {
                if ($type === 'document') {
                    $any->orWhere(fn (Builder|QueryBuilder $q) => $q->whereNot(fn (Builder|QueryBuilder $n) => $n
                        ->whereLike('mime_type', 'image/%')->orWhereLike('mime_type', 'video/%')->orWhereLike('mime_type', 'audio/%')));
                } elseif (in_array($type, self::TYPES, true)) {
                    $any->orWhereLike('mime_type', "{$type}/%");
                }
            }
        });
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
     * The URL of a small copy for lists and previews: the "thumbnail"
     * size, else the smallest size it has, else the original.
     */
    public function thumbnail(): string
    {
        if (isset($this->sizes['thumbnail'])) {
            return $this->url('thumbnail');
        }
        $widths = [];
        foreach (array_keys($this->sizes) as $name) {
            $widths[$name] = $this->sizeWidth($name) ?? PHP_INT_MAX;
        }
        asort($widths);

        return $this->url(array_key_first($widths));
    }

    /**
     * A `srcset` attribute value: the URL and width of each of these sizes
     * it has, and of the original. Without names, every size that keeps
     * the image's proportions ("fit", not "crop"). Sizes as wide as the
     * original or one listed before are left out; '' for files that
     * can't have sizes.
     *
     *     <img src="{{ $media->url('medium') }}" srcset="{{ $media->srcset() }}" sizes="(min-width: 800px) 800px, 100vw">
     */
    public function srcset(string ...$sizes): string
    {
        if (! $this->isResizable()) {
            return '';
        }
        $config = MediaConfig::imageSizes();
        $names = $sizes !== [] ? $sizes : array_keys(array_filter($config, fn (array $size) => ! $size['crop']));
        // The original first: a size as wide as it is the same picture.
        $candidates = $this->width === null ? [] : [$this->width => $this->url().' '.$this->width.'w'];
        foreach ($names as $name) {
            $width = isset($this->sizes[$name]) ? $this->sizeWidth($name) : null;
            if ($width !== null) {
                $candidates[$width] ??= $this->url($name).' '.$width.'w';
            }
        }
        ksort($candidates);

        return implode(', ', $candidates);
    }

    /** How wide a size comes out, from its configuration and the original's dimensions (null: unknown). */
    private function sizeWidth(string $name): ?int
    {
        $size = MediaConfig::imageSizes()[$name] ?? null;
        if ($size === null) {
            return null;
        }
        if ($size['crop']) {
            return $size['width'];
        }
        if ($this->width === null || $this->height === null || $this->width < 1 || $this->height < 1) {
            return $size['width'];
        }
        // Scaled down to fit, never up.
        $factor = min(1, $size['width'] === null ? 1 : $size['width'] / $this->width, $size['height'] === null ? 1 : $size['height'] / $this->height);

        return max(1, (int) round($this->width * $factor));
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
