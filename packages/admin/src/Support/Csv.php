<?php

declare(strict_types=1);

namespace Sunrice\Admin\Support;

/**
 * Writing CSV that is safe to open in a spreadsheet.
 *
 * @api
 */
final class Csv
{
    /**
     * A spreadsheet cell: null as empty, booleans as Yes/No, and values
     * starting with = + - @ (or a tab or carriage return) prefixed with '
     * so spreadsheet apps don't run them as formulas.
     */
    public static function cell(string|int|float|bool|null $value): string
    {
        $value = match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'Yes' : 'No',
            default => (string) $value,
        };

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }

    /**
     * Writes one row to a stream.
     *
     * @param  resource  $stream
     * @param  list<string|int|float|bool|null>  $cells
     */
    public static function write($stream, array $cells): void
    {
        fputcsv($stream, array_map(self::cell(...), $cells), escape: '');
    }
}
