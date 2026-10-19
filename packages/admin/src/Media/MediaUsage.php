<?php

declare(strict_types=1);

namespace Sunrice\Admin\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One place a media file is used, as the plugin that uses it reported it
 * (MediaUsages): an entry, a product, a setting.
 *
 * @property int $id
 * @property int $media_id
 * @property string $type
 * @property string $item_id
 * @property string $locale
 * @property string $label
 * @property string|null $url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Media $media
 *
 * @api
 */
class MediaUsage extends Model
{
    protected $table = 'sunrice_media_usages';

    protected $guarded = [];

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class)->withTrashed();
    }
}
