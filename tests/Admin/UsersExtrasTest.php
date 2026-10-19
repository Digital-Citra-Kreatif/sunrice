<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Security\AccountGuard;
use Sunrice\Admin\Security\SecurityLog;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    Storage::fake('public');
    config(['inertia.testing.ensure_pages_exist' => false]);
    usePanel();
    app(SyncPermissions::class)->handle();
    $this->admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret-password'])->assignRole('super-admin');
    $this->rina = User::query()->create(['name' => 'Rina', 'email' => 'rina@example.com', 'password' => 'secret-password']);
});

it('asks for a new user\'s password twice', function () {
    actingAs($this->admin);

    post('/admin/users', ['name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'a-Long-password-1', 'password_confirmation' => 'something else'])
        ->assertSessionHasErrors('password');
    post('/admin/users', ['name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'a-Long-password-1', 'password_confirmation' => 'a-Long-password-1'])
        ->assertSessionHasNoErrors();
    get('/admin/users/create')->assertInertia(fn (Assert $page) => $page->where('sections.0.fields.2.confirmed', true));
});

it('shows a user\'s security and what they own beside the form', function () {
    app(SecurityLog::class)->record('logged in', $this->rina, 'Rina');
    app(AccountGuard::class)->lock($this->rina, 'Left the company');
    app(UploadMedia::class)->handle(UploadedFile::fake()->image('a.png'), ['uploaded_by' => (string) $this->rina->id]);
    actingAs($this->admin);

    get("/admin/users/{$this->rina->id}/edit")->assertInertia(fn (Assert $page) => $page
        ->where('aside.0.label', 'Last login')->where('aside.0.value', fn (string $value) => str_contains($value, 'ago'))
        ->where('aside.1', fn ($fact) => $fact['label'] === 'Locked' && str_ends_with($fact['value'], 'Left the company'))
        ->where('aside', fn ($aside) => collect($aside)->firstWhere('label', 'Owns')['value'] === '1 file'));
    get('/admin/users/create')->assertInertia(fn (Assert $page) => $page->where('aside', []));
});

it('hands what a deleted user owned to whoever deletes them', function () {
    $file = app(UploadMedia::class)->handle(UploadedFile::fake()->image('a.png'), ['uploaded_by' => (string) $this->rina->id]);
    actingAs($this->admin);

    delete("/admin/users/{$this->rina->id}")->assertRedirect();

    expect(User::query()->find($this->rina->id))->toBeNull()
        ->and(Media::query()->find($file->id)?->uploaded_by)->toBe((string) $this->admin->id)
        ->and(Activity::query()->where('action', 'handed over')->sole()->properties['note'])->toBe('1 file to Admin');
});
