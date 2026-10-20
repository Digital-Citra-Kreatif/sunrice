<?php

declare(strict_types=1);

namespace Sunrice\Admin\Support;

/**
 * Searching with LIKE for what was typed, not for patterns.
 *
 * @internal
 */
final class Like
{
    /**
     * A value with its % and _ escaped (and \, the escape character), to
     * put between %s in whereLike(): `'%'.Like::escape($search).'%'`.
     */
    public static function escape(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
