<?php

declare(strict_types=1);

namespace Sunrice\Admin\Support;

use Illuminate\Support\Collection;
use Sunrice\Admin\Assets\AdminAssets;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Settings\BrandingSettings;

/**
 * The panel's branding as shown: Settings → Branding with the panel's own
 * values for what's left empty. Used by the admin layout (font, colour
 * and browser icon) and the shared page data. Panel::brandName() is the
 * public way to read the name.
 *
 * @internal
 */
final class Branding
{
    public const SYSTEM_FONT = 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif';

    /** The lettermark icon's background without a brand colour: the neutral palette's primary. */
    public const NEUTRAL = '#2a2622';

    private const HEX = '/^#[0-9a-f]{6}$/';

    /** @var Collection<int, Media>|null the chosen images by id, loaded once */
    private ?Collection $media = null;

    public function __construct(private Panel $panel, private BrandingSettings $settings) {}

    public static function make(): self
    {
        return new self(app(Panel::class), app(BrandingSettings::class));
    }

    public function name(): string
    {
        $name = trim($this->settings->name);

        return $name !== '' ? $name : $this->panel->getBrand();
    }

    public function tagline(): string
    {
        return trim($this->settings->tagline);
    }

    /** The name's first letter, shown where there's no logo. */
    public function lettermark(): string
    {
        return mb_strtoupper(mb_substr($this->name(), 0, 1)) ?: '?';
    }

    /** The chosen font's key, or null for the `sunrice-admin.font` config. */
    public function font(): ?string
    {
        return array_key_exists($this->settings->font, BrandingSettings::FONTS) ? $this->settings->font : null;
    }

    /** The web font's stylesheet: the chosen font's, else the config's. */
    public function fontStylesheet(): ?string
    {
        $font = $this->font();
        if ($font === null) {
            return AdminAssets::fontStylesheet();
        }
        $slug = BrandingSettings::FONTS[$font][1];

        return $slug === null ? null : "https://fonts.bunny.net/css?family={$slug}:400,500,600&display=swap";
    }

    /** The stylesheet's origin, to connect to early. */
    public function fontOrigin(): ?string
    {
        $href = $this->fontStylesheet();
        $parts = $href === null ? false : parse_url($href);

        return is_array($parts) && isset($parts['scheme'], $parts['host']) ? $parts['scheme'].'://'.$parts['host'] : null;
    }

    /** The CSS value for --sunrice-font, or null to keep the stylesheet's (Inter). */
    public function fontFamily(): ?string
    {
        $font = $this->font();
        if ($font === null) {
            $family = AdminAssets::fontFamily();

            return $family === null ? null : "\"{$family}\"";
        }

        return BrandingSettings::FONTS[$font][1] === null ? self::SYSTEM_FONT : '"'.BrandingSettings::FONTS[$font][0].'"';
    }

    /** The brand colour as #rrggbb, or null for the neutral palette. */
    public function color(): ?string
    {
        $color = strtolower(trim($this->settings->color));

        return preg_match(self::HEX, $color) === 1 ? $color : null;
    }

    /**
     * CSS custom properties for the admin layout's <style>: the font and
     * the brand colour in light and dark mode. Only known fonts and
     * validated colours get in, so it is safe to print unescaped.
     */
    public function css(): string
    {
        $css = '';
        $family = $this->fontFamily();
        if ($family !== null) {
            $css .= "html:root{--sunrice-font:{$family};}";
        }

        $color = $this->color();
        if ($color !== null) {
            // Dark mode: lighter until it stands out on the dark surfaces.
            $dark = self::lighten($color, 0.3);
            // html:root and html.dark outrank admin.css's :root and .dark, which load after this.
            $css .= 'html:root{'.self::variables($color).'}html.dark{'.self::variables($dark).'}';
        }

        return $css;
    }

    /**
     * Black or white, whichever has more contrast on `$hex` (WCAG relative
     * luminance).
     */
    public static function readableOn(string $hex): string
    {
        $luminance = self::luminance($hex);

        return (1.05 / ($luminance + 0.05)) >= (($luminance + 0.05) / 0.05) ? '#ffffff' : '#111111';
    }

