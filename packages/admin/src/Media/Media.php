<?php

declare(strict_types=1);

namespace Sunrice\Admin\Media;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

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
 * Deleting moves a file to the trash (soft delete) and its files off the
 * public disk to the private trash disk (`media.trash_disk`), so its links
 * stop working; restoring moves them back. Either throws
 * MediaFilesNotMoved, changing nothing, when the files can't be moved.
 * DeleteMedia permanently removes it and its files, wherever they are.
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
 * @phpstan-type MediaSizeData array{url: string, width: int|null, height: int|null}
 * @phpstan-type MediaData array{id: int, url: string|null, alt: string|null, title: string|null, caption: string|null, mime: string, type: 'image'|'video'|'audio'|'document', width: int|null, height: int|null, sizes: array<string, MediaSizeData>, srcset: string}
 *
 * @api
 */
class Media extends Model
{
    use SoftDeletes {
        runSoftDelete as private softDeleteRecord;
        restore as private restoreRecord;
    }

    protected $table = 'sunrice_media';

    protected $guarded = [];

    protected $attributes = ['sizes' => '{}', 'version' => 1];

    /** @var array<string, array{0: mixed, 1: mixed}> JSON attributes as last read, and them decoded (decoded()) */
    private array $decoded = [];

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
     * Moves it to the trash, its files off the public disk (runSoftDelete());
     * DeleteMedia deletes it for good, with its files.
     *
     * @throws MediaFilesNotMoved when its files can't be moved (nothing changed)
     */
    public function delete(): ?bool
    {
        return parent::delete();
    }

    /**
     * To the trash: its files go off the public disk first, and come back
     * if the record can't be saved.
     *
     * @throws MediaFilesNotMoved
     */
    protected function runSoftDelete(): void
    {
        $trash = app(MediaTrash::class);
        $trash->hide($this);
        try {
            $this->softDeleteRecord();
        } catch (Throwable $e) {
            if (! $this->trashedInDatabase()) {
                $trash->reveal($this);
            }
            throw $e;
        }
    }

    /**
     * Out of the trash: its files go back to their paths first, and back
     * into the trash if the record isn't restored.
     *
     * @throws MediaFilesNotMoved
     */
    public function restore(): bool
    {
        $trash = app(MediaTrash::class);
        $trash->reveal($this);
        try {
            $restored = $this->restoreRecord();
        } catch (Throwable $e) {
            if ($this->trashedInDatabase()) {
                $trash->hide($this);
            }
            throw $e;
        }
        if (! $restored) {
            $trash->hide($this);
        }

        return $restored;
    }

    private function trashedInDatabase(): bool
    {
        return static::withTrashed()->whereKey($this->getKey())->whereNotNull($this->getDeletedAtColumn())->exists();
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
        $translations = $this->decoded('translations', fn (): array => $this->translations ?? []);
        $text = $translations[$locale ?? app()->getLocale()][$key] ?? null;

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
        $sizes = $size === null ? [] : $this->sizePaths();

        return ($this->urls())($size !== null && isset($sizes[$size]) ? $sizes[$size] : $this->path);
    }

    /**
     * Makes the URLs of its files (with the version), reading the disk and
     * version once for all of them.
     *
     * @return Closure(string): string
     */
    private function urls(): Closure
    {
        $disk = Storage::disk($this->disk);
        $version = $this->version;

        return static function (string $path) use ($disk, $version): string {
            $url = $disk->url($path);

            return $url.(str_contains($url, '?') ? '&' : '?').'v='.$version;
        };
    }

