<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Install;

use ReflectionClass;
use Spatie\Permission\Traits\HasRoles;

/**
 * Gives the app's user model spatie/laravel-permission's HasRoles trait,
 * which the panel needs for roles: imports it and adds `use HasRoles;` as
 * the first line of the class. Used by `sunrice:install`.
 *
 *     app(AddHasRoles::class)->handle(App\Models\User::class);
 *
 * Returns whether the file was changed: false when the model already has
 * the trait, and when its file doesn't look like a class it can edit
 * (then add the trait by hand).
 *
 * @internal
 */
final class AddHasRoles
{
    /** @param class-string $model */
    public function handle(string $model): bool
    {
        if (in_array(HasRoles::class, class_uses_recursive($model), true)) {
            return false;
        }
        $file = (new ReflectionClass($model))->getFileName();
        if ($file === false || ! is_writable($file)) {
            return false;
        }
        $code = $this->edit((string) file_get_contents($file), class_basename($model));
        if ($code === null) {
            return false;
        }

        return file_put_contents($file, $code) !== false;
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
}