    /** WCAG relative luminance of #rrggbb, 0 (black) to 1 (white). */
    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = self::channels($hex);
        $linear = fn (int $c): float => ($c / 255) <= 0.03928 ? ($c / 255) / 12.92 : ((($c / 255) + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $linear($r) + 0.7152 * $linear($g) + 0.0722 * $linear($b);
    }

    /** `$hex` mixed with white, 10% at a time, until its luminance is at least `$min`. */
    public static function lighten(string $hex, float $min): string
    {
        [$r, $g, $b] = self::channels($hex);
        $mixed = $hex;
        for ($white = 0; $white <= 100 && self::luminance($mixed) < $min; $white += 10) {
            $mix = fn (int $c): int => (int) round($c + (255 - $c) * $white / 100);
            $mixed = sprintf('#%02x%02x%02x', $mix($r), $mix($g), $mix($b));
        }

        return $mixed;
    }

    /**
     * The browser icon: the chosen favicon, else the logo when it is
     * square-ish (or an SVG), else the lettermark (`lettermarkSvg()`).
     * `apple` is a square raster image for home screens, when there is
     * one. Runs at most one query, shared with toArray().
     *
     * @return array{href: string, type: string, apple: string|null}
     */
    public function icon(): array
    {
        $favicon = $this->image($this->settings->favicon);
        $logo = $this->image($this->settings->logo);
        // An SVG without known dimensions is taken at its word.
        $squareLogo = $logo !== null && (self::squareish($logo) || (self::isSvg($logo) && ($logo->width === null || $logo->height === null))) ? $logo : null;
        $icon = $favicon ?? $squareLogo;
        $apple = null;
        foreach ([$favicon, $squareLogo] as $candidate) {
            if ($candidate !== null && $candidate->isResizable() && self::squareish($candidate)) {
                $apple = $candidate->url();
                break;
            }
        }

        if ($icon === null) {
            return ['href' => route('sunrice.admin.branding.icon', ['v' => $this->lettermarkVersion()]), 'type' => 'image/svg+xml', 'apple' => $apple];
        }

        return ['href' => $icon->url(), 'type' => $icon->mime_type, 'apple' => $apple];
    }

    /**
     * The lettermark as a square SVG: the name's first letter on the brand
     * colour (or the neutral one). Holds nothing else.
     */
    public function lettermarkSvg(): string
    {
        $color = $this->color() ?? self::NEUTRAL;
        $on = self::readableOn($color);
        $letter = htmlspecialchars($this->lettermark(), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            ."<rect width=\"64\" height=\"64\" rx=\"14\" fill=\"{$color}\"/>"
            ."<text x=\"32\" y=\"33\" text-anchor=\"middle\" dominant-baseline=\"central\" font-family=\"ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif\" font-size=\"38\" font-weight=\"600\" fill=\"{$on}\">{$letter}</text>"
            .'</svg>';
    }

    /** Changes with the lettermark's letter and colour, so its URL does too. */
    public function lettermarkVersion(): string
    {
        return substr(sha1($this->lettermark().'|'.($this->color() ?? '')), 0, 8);
    }

    /**
     * For the frontend (`sunrice.branding`); runs one query for the logo,
     * sign-in image and browser icon, and only when one is set.
     *
     * @return array{name: string, tagline: string, logo: string|null, lettermark: string, color: string|null, login: array{layout: string, eyebrow: string, headline: string, text: string, footer: string, image: string|null}}
     */
    public function toArray(): array
    {
        $url = fn (?int $id): ?string => $this->image($id)?->url();
        $text = fn (string $value, string $key): string => trim($value) !== '' ? trim($value) : Say::it(BrandingSettings::LOGIN_DEFAULTS[$key]);

        return [
            'name' => $this->name(),
            'tagline' => $this->tagline(),
            'logo' => $url($this->settings->logo),
            'lettermark' => $this->lettermark(),
            'color' => $this->color(),
            'login' => [
                'layout' => array_key_exists($this->settings->loginLayout, BrandingSettings::LOGIN_LAYOUTS) ? $this->settings->loginLayout : 'split',
                'eyebrow' => $text($this->settings->loginEyebrow, 'eyebrow'),
                'headline' => $text($this->settings->loginHeadline, 'headline'),
                'text' => $text($this->settings->loginText, 'text'),
                'footer' => $text($this->settings->loginFooter, 'footer'),
                'image' => $url($this->settings->loginImage),
            ],
        ];
    }

    /** The chosen image with this id, if it is (still) an image in the library. */
    private function image(?int $id): ?Media
    {
        if ($id === null) {
            return null;
        }
        if ($this->media === null) {
            $ids = array_values(array_filter([$this->settings->logo, $this->settings->loginImage, $this->settings->favicon], fn (?int $id) => $id !== null));
            $this->media = Media::query()->whereIn('id', $ids)->get()->keyBy('id');
        }
        $file = $this->media->get($id);

        return $file instanceof Media && $file->isImage() ? $file : null;
    }

    /** Width and height within a quarter of each other. */
    private static function squareish(Media $media): bool
    {
        if ($media->width === null || $media->height === null || $media->width <= 0 || $media->height <= 0) {
            return false;
        }

        return max($media->width, $media->height) / min($media->width, $media->height) <= 1.25;
    }

    private static function isSvg(Media $media): bool
    {
        return in_array($media->mime_type, ['image/svg+xml', 'image/svg'], true);
    }

    private static function variables(string $color): string
    {
        $on = self::readableOn($color);

        return "--primary:{$color};--primary-foreground:{$on};"
            ."--ring:color-mix(in oklch,{$color} 70%,var(--background));"
            ."--sidebar-primary:{$color};--sidebar-primary-foreground:{$on};"
            .'--sidebar-ring:var(--ring);';
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function channels(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }
}
