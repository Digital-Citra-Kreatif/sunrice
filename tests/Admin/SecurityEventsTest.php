<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Monolog\Handler\TestHandler;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Events\SecurityEventRecorded;
use Sunrice\Admin\Panel;
use Sunrice\Tests\Admin\Fixtures\Product;
use Sunrice\Tests\Admin\Fixtures\ProductResource;
use Sunrice\Tests\Admin\Fixtures\TestPlugin;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    Product::createTables();
    Storage::fake('public');
    usePanel(fn (Panel $panel) => $panel->plugin(new TestPlugin('shop', register: fn (Panel $p) => $p->resources(ProductResource::class))));
    app(SyncPermissions::class)->handle();
    $this->admin = User::query()->create(['name' => 'Rina', 'email' => 'rina@example.com', 'password' => 'secret-password'])->assignRole('super-admin');
    $this->customer = User::query()->create(['name' => 'Budi', 'email' => 'budi@shop.test', 'password' => 'secret-password']);
});

/** The security lines as [action, actor, subject label, note], oldest first. */
function securityLines(): array
{
    return Activity::query()->where('source', 'security')->where('action', '!=', 'alert sent')->orderBy('id')->get()
        ->map(fn (Activity $a) => [$a->action, $a->user_name, $a->subject_label, $a->properties['note'] ?? null])->all();
}

function attemptPanelLogin(string $email, string $password): TestResponse
{
    return post('/admin/login', ['email' => $email, 'password' => $password]);
}

it('records logins: in, failed, refused, out', function () {
    attemptPanelLogin('rina@example.com', 'wrong');
    attemptPanelLogin('nobody@example.com', 'wrong');
    attemptPanelLogin('budi@shop.test', 'secret-password'); // a customer: no access to the panel
    attemptPanelLogin('rina@example.com', 'secret-password')->assertRedirect('/admin');
    post('/admin/logout');

    expect(securityLines())->toBe([
        ['login failed', null, 'Rina', 'wrong password'],
        ['login failed', null, 'nobody@example.com', 'no account with this email'],
        ['login refused', null, 'Budi', 'no access to the panel'],
        ['logged in', 'Rina', 'Rina', null],
        ['logged out', 'Rina', 'Rina', null],
    ]);
    $line = Activity::query()->where('action', 'login failed')->first();
    expect($line)->subject_type->toBe('account')->subject_id->toBe((string) $this->admin->id)->ip_address->toBe('127.0.0.1')
        ->and($line->properties['user_agent'])->toBe('Symfony');
});

it('records login limits reached: blocked, and the account locked', function () {
    config(['sunrice-admin.auth.throttle' => ['attempts' => 2, 'per_ip' => 50, 'per_account' => 3, 'account_lock_seconds' => 900]]);
    attemptPanelLogin('rina@example.com', 'wrong');
    attemptPanelLogin('rina@example.com', 'wrong');
    test()->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])->post('/admin/login', ['email' => 'rina@example.com', 'password' => 'wrong']);

    expect(array_values(array_map(fn ($line) => [$line[0], $line[3]], array_filter(securityLines(), fn ($line) => $line[0] !== 'login failed'))))->toBe([
        ['login blocked', 'too many wrong passwords; blocked for 1 min'],
        ['account locked', 'too many wrong passwords from any IP; locked for 15 min'],
    ]);
});

