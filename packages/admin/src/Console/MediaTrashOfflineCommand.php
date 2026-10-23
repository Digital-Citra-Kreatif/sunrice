<?php

declare(strict_types=1);

namespace Sunrice\Admin\Console;

use Illuminate\Console\Command;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Media\MediaFilesNotMoved;
use Sunrice\Admin\Media\MediaTrash;

/**
 * sunrice:media-trash-offline — moves the files of media already in the
 * trash off the public disk, as moving to the trash does since 0.2: for
 * sites upgraded from before, once. Files already moved are left alone,
 * so it can run again.
 *
 * @internal
 */
final class MediaTrashOfflineCommand extends Command
{
    protected $signature = 'sunrice:media-trash-offline';

    protected $description = 'Move the files of media in the trash off the public disk, so their links stop working';

    public function handle(MediaTrash $trash): int
    {
        $files = 0;
        $failed = 0;
        foreach (Media::onlyTrashed()->lazyById(100) as $media) {
            try {
                $files += $trash->hide($media);
            } catch (MediaFilesNotMoved $e) {
                $failed++;
                $this->components->error("{$media->filename} (id {$media->id}): {$e->getMessage()}");
            }
        }
        $this->components->info("{$files} ".($files === 1 ? 'file' : 'files').' of media in the trash moved off the public disk.');
        if ($failed > 0) {
            $this->components->warn("{$failed} couldn't be moved and are still online: see the errors above, then run this again.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
