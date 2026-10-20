<?php

declare(strict_types=1);

namespace Sunrice\Admin\Assets;

use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;

/**
 * The panel's own built assets. They are built into the package's dist/
 * and published to public/vendor/sunrice-admin (`php artisan sunrice:assets`).
 *
 * @internal
 */
final class AdminAssets
{
    public const BUILD_DIRECTORY = 'vendor/sunrice-admin';

    public const ENTRY = 'resources/js/app.tsx';

    /** The package's dist/ folder. */
    public static function distPath(): string
    {
        return dirname(__DIR__, 2).'/dist';
    }

    /** The published copy, or the package's own build when running in place. */
    public static function manifestPath(): ?string
    {
        foreach ([public_path(self::BUILD_DIRECTORY.'/manifest.json'), self::distPath().'/manifest.json'] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public static function published(): bool
    {
        return is_file(public_path(self::BUILD_DIRECTORY.'/manifest.json')) || is_file(public_path(self::BUILD_DIRECTORY.'/hot'));
    }

    /** Whether the published copy differs from the package's build (an update without re-publishing). */
    public static function outdated(): bool
    {
        $published = public_path(self::BUILD_DIRECTORY.'/manifest.json');
        $built = self::distPath().'/manifest.json';

        return is_file($published) && is_file($built) && md5_file($published) !== md5_file($built);
    }

    /** @var array<string, string|null> the manifests' hashes, once per process */
    private static array $versions = [];

    /** The asset version Inertia sends with every page, so browsers reload after an update (hashed once per process). */
    public static function version(): ?string
    {
        $manifest = self::manifestPath();
        if ($manifest === null) {
            return null;
        }
        if (! array_key_exists($manifest, self::$versions)) {
            self::$versions[$manifest] = md5_file($manifest) ?: null;
        }

        return self::$versions[$manifest];
    }

    /** The web font's stylesheet (config `sunrice-admin.font.stylesheet`), if one is loaded. */
    public static function fontStylesheet(): ?string
    {
        $href = config('sunrice-admin.font.stylesheet');

        return is_string($href) && $href !== '' ? $href : null;
    }

    /** The font stylesheet's origin, to connect to before the stylesheet is asked for. */
    public static function fontOrigin(): ?string
    {
        $href = self::fontStylesheet();
        $parts = $href === null ? false : parse_url($href);

        return is_array($parts) && isset($parts['scheme'], $parts['host']) ? $parts['scheme'].'://'.$parts['host'] : null;
    }

    /** The font family the panel uses instead of Inter (config `sunrice-admin.font.family`), safe for a CSS string. */
    public static function fontFamily(): ?string
    {
        $family = config('sunrice-admin.font.family');
        if (! is_string($family)) {
            return null;
        }
        $family = trim((string) preg_replace('/[^\w\s-]/u', '', $family));

        return $family === '' ? null : $family;
    }

    /** The script and style tags for the panel's entry point. */
    public static function tags(): HtmlString
    {
        if (! self::published()) {
            return new HtmlString('');
        }

        $vite = (new Vite)
            ->useBuildDirectory(self::BUILD_DIRECTORY)
            ->useHotFile(public_path(self::BUILD_DIRECTORY.'/hot'));

        return $vite(self::ENTRY);
    }
}
