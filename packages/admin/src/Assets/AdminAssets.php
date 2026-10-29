<?php

declare(strict_types=1);

namespace Sunrice\Admin\Assets;

use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;
use Sunrice\Admin\Panel;

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

    /**
     * What's published older than the installed packages: "Sunrice" for
     * the panel's own, and the display name of each plugin bundle that said
     * where it's published from (PluginBundle::publishedFrom(), named()).
     * Super admins see a warning.
     *
     * @return list<string>
     */
    public static function stale(): array
    {
        $stale = self::outdated() ? ['Sunrice'] : [];
        foreach (app(Panel::class)->getBundles() as $bundle) {
            if ($bundle->outdated()) {
                $stale[] = $bundle->displayName();
            }
        }

        return $stale;
    }

    /**
     * The panel's own asset version: the md5 of its manifest, the published
     * copy's or the build's (stat'ed once per request, AssetVersions).
     */
    public static function version(): ?string
    {
        $manifest = self::manifestPath();

        return $manifest === null ? null : app(AssetVersions::class)->file($manifest);
    }

    /**
     * The version Inertia sends with every page: the panel's manifest and
     * each plugin bundle's files (PluginBundle::version()), so an update of
     * only a plugin's bundle reloads open tabs on their next visit too.
     */
    public static function inertiaVersion(Panel $panel): ?string
    {
        $bundles = $panel->getBundles();
        if ($bundles === []) {
            return self::version();
        }

        return md5(implode('|', [(string) self::version(), ...array_map(fn (PluginBundle $bundle): string => $bundle->id.':'.$bundle->version(), array_values($bundles))]));
    }

    /** The web font's stylesheet (config `sunrice-admin.font.stylesheet`), if one is loaded. */
    public static function fontStylesheet(): ?string
    {
        $href = config('sunrice-admin.font.stylesheet');

        return is_string($href) && $href !== '' ? $href : null;
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
        // The request's CSP nonce (PanelSecurityHeaders), which this instance doesn't share with the app's Vite.
        $nonce = app(Vite::class)->cspNonce();
        if (is_string($nonce) && $nonce !== '') {
            $vite->useCspNonce($nonce);
        }

        return $vite(self::ENTRY);
    }

    /** The Vite dev server's URL while the panel is developed (its hot file), else null. */
    public static function hotUrl(): ?string
    {
        $hot = public_path(self::BUILD_DIRECTORY.'/hot');
        $url = is_file($hot) ? trim((string) file_get_contents($hot)) : '';

        return $url === '' ? null : $url;
    }
}
