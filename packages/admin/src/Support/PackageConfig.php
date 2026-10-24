<?php

declare(strict_types=1);

namespace Sunrice\Admin\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;

/**
 * A package's config file merged under the app's published copy, key by
 * key at every depth: Laravel's `mergeConfigFrom()` merges only the top
 * level, so a file published on an older version, which has `auth` or
 * `site` already, missed every key added under them since (and their
 * `env()` reads). Here a key the app's file doesn't have gets the
 * package's default; a key it has keeps the app's value, `null` and `[]`
 * included.
 *
 * Lists (`['cms.', 'pages.']`) are values as a whole: the app's list is
 * never merged index by index with the package's. Maps whose keys are the
 * app's own choice (image sizes, CSP sources by directive) are named in
 * `$whole` by their dot path, so a key the app removed doesn't come back.
 * Nothing is merged while the configuration is cached.
 *
 * In a plugin's service provider, `register()`:
 *
 *     PackageConfig::merge($this->app, __DIR__.'/../config/shop.php', 'shop', whole: ['currencies']);
 *
 * @api
 */
final class PackageConfig
{
    /**
     * @param  string  $path  the package's config file, returning an array
     * @param  string  $key  where it lives (`config('shop')`)
     * @param  list<string>  $whole  dot paths, under $key, of maps the app's value replaces as a whole
     */
    public static function merge(Application $app, string $path, string $key, array $whole = []): void
    {
        if ($app->configurationIsCached()) {
            return;
        }

        /** @var Repository $config */
        $config = $app->make('config');
        $defaults = require $path;
        if (! is_array($defaults)) {
            return;
        }
        $current = $config->get($key);

        $config->set($key, is_array($current) ? self::mergeArrays($defaults, $current, $whole) : $defaults);
    }

    /**
     * The app's values with the defaults' missing keys added, at every
     * depth but in lists and the `$whole` paths.
     *
     * @param  array<array-key, mixed>  $defaults
     * @param  array<array-key, mixed>  $values
     * @param  list<string>  $whole
     * @return array<array-key, mixed>
     */
    private static function mergeArrays(array $defaults, array $values, array $whole = [], string $prefix = ''): array
    {
        foreach ($defaults as $name => $default) {
            $path = $prefix.$name;
            if (! array_key_exists($name, $values)) {
                $values[$name] = $default;
            } elseif (is_array($default) && is_array($values[$name]) && self::isMap($default) && self::isMap($values[$name]) && ! in_array($path, $whole, true)) {
                $values[$name] = self::mergeArrays($default, $values[$name], $whole, $path.'.');
            }
        }

        return $values;
    }

    /** @param  array<array-key, mixed>  $array */
    private static function isMap(array $array): bool
    {
        return $array !== [] && ! array_is_list($array);
    }
}
