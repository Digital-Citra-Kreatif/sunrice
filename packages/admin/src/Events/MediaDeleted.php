<?php

declare(strict_types=1);

namespace Sunrice\Admin\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Sunrice\Admin\Media\Media;

/**
 * A media file went to the trash (`$permanent` false: its files are off
 * the public disk) or was deleted for good (`$permanent` true: the record
 * and its files are gone; the model still holds its attributes), once
 * the database transaction it's part of commits.
 *
 * @api
 */
final class MediaDeleted implements ShouldDispatchAfterCommit
{
    use Dispatchable, RecordsActor;

    public function __construct(
        public readonly Media $media,
        public readonly bool $permanent,
    ) {
        $this->recordActor();
    }
}
