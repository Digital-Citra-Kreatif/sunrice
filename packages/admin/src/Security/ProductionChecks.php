<?php

declare(strict_types=1);

namespace Sunrice\Admin\Security;

/**
 * Settings that are dangerous on a live site, shown to super admins at
 * the top of each panel page they load (see the production checklist in
 * the installation docs): debug on in production (error pages show code,
 * settings and secrets to anyone) and an http:// APP_URL in production
 * (links in emails and the sitemap aren't encrypted).
 *
 * @internal
 */
final class ProductionChecks
{
    /** @return list<string> what's wrong, in the panel's language; none outside production */
    public static function warnings(): array
    {
        if (config('app.env') !== 'production') {
            return [];
        }
        $warnings = [];
        if (config('app.debug') === true) {
            $warnings[] = self::said('APP_DEBUG is on in production: error pages show code, settings and secrets to anyone. Set APP_DEBUG=false in .env, then run php artisan config:cache.');
        }
        $url = config('app.url');
        if (is_string($url) && str_starts_with(strtolower($url), 'http://')) {
            $warnings[] = self::said('APP_URL starts with http:// in production, so links in emails and the sitemap aren\'t encrypted. Serve the site over HTTPS and set APP_URL to its https:// address.');
        }

        return $warnings;
    }

    private static function said(string $text): string
    {
        $said = __($text);

        return is_string($said) ? $said : $text;
    }
}
