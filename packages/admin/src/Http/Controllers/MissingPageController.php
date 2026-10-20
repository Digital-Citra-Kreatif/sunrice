<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Controllers;

/**
 * Any other address under the panel's path, for someone signed in: the
 * panel's 404 page in its layout (see ErrorPages).
 *
 * @internal
 */
final class MissingPageController
{
    public function __invoke(): never
    {
        abort(404);
    }
}
