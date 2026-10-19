<?php

declare(strict_types=1);

namespace Sunrice\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Sunrice\Admin\Media\Jobs\GenerateImageSizes;
use Sunrice\Admin\Media\Media;

/**
 * sunrice:media-sizes — make the image sizes again (after changing
 * `sunrice-admin.media.image_sizes`), for all images or some.
 *
 * @internal
 */
final class MediaSizesCommand extends Command
{
    protected $signature = 'sunrice:media-sizes {--id=* : Only these media ids}';

    protected $description = 'Make the media library\'s image sizes again';

    public function handle(): int
    {
        $ids = array_values(array_filter((array) $this->option('id'), 'is_numeric'));
        $query = Media::query()->where('mime_type', 'like', 'image/%')->when($ids !== [], fn (Builder $query) => $query->whereKey($ids));

        $count = 0;
        foreach ($query->lazyById(100) as $media) {
            if ($media->isResizable()) {
                (new GenerateImageSizes($media->id))->handle();
                $count++;
            }
        }
        $this->components->info("Image sizes made for {$count} ".($count === 1 ? 'image' : 'images').'.');

        return self::SUCCESS;
    }
}
