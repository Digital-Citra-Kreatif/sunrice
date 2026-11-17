<?php

declare(strict_types=1);

namespace Sunrice\Admin\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Sunrice\Admin\Media\Media;

/**
 * A media file is about to go to the trash (TrashMedia, `$permanent`
 * false) or be deleted for good (DeleteMedia, `$permanent` true), after
 * the panel's own checks. A listener can stop it by throwing a
 * ValidationException: the editor sees its message as they'd see the
 * panel's own refusal, and nothing is changed.
 *
 *     throw ValidationException::withMessages(['media' => 'The logo can\'t be deleted.']);
 *
 * @api
 */
final class MediaDeleting
{
    use Dispatchable, RecordsActor;

    public function __construct(
        public readonly Media $media,
        public readonly bool $permanent,
    ) {
        $this->recordActor();
    }
}
