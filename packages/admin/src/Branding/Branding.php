<?php

declare(strict_types=1);

namespace Sunrice\Admin\Branding;

use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Panel;

/**
 * What the admin pages show of the brand: Settings → Branding over the
 * panel's own.
 *
 * @internal
 */
final class Branding
{
    /** @return array{brand: string, logo: string|null, color: string|null, colorText: string|null, login: array{headline: string|null, text: string|null}} */
    public static function shared(Panel $panel): array
    {
        $settings = app(BrandingSettings::class);
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', $settings->color) === 1 ? strtolower($settings->color) : null;
        $logo = $settings->logo === null ? null : Media::query()->find($settings->logo);

        return [
            'brand' => trim($settings->name) !== '' ? trim($settings->name) : $panel->getBrand(),
            'logo' => $logo instanceof Media && $logo->isImage() ? $logo->url() : null,
            'color' => $color,
            'colorText' => $color === null ? null : self::readableOn($color),
            'login' => [
                'headline' => trim($settings->loginHeadline) !== '' ? trim($settings->loginHeadline) : null,
                'text' => trim($settings->loginText) !== '' ? trim($settings->loginText) : null,
            ],
        ];
    }

    /** Dark or light text, whichever reads better on the colour. */
    public static function readableOn(string $hex): string
    {
        [$r, $g, $b] = array_map(fn (string $pair): float => hexdec($pair) / 255, str_split(substr($hex, 1), 2));
        $linear = fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $luminance = 0.2126 * $linear($r) + 0.7152 * $linear($g) + 0.0722 * $linear($b);

        // Contrast with white against contrast with near-black.
        return (1.05 / ($luminance + 0.05)) >= (($luminance + 0.05) / 0.0584) ? '#ffffff' : '#171717';
    }
}
