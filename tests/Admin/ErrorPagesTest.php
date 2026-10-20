<?php

declare(strict_types=1);

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Assets\AdminAssets;
use Sunrice\Admin\Panel;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\withHeaders;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->routes(function () {
        Route::get('reports/broken', fn () => throw new RuntimeException('boom'))->name('reports.broken');
        Route::get('reports/expired', fn () => throw new TokenMismatchException)->name('reports.expired');
        Route::get('reports/missing', fn () => abort(404))->name('reports.missing');
    }));
    // Outside the panel: the framework's own pages.
    Route::get('elsewhere', fn () => abort(403))->middleware('web');
    app('router')->getRoutes()->refreshNameLookups();
    $this->user = adminUser(Panel::ACCESS);
});

it('renders 403, 404, 419 and 500 under the panel path as the Error page in the layout', function () {
    config(['app.debug' => false]);
    actingAs($this->user);

    get('/admin/users')->assertForbidden()->assertInertia(fn (Assert $page) => $page
        ->component('Error', false)
        ->where('status', 403)
        ->where('auth.user.id', $this->user->id)
        ->has('navigation'));
    get('/admin/reports/missing')->assertNotFound()->assertInertia(fn (Assert $page) => $page->component('Error', false)->where('status', 404));
    get('/admin/users/999999/edit')->assertNotFound()->assertInertia(fn (Assert $page) => $page->where('status', 404)); // a missing record
    get('/admin/no/such/page')->assertNotFound()->assertInertia(fn (Assert $page) => $page->where('status', 404)->where('auth.user.id', $this->user->id));
    get('/admin/reports/expired')->assertStatus(419)->assertInertia(fn (Assert $page) => $page->where('status', 419));
    get('/admin/reports/broken')->assertStatus(500)->assertInertia(fn (Assert $page) => $page->where('status', 500));

    // Inertia visits get the page as JSON, with the status.
    withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) AdminAssets::version(), 'X-Requested-With' => 'XMLHttpRequest'])->get('/admin/users')
        ->assertForbidden()->assertJsonPath('component', 'Error')->assertJsonPath('props.status', 403);
});

it('keeps JSON answers, the debug page, and pages outside the panel', function () {
    actingAs($this->user);

    getJson('/admin/users')->assertForbidden()->assertJsonMissingPath('component');
    getJson('/admin/no/such/page')->assertNotFound()->assertJsonMissingPath('component');

    config(['app.debug' => true]);
    $inertia = 'data-'.'page=';
    expect(get('/admin/reports/broken')->assertStatus(500)->getContent())->not->toContain($inertia);
    expect(get('/elsewhere')->assertForbidden()->getContent())->not->toContain($inertia);
    expect(get('/admin/users')->assertForbidden()->getContent())->toContain($inertia);
});

it('sends guests to the login page instead of an error page', function () {
    get('/admin/no/such/page')->assertRedirect('/admin/login');
    get('/admin/users')->assertRedirect('/admin/login');
});

it('still records refused pages as security events', function () {
    actingAs($this->user);
    get('/admin/users')->assertForbidden()->assertInertia(fn (Assert $page) => $page->component('Error', false));

    expect(Activity::query()->where('action', 'page refused')->pluck('subject_id')->all())->toBe([(string) $this->user->id]);
});
