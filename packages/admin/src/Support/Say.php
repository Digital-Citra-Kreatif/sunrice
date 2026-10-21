<?php

declare(strict_types=1);

namespace Sunrice\Admin\Support;

/**
 * Words written in English in code (a field's label, a section's title,
 * a card's text) said in the panel's language when they're sent to the
 * page: __() for strings, the rest as they are. Data (record titles)
 * doesn't go through here.
 *
 * @internal
 */
final class Say
{
    /**
     * @template T
     *
     * @param  T  $text
     * @return T|string
     */
    public static function it(mixed $text): mixed
    {
        if (! is_string($text) || $text === '') {
            return $text;
        }
        $said = __($text);

        return is_string($said) ? $said : $text;
    }
}