it('records attempts made while blocked, at most once a minute per email and IP', function () {
    config(['sunrice-admin.auth.throttle' => ['attempts' => 2, 'per_ip' => 50, 'per_account' => 50]]);
    attemptPanelLogin('rina@example.com', 'wrong');
    attemptPanelLogin('rina@example.com', 'wrong');
    attemptPanelLogin('rina@example.com', 'wrong');
    attemptPanelLogin('rina@example.com', 'secret-password')->assertSessionHasErrors('email');
    foreach (range(1, 3) as $i) {
        test()->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])->post('/admin/login', ['email' => 'nobody@example.com', 'password' => 'x']);
    }

    expect(array_values(array_filter(securityLines(), fn ($line) => $line[0] !== 'login failed')))->toBe([
        ['login blocked', null, 'Rina', 'too many wrong passwords; blocked for 1 min'],
        ['login blocked', null, 'Rina', 'tried while blocked (pair)'],
        ['login blocked', null, 'nobody@example.com', 'too many wrong passwords; blocked for 1 min'],
        ['login blocked', null, 'nobody@example.com', 'tried while blocked (pair)'],
    ]);

    // A minute later, the next attempt while blocked is recorded again.
    $this->travel(61)->seconds();
    attemptPanelLogin('rina@example.com', 'wrong');
    attemptPanelLogin('rina@example.com', 'wrong');
    attemptPanelLogin('rina@example.com', 'wrong');
    expect(array_values(array_filter(securityLines(), fn ($line) => $line[3] === 'tried while blocked (pair)')))->toHaveCount(3);
});

it('records password resets', function () {
    Notification::fake();
    post('/admin/forgot-password', ['email' => 'rina@example.com']);
    post('/admin/forgot-password', ['email' => 'ghost@example.com']);
    $token = Password::broker()->createToken($this->admin);
    post('/admin/reset-password', ['token' => $token, 'email' => 'rina@example.com', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])->assertRedirect();

    expect(securityLines())->toBe([
        ['password reset requested', null, 'Rina', null],
        ['password reset requested', null, 'ghost@example.com', 'no account with this email'],
        ['password reset', null, 'Rina', null],
    ]);
});

it('records blocked uploads, exports and changes of roles and permissions', function () {
    actingAs($this->admin);
    post('/admin/media', ['file' => UploadedFile::fake()->createWithContent('shell.php', '<?php')])->assertSessionHasErrors('file');
    post('/admin/media', ['file' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')]);
    post('/admin/media', ['file' => UploadedFile::fake()->create('notes.exe.docx-not-allowed.xyz', 1)]); // merely not allowed: no event
    get('/admin/shop/products/export')->assertOk()->streamedContent();

    Role::findOrCreate('editor', 'web');
    put("/admin/users/{$this->customer->id}", ['name' => 'Budi', 'email' => 'budi@shop.test', 'roles' => ['editor']])->assertSessionHasNoErrors();
    $editor = Role::findByName('editor', 'web');
    put("/admin/roles/{$editor->id}", ['name' => 'editor', 'permissions' => [Panel::ACCESS, 'shop.products.view']])->assertSessionHasNoErrors();
    put("/admin/roles/{$editor->id}", ['name' => 'editor', 'permissions' => [Panel::ACCESS]])->assertSessionHasNoErrors();

    expect(securityLines())->toBe([
        ['upload blocked', 'Rina', 'Rina', 'shell.php: .php files are never accepted'],
        ['upload blocked', 'Rina', 'Rina', 'logo.svg: an SVG with scripts or links that run code'],
        ['exported', 'Rina', 'Rina', 'Products (CSV)'],
        ['roles changed', 'Rina', 'Budi', 'added: editor'],
        ['permissions changed', 'Rina', 'editor', 'added: sunrice.access, shop.products.view'],
        ['permissions changed', 'Rina', 'editor', 'removed: shop.products.view'],
    ]);

    get('/admin/activity?source=security')->assertInertia(fn (Assert $page) => $page
        ->where('rows.data.2.url', "/admin/users/{$this->customer->id}/edit")
        ->where('rows.data.2.typeLabel', 'Account'));
});

it('fires an event and writes to a log channel when one is set', function () {
    Event::fake([SecurityEventRecorded::class]);
    config(['sunrice-admin.security.log_channel' => 'security-test', 'logging.channels.security-test' => ['driver' => 'monolog', 'handler' => TestHandler::class]]);
    attemptPanelLogin('rina@example.com', 'wrong');

    Event::assertDispatched(SecurityEventRecorded::class, fn (SecurityEventRecorded $e) => $e->action === 'login failed'
        && $e->account?->is($this->admin) && $e->ip === '127.0.0.1' && $e->activity !== null);
    $handler = Log::channel('security-test')->getLogger()->getHandlers()[0];
    expect($handler->hasWarningThatContains('Sunrice security: login failed'))->toBeTrue();
});
