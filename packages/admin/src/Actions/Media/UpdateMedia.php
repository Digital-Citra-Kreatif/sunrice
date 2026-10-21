<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Media;

use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Panel;

/**
 * Changes a media file's details: its title, alternative text and caption,
 * and those two in the content's other languages (`translations`, by a
 * language of Panel::contentLocales() besides the first; empty: its own).
 *
 * @api
 */
final class UpdateMedia
{
    public function __construct(private ActivityLog $activity) {}

    /** @param array{title?: string|null, alt?: string|null, caption?: string|null, translations?: array<string, array{alt?: string|null, caption?: string|null}>} $details */
    public function handle(Media $media, array $details): Media
    {
        foreach (['title', 'alt', 'caption'] as $key) {
            if (array_key_exists($key, $details)) {
                $value = $details[$key];
                $media->setAttribute($key, $value === null || trim($value) === '' ? null : trim($value));
            }
        }
        if (array_key_exists('translations', $details)) {
            $translations = $media->translations ?? [];
            $others = array_slice(array_keys(app(Panel::class)->getContentLocales()), 1);
            foreach ($details['translations'] as $locale => $texts) {
                if (! in_array($locale, $others, true)) {
                    continue;
                }
                foreach (['alt', 'caption'] as $key) {
                    if (array_key_exists($key, $texts)) {
                        $text = is_string($texts[$key]) ? trim($texts[$key]) : '';
                        if ($text === '') {
                            unset($translations[$locale][$key]);
                        } else {
                            $translations[$locale][$key] = $text;
                        }
                    }
                }
            }
            $translations = array_filter($translations, fn (array $texts): bool => $texts !== []);
            $media->translations = $translations === [] ? null : $translations;
        }
        $changed = array_keys($media->getDirty());
        $media->save();
        $this->activity->updated('media', $media, $media->title ?? $media->filename, $changed);

        return $media;
    }
}
