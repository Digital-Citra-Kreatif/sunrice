<?php

declare(strict_types=1);

namespace Sunrice\Admin\Mail;

use Sunrice\Admin\Support\AppUrl;

/**
 * What the top of the emails the panel and its plugins send shows: a logo
 * (a PNG or JPEG at an absolute URL, never an SVG, which email clients
 * don't show), with the name as its alternative text, or the name alone.
 * `Panel::mailBrand()` gives the one in use; a plugin replaces the
 * panel's own (Settings → Branding's) with `$panel->mailBrandUsing()`.
 *
 *     $panel->mailBrandUsing(fn (): MailBrand => MailBrand::image('Acme', 'https://acme.test/logo-mail.png', 192, 96));
 *
 * `width` and `height` are the size it's shown at, in CSS pixels: fit in
 * HEIGHT × MAX_WIDTH, its proportions kept.
 *
 * @api
 */
final readonly class MailBrand
{
    /** The tallest a logo is shown, in CSS pixels; its image is best twice that, for sharp screens. */
    public const HEIGHT = 48;

    /** The widest a logo is shown, in CSS pixels. */
    public const MAX_WIDTH = 240;

    /**
     * @param  string|null  $logo  an absolute URL of a PNG, JPEG or GIF; null: the name alone
     */
    public function __construct(
        public string $name,
        public ?string $logo = null,
        public ?int $width = null,
        public ?int $height = null,
    ) {}

    /** The name alone, as text. */
    public static function name(string $name): self
    {
        return new self($name);
    }

    /**
     * The name with a logo, whose image is `$pixelWidth` × `$pixelHeight`:
     * shown at most HEIGHT tall and MAX_WIDTH wide. A relative URL
     * ("/storage/…") is made absolute on the app's URL (`APP_URL`), as
     * links in emails must be. An SVG, or an image without a size, gives
     * the name alone.
     */
    public static function image(string $name, string $url, int $pixelWidth, int $pixelHeight): self
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        if ($url === '' || $pixelWidth <= 0 || $pixelHeight <= 0 || str_ends_with($path, '.svg') || str_ends_with($path, '.svgz')) {
            return new self($name);
        }
        $scale = min(1, self::HEIGHT / $pixelHeight, self::MAX_WIDTH / $pixelWidth);

        return new self($name, self::absolute($url), max(1, (int) round($pixelWidth * $scale)), max(1, (int) round($pixelHeight * $scale)));
    }

    private static function absolute(string $url): string
    {
        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }
        if (preg_match('#^https?://#i', $url) === 1) {
            return AppUrl::url($url);
        }

        return AppUrl::url(url($url));
    }
}
