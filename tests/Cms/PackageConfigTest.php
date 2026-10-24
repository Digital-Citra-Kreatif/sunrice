<?php

declare(strict_types=1);

use Sunrice\Admin\Support\PackageConfig;
use Sunrice\Cms\CmsServiceProvider;

/*
 * A config/sunrice-cms.php published on 0.1 has `site`, without the keys
 * added under it since: they get the CMS's defaults and env variables,
 * while what the app set (its template prefixes, its headers) is kept.
 */

/** A published 0.1 file's `site`, trimmed. */
function publishedCmsConfig(): array
{
    return [
        'locales' => ['en', 'id'],
        'locale_names' => ['id' => 'Bahasa'],
        'site' => [
            'enabled' => true,
            'middleware' => ['web', 'throttle:60,1'],
            'template_prefixes' => ['cms.'],
            'fallback' => null,
            'headers' => ['X-Frame-Options' => 'DENY'],
        ],
        'search' => ['collections' => null],
    ];
}

/** Runs $test with env variables set. @param  array<string, string>  $variables */
function withCmsEnv(array $variables, Closure $test): void
{
    foreach ($variables as $name => $value) {
        putenv($name.'='.$value);
        $_ENV[$name] = $_SERVER[$name] = $value;
    }
    try {
        $test();
    } finally {
        foreach (array_keys($variables) as $name) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }
    }
}

function mergeCmsConfig(): void
{
    $provider = new ReflectionClass(CmsServiceProvider::class);
    // As the CMS's provider does.
    PackageConfig::merge(app(), dirname((string) $provider->getFileName(), 2).'/config/sunrice-cms.php', 'sunrice-cms', whole: ['locale_names', 'locale_prefixes', 'site.headers', 'site.csp.sources']);
}

it('adds the site keys a published 0.1 config file lacks, with their env variables', function () {
    withCmsEnv([
        'SUNRICE_SITE_CSP_ENABLED' => 'true',
        'SUNRICE_SITE_CSP_REPORT_ONLY' => 'true',
        'SUNRICE_SITE_CSP_REPORT_URI' => 'https://example.com/report',
        'SUNRICE_SITE_CODE' => 'false',
        'SUNRICE_LAZY_SESSIONS' => 'false',
    ], function () {
        config(['sunrice-cms' => publishedCmsConfig()]);

        mergeCmsConfig();

        expect(config('sunrice-cms.site.csp'))->toBe(['enabled' => true, 'report_only' => true, 'report_uri' => 'https://example.com/report', 'nonce' => true, 'sources' => []])
            ->and(config('sunrice-cms.site.custom_code'))->toBeFalse()
            ->and(config('sunrice-cms.site.lazy_sessions'))->toBeFalse()
            ->and(config('sunrice-cms.cache.preload.batch'))->toBe(25)
            ->and(config('sunrice-cms.search.per_page'))->toBe(10);
    });
});

it('keeps the app\'s lists, maps and nulls as they are', function () {
    config(['sunrice-cms' => publishedCmsConfig()]);

    mergeCmsConfig();

    expect(config('sunrice-cms.site.template_prefixes'))->toBe(['cms.'])
        ->and(config('sunrice-cms.site.middleware'))->toBe(['web', 'throttle:60,1'])
        ->and(config('sunrice-cms.locales'))->toBe(['en', 'id'])
        ->and(config('sunrice-cms.locale_names'))->toBe(['id' => 'Bahasa'])
        ->and(config('sunrice-cms.site.headers'))->toBe(['X-Frame-Options' => 'DENY'])
        ->and(config('sunrice-cms.site.fallback'))->toBeNull()
        ->and(config('sunrice-cms.search.collections'))->toBeNull();
});
