<?php

declare(strict_types=1);

use Illuminate\Contracts\Foundation\Application;
use Sunrice\Admin\Support\PackageConfig;

/*
 * A config/sunrice-admin.php published on 0.1 has `auth`, `media` and
 * `security`, without the keys added under them since: they get the
 * package's defaults (and env variables), key by key.
 */

function adminConfigFile(): string
{
    return dirname(__DIR__, 2).'/packages/admin/config/sunrice-admin.php';
}

/** @param  array<string, string>  $variables */
function withPackageConfigEnv(array $variables, Closure $test): void
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

/** A published 0.1 file's sections, trimmed. */
function publishedAdminConfig(): array
{
    return [
        'locales' => ['en' => 'English'],
        'auth' => ['passwords' => null, 'two_factor' => null, 'throttle' => ['attempts' => 3]],
        'media' => [
            'disk' => 'public',
            'directory' => 'uploads',
            'allowed_extensions' => ['jpg', 'png'],
            'image_sizes' => ['thumbnail' => [200, 200, 'crop']],
            'queue' => null,
        ],
        'security' => [
            'headers' => ['csp' => null],
            'alerts' => ['enabled' => true, 'roles' => ['security'], 'emails' => []],
        ],
        'ai' => ['commands' => ['cache:clear', 'sunrice:activity-prune' => ['--days']]],
    ];
}

it('adds the keys a published 0.1 config file lacks, with their env variables', function () {
    withPackageConfigEnv([
        'SUNRICE_CSP_ENABLED' => 'false',
        'SUNRICE_CSP_REPORT_ONLY' => 'true',
        'SUNRICE_CSP_REPORT_URI' => 'https://example.com/csp',
        'SUNRICE_PASSWORD_UNCOMPROMISED' => 'false',
    ], function () {
        config(['sunrice-admin' => publishedAdminConfig()]);

        PackageConfig::merge(app(), adminConfigFile(), 'sunrice-admin');

        expect(config('sunrice-admin.security.csp'))->toBe(['enabled' => false, 'report_only' => true, 'report_uri' => 'https://example.com/csp', 'sources' => []])
            ->and(config('sunrice-admin.auth.password_uncompromised'))->toBeFalse()
            ->and(config('sunrice-admin.auth.password_min'))->toBe(12)
            ->and(config('sunrice-admin.auth.strong_passwords'))->toBeNull()
            ->and(config('sunrice-admin.auth.throttle.per_ip'))->toBe(20)
            ->and(config('sunrice-admin.media.trash_disk'))->toBe('local')
            ->and(config('sunrice-admin.media.trash_directory'))->toBe('sunrice-media-trash')
            ->and(config('sunrice-admin.media.optimize.quality'))->toBe(82)
            ->and(config('sunrice-admin.security.alerts.cooldown_minutes'))->toBe(60)
            ->and(config('sunrice-admin.captcha.provider'))->toBe('turnstile');
    });
});

it('keeps what the app set, null and lists as a whole included', function () {
    config(['sunrice-admin' => publishedAdminConfig()]);

    PackageConfig::merge(app(), adminConfigFile(), 'sunrice-admin', whole: ['locales', 'media.image_sizes', 'ai.commands']);

    expect(config('sunrice-admin.media.directory'))->toBe('uploads')
        ->and(config('sunrice-admin.auth.throttle.attempts'))->toBe(3)
        ->and(config('sunrice-admin.auth.passwords'))->toBeNull()
        ->and(config('sunrice-admin.security.headers.csp'))->toBeNull()
        ->and(config('sunrice-admin.media.queue'))->toBeNull()
        ->and(config('sunrice-admin.media.allowed_extensions'))->toBe(['jpg', 'png'])
        ->and(config('sunrice-admin.security.alerts.roles'))->toBe(['security'])
        ->and(config('sunrice-admin.security.alerts.emails'))->toBe([])
        ->and(config('sunrice-admin.ai.commands'))->toBe(['cache:clear', 'sunrice:activity-prune' => ['--days']])
        // Maps named as whole keep the app's keys only.
        ->and(config('sunrice-admin.locales'))->toBe(['en' => 'English'])
        ->and(config('sunrice-admin.media.image_sizes'))->toBe(['thumbnail' => [200, 200, 'crop']]);
});

it('keeps an app value of another type, and an app section set to null', function () {
    config(['sunrice-admin' => ['font' => null, 'locks' => 'redis']]);

    PackageConfig::merge(app(), adminConfigFile(), 'sunrice-admin');

    expect(config('sunrice-admin.font'))->toBeNull()
        ->and(config('sunrice-admin.locks'))->toBe('redis')
        ->and(config('sunrice-admin.activity.keep_days'))->toBe(365);
});

it('gives an app without a published file the package\'s whole config', function () {
    config(['sunrice-admin' => null]);

    PackageConfig::merge(app(), adminConfigFile(), 'sunrice-admin');

    expect(config('sunrice-admin'))->toBe(require adminConfigFile());
});

it('merges nothing while the configuration is cached', function () {
    $app = Mockery::mock(Application::class);
    $app->shouldReceive('configurationIsCached')->once()->andReturnTrue();
    $app->shouldNotReceive('make');

    PackageConfig::merge($app, adminConfigFile(), 'sunrice-admin');
});
