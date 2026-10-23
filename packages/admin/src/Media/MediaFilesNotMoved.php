<?php

declare(strict_types=1);

namespace Sunrice\Admin\Media;

use RuntimeException;

/**
 * Moving media to the trash, or restoring it, couldn't move its files
 * (to or from the private trash disk). Nothing was changed: the media
 * and its files are where they were.
 *
 * @api
 */
final class MediaFilesNotMoved extends RuntimeException {}
