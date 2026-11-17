<?php

declare(strict_types=1);

namespace Sunrice\Admin\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Sunrice\Admin\Media\Media;

/**
 * A file was added to the media library (UploadMedia), once the database
 * transaction it's part of commits. Its image sizes may still be being
 * made (on the queue).
 *
 * @api
 */
final class MediaUploaded implements ShouldDispatchAfterCommit
{
    use Dispatchable, RecordsActor;

    public function __construct(public readonly Media $media)
    {
        $this->recordActor();
    }
}
