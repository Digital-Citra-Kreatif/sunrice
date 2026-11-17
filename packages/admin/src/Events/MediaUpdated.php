<?php

declare(strict_types=1);

namespace Sunrice\Admin\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Sunrice\Admin\Media\Media;

/**
 * A media file's details changed (UpdateMedia: `$changed` names them,
 * like "alt" or "translations"), or its file was replaced (ReplaceMedia:
 * `$changed` is ["file"]), once the database transaction it's part of
 * commits. Not fired when nothing changed.
 *
 * @api
 */
final class MediaUpdated implements ShouldDispatchAfterCommit
{
    use Dispatchable, RecordsActor;

    /** @param list<string> $changed */
    public function __construct(
        public readonly Media $media,
        public readonly array $changed,
    ) {
        $this->recordActor();
    }
}
