<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Install;

use Composer\Autoload\ClassLoader;
use Spatie\Permission\Traits\HasRoles;

/**
 * Gives the app's user model spatie/laravel-permission's HasRoles trait,
 * which the panel needs for roles: imports it and adds `use HasRoles;` as
 * the first line of the class. Used by `sunrice:install`, before anything
 * loads the model (PHP can't reload a class it has loaded).
 *
 *     $roles = app(AddHasRoles::class);
 *     if ($roles->missing($model)) $roles->handle($model);
 *     $roles->ready($model);   // loads it
 *
 * @internal
 */
final class AddHasRoles
{
    /** Whether the model lacks the trait. Doesn't load a model that isn't loaded yet: its file is read. */
    public function missing(string $model): bool
    {
        if (class_exists($model, false)) {
            return ! $this->ready($model);
        }
        $file = $this->fileOf($model);

        return $file !== null && ! str_contains((string) file_get_contents($file), 'HasRoles');
    }

    /**
     * Adds the trait to the model's file. False when the model is loaded
     * already, or its file isn't one it can edit (then add it by hand).
     */
    public function handle(string $model): bool
    {
        $file = class_exists($model, false) ? null : $this->fileOf($model);
        if ($file === null || ! is_writable($file)) {
            return false;
        }
        $code = $this->edit((string) file_get_contents($file), class_basename($model));

        return $code !== null && file_put_contents($file, $code) !== false;
    }

    /** Whether the model, as PHP loads it, has the trait. Loads it. */
    public function ready(string $model): bool
    {
        return class_exists($model) && in_array(HasRoles::class, class_uses_recursive($model), true);
    }

    /** The code with the trait added, or null when the class isn't found. */
    public function edit(string $code, string $class): ?string
    {
        if (preg_match('/^(?:final\s+|abstract\s+|readonly\s+)*class\s+'.preg_quote($class, '/').'\b[^{]*\{\n?/m', $code, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }
        $body = $match[0][1] + strlen($match[0][0]);
        $code = substr($code, 0, $body).'    use HasRoles;'.(str_ends_with($match[0][0], "\n") ? "\n" : "\n\n").substr($code, $body);

        if (str_contains($code, 'use '.HasRoles::class.';')) {
            return $code;
        }
        // The import, after the file's last import (or its namespace).
        $before = substr($code, 0, $match[0][1]);
        $at = null;
        preg_match_all('/^use [^;(]+;\n/m', $before, $imports, PREG_OFFSET_CAPTURE);
        foreach ($imports[0] as [$import, $offset]) {
            $at = $offset + strlen($import);
        }
        if ($at === null) {
            if (preg_match('/^namespace [^;]+;\n/m', $before, $namespace, PREG_OFFSET_CAPTURE) !== 1) {
                return null;
            }
            $at = $namespace[0][1] + strlen($namespace[0][0]) + 1;
            $code = substr($code, 0, $at - 1)."\n".substr($code, $at - 1);
        }

        return substr($code, 0, $at).'use '.HasRoles::class.";\n".substr($code, $at);
    }

    /** Where Composer would load the class from, without loading it. */
    private function fileOf(string $class): ?string
    {
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $file = $loader->findFile($class);
            if (is_string($file)) {
                return realpath($file) ?: $file;
            }
        }

        return null;
    }
}
