<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Formats prices for the page's language without the intl extension: the currency pattern and separators
 * are translation lines (lang/{locale}/site.php: price.format, number.thousands, number.decimal), so
 * English shows "IDR 12,500,000,000" and Indonesian "Rp 12.500.000.000".
 */
final class Money
{
    public static function format(int|float|string $amount): string
    {
        $number = number_format(
            (float) $amount,
            0,
            (string) __('site.number.decimal'),
            (string) __('site.number.thousands'),
        );

        return (string) __('site.price.format', ['amount' => $number]);
    }

    /** A short form for tight spaces: "IDR 12.5 bn" / "Rp 12,5 M". */
    public static function short(int|float|string $amount): string
    {
        $amount = (float) $amount;
        foreach ([1_000_000_000_000 => 'trillion', 1_000_000_000 => 'billion', 1_000_000 => 'million'] as $step => $unit) {
            if ($amount >= $step) {
                $value = rtrim(rtrim(number_format($amount / $step, 1, (string) __('site.number.decimal'), ''), '0'), (string) __('site.number.decimal'));

                return (string) __('site.price.short.'.$unit, ['amount' => $value]);
            }
        }

        return self::format($amount);
    }
}
