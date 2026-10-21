<?php

declare(strict_types=1);

namespace Sunrice\Admin\Support;

/**
 * Links that leave the request, in emails: built from the app's URL
 * (`APP_URL`), never from the request's `Host` header, which whoever
 * sends the request chooses. A password reset link built from the
 * request could point at an attacker's host and hand them the token; a
 * moderation link in a mail sent while a visitor posts a comment could
 * point anywhere.
 *
 * @api
 */
final class AppUrl
{
    /**
     * An absolute link to a named route, on `APP_URL`.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function route(string $name, array $parameters = []): string
    {
        return self::rooted(route($name, $parameters, false));
    }

    /**
     * An absolute link (from `url()`, `route()`, an entry's `url()`) moved
     * from the current request's host to `APP_URL`. Links to other sites,
     * and every link when `APP_URL` isn't set, are returned as they are.
     */
    public static function url(string $url): string
    {
        $current = rtrim(url('/'), '/');
        if ($url === $current || str_starts_with($url, $current.'/') || str_starts_with($url, $current.'?') || str_starts_with($url, $current.'#')) {
            return self::rooted(substr($url, strlen($current)));
        }

        return $url;
    }

    private static function rooted(string $path): string
    {
        $root = config('app.url');

        return is_string($root) && $root !== '' ? rtrim($root, '/').'/'.ltrim($path, '/') : url($path);
    }
}