    /**
     * The URL of a small copy for lists and previews: the "thumbnail"
     * size, else the smallest size it has, else the original.
     */
    public function thumbnail(): string
    {
        $sizes = $this->sizePaths();
        if (isset($sizes['thumbnail'])) {
            return $this->url('thumbnail');
        }
        $widths = [];
        foreach (array_keys($sizes) as $name) {
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
        $paths = $this->sizePaths();
        $url = $this->urls();
        [$originalWidth, $originalHeight] = [$this->width, $this->height];
        // The original first: a size as wide as it is the same picture.
        $candidates = $originalWidth === null ? [] : [$originalWidth => $url($this->path).' '.$originalWidth.'w'];
        foreach ($names as $name) {
            $width = isset($paths[$name]) ? self::dimensions($config[$name] ?? null, $originalWidth, $originalHeight)[0] : null;
            if ($width !== null) {
                $candidates[$width] ??= $url($paths[$name]).' '.$width.'w';
            }
        }
        ksort($candidates);

        return implode(', ', $candidates);
    }

    /** How wide a size comes out, from its configuration and the original's dimensions (null: unknown). */
    private function sizeWidth(string $name): ?int
    {
        return $this->sizeDimensions($name)[0];
    }

    /**
     * How wide and high a size comes out, from its configuration and the
     * original's dimensions (null: unknown).
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function sizeDimensions(string $name): array
    {
        return self::dimensions(MediaConfig::imageSizes()[$name] ?? null, $this->width, $this->height);
    }

    /**
     * How wide and high a size (its configuration, null: none) of an
     * original this wide and high comes out (null: unknown).
     *
     * @param  array{width: int|null, height: int|null, crop: bool}|null  $size
     * @return array{0: int|null, 1: int|null}
     */
    private static function dimensions(?array $size, ?int $width, ?int $height): array
    {
        if ($size === null) {
            return [null, null];
        }
        if ($size['crop']) {
            return [$size['width'], $size['height']];
        }
        if ($width === null || $height === null || $width < 1 || $height < 1) {
            return [$size['width'], null];
        }
        // Scaled down to fit, never up.
        $factor = min(1, $size['width'] === null ? 1 : $size['width'] / $width, $size['height'] === null ? 1 : $size['height'] / $height);

        return [max(1, (int) round($width * $factor)), max(1, (int) round($height * $factor))];
    }

    /**
     * What kind of file it is, as whereTypes() counts them.
     *
     * @return 'image'|'video'|'audio'|'document'
     */
    private function kind(): string
    {
        return match (true) {
            str_starts_with($this->mime_type, 'image/') => 'image',
            str_starts_with($this->mime_type, 'video/') => 'video',
            str_starts_with($this->mime_type, 'audio/') => 'audio',
            default => 'document',
        };
    }

    /**
     * A plain array of it, for a script on a page (a gallery, a map) or a
     * small JSON route of the app's: its id, URL (the original's), alt
     * text, title and caption in a language (null: the app's, as altIn()),
     * MIME type and kind, an image's dimensions, its sizes (name =>
     * url, width, height) and a ready `srcset`. The same URLs as url()
     * and srcset(). In the trash its files are offline: `url` is null and
     * it has no sizes. Only what's listed here: never its disk, path or
     * who uploaded it (toArray() is Eloquent's own and not meant for pages).
     *
     *     <div data-photos='@json($gallery->map->toData())'></div>
     *
     * @return MediaData
     */
    public function toData(?string $locale = null): array
    {
        $online = ! $this->trashed();
        $sizes = [];
        // In the config's order, whatever order the database kept them in (MySQL sorts JSON keys).
        $paths = $online ? $this->sizePaths() : [];
        $names = array_keys($paths);
        $rank = MediaConfig::imageSizeRanks();
        usort($names, fn (string $a, string $b): int => [$rank[$a] ?? PHP_INT_MAX, $a] <=> [$rank[$b] ?? PHP_INT_MAX, $b]);
        $config = MediaConfig::imageSizes();
        [$width, $height] = [$this->width, $this->height];
        $url = $this->urls();
        foreach ($names as $name) {
            [$sizeWidth, $sizeHeight] = self::dimensions($config[$name] ?? null, $width, $height);
            $sizes[$name] = ['url' => $url($paths[$name]), 'width' => $sizeWidth, 'height' => $sizeHeight];
        }
        $image = $this->isImage();

        return [
            'id' => $this->id,
            'url' => $online ? $url($this->path) : null,
            'alt' => $this->altIn($locale),
            'title' => $this->title !== null && $this->title !== '' ? $this->title : null,
            'caption' => $this->captionIn($locale),
            'mime' => $this->mime_type,
            'type' => $this->kind(),
            'width' => $image ? $width : null,
            'height' => $image ? $height : null,
            'sizes' => $sizes,
            'srcset' => $online ? $this->srcset() : '',
        ];
    }

    /**
     * Its sizes (name => path), decoded once for as long as they don't
     * change: the array cast decodes the JSON on every read, and a list of
     * images reads them for every URL.
     *
     * @return array<string, string>
     */
    private function sizePaths(): array
    {
        /** @var array<string, string> */
        return $this->decoded('sizes', fn (): array => $this->sizes);
    }

    /**
     * A JSON attribute decoded once for as long as it isn't changed.
     *
     * @template TValue
     *
     * @param  Closure(): TValue  $read
     * @return TValue
     */
    private function decoded(string $key, Closure $read): mixed
    {
        $stored = $this->attributes[$key] ?? null;
        if (! isset($this->decoded[$key]) || $this->decoded[$key][0] !== $stored) {
            $this->decoded[$key] = [$stored, $read()];
        }

        return $this->decoded[$key][1];
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
