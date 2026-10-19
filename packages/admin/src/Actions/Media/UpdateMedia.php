<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Media;

use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Media\Media;

/**
 * Changes a media file's details: its title, alternative text and caption.
 *
 * @api
 */
final class UpdateMedia
{
    public function __construct(private ActivityLog $activity) {}

    /** @param array{title?: string|null, alt?: string|null, caption?: string|null} $details */
    public function handle(Media $media, array $details): Media
    {
        foreach (['title', 'alt', 'caption'] as $key) {
            if (array_key_exists($key, $details)) {
                $value = $details[$key];
                $media->setAttribute($key, $value === null || trim($value) === '' ? null : trim($value));
            }
        }
        $changed = array_keys($media->getDirty());
        $media->save();
        $this->activity->updated('media', $media, $media->title ?? $media->filename, $changed);

        return $media;
    }
}
