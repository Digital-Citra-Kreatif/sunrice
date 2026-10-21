<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Inertia\DevTools\DevTools;
use Sunrice\Admin\Panel;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('sends security headers on every panel response', function () {
    usePanel(fn (Panel $panel) => $panel->routes(fn () => Route::get('reports', fn () => 'reports')->name('reports')));

    $secured = fn (TestResponse $response) => $response
        ->assertHeader('X-Frame-Options', 'DENY')->assertHeader('Referrer-Policy', 'same-origin')->assertHeader('X-Content-Type-Options', 'nosniff');
    $secured(get('/admin/login')->assertOk())->assertHeaderMissing('Content-Security-Policy');
    $secured(get('/admin')->assertRedirect('/admin/login'));

    actingAs(adminUser(Panel::ACCESS));
    $secured(get('/admin')->assertOk());
    $secured(get('/admin/reports')->assertOk());
});

it('sends a Content-Security-Policy when the app sets one', function () {
    config(['sunrice-admin.security.headers.csp' => "default-src 'self'; frame-ancestors 'none'"]);
    usePanel();

    get('/admin/login')->assertOk()->assertHeader('Content-Security-Policy', "default-src 'self'; frame-ancestors 'none'");
});

it('turns Inertia DevTools off unless the app turns them on, so local installs don\'t serve every page\'s props to anyone', function () {
    usePanel();
    app()->detectEnvironment(fn () => 'local');

    expect(config('inertia.devtools.enabled'))->toBeFalse()
        ->and(DevTools::enabled())->toBeFalse();
    get('/_inertia/devtools/entries')->assertNotFound();
});
