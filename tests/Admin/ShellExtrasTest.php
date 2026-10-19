<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Mail\SecurityAlert;
use Sunrice\Admin\Panel;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travel;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
    usePanel(fn (Panel $panel) => $panel->aiAccess());
    app(SyncPermissions::class)->handle();
});

it('creates, lists and revokes AI tokens from the command line', function () {
    $rina = adminUser(Panel::ACCESS, 'sunrice.ai.connect');

    artisan('sunrice:ai-token', ['email' => $rina->email, '--name' => 'Server agent', '--read-only' => true, '--days' => 30])
        ->assertSuccessful()->expectsOutputToContain('sra_')->expectsOutputToContain('Endpoint');
    $token = AiToken::query()->sole();
    expect($token->name)->toBe('Server agent')->and($token->read_only)->toBeTrue()->and($token->expires_at?->isFuture())->toBeTrue();

    artisan('sunrice:ai-token', ['email' => $rina->email, '--list' => true])->assertSuccessful()->expectsOutputToContain('Server agent');
    artisan('sunrice:ai-token', ['email' => $rina->email, '--revoke' => true])->assertSuccessful()->expectsOutputToContain('Revoked 1 token.');
    expect(AiToken::query()->count())->toBe(0);

    artisan('sunrice:ai-token', ['email' => 'nobody@example.com'])->assertFailed();
    artisan('sunrice:ai-token', ['email' => adminUser()->email])->assertSuccessful()->expectsOutputToContain('may not connect AI agents');
});

it('deletes old activity log lines from the page, and alerts', function () {
    Mail::fake();
    User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret-password'])->assignRole('super-admin');
    app(ActivityLog::class)->record('created', 'product', null, 'Old');
    travel(40)->days();
    app(ActivityLog::class)->record('created', 'product', null, 'New');
    actingAs(adminUser(Panel::ACCESS, 'sunrice.activity.view', 'sunrice.activity.prune'));

    get('/admin/activity')->assertInertia(fn (Assert $page) => $page->where('can.prune', true));
    post('/admin/activity/prune', ['days' => 0])->assertSessionHasErrors('days');
    post('/admin/activity/prune', ['days' => 30])->assertSessionHas('success', 'Deleted 1 line older than 30 days.');

    expect(Activity::query()->where('subject_label', 'Old')->exists())->toBeFalse()
        ->and(Activity::query()->where('subject_label', 'New')->exists())->toBeTrue();
    Mail::assertSent(SecurityAlert::class, fn (SecurityAlert $mail) => $mail->headline === 'Activity log lines were deleted' && $mail->hasTo('admin@example.com'));

    actingAs(adminUser(Panel::ACCESS, 'sunrice.activity.view'));
    get('/admin/activity')->assertInertia(fn (Assert $page) => $page->where('can.prune', false));
    post('/admin/activity/prune', ['days' => 30])->assertForbidden();
});
