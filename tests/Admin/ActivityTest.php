<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Dashboard\PanelWidgets;
use Sunrice\Admin\Mail\SecurityAlert;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Security\SecurityLog;
use Sunrice\Tests\Admin\Fixtures\MailSettings;
use Sunrice\Tests\Admin\Fixtures\Product;
use Sunrice\Tests\Admin\Fixtures\ProductResource;
use Sunrice\Tests\Admin\Fixtures\TestPlugin;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    Product::createTables();
    Storage::fake('public');
    usePanel(fn (Panel $panel) => $panel->settings(MailSettings::class)
        ->plugin(new TestPlugin('shop', register: fn (Panel $p) => $p->resources(ProductResource::class))));
    app(SyncPermissions::class)->handle();
    $this->admin = User::query()->create(['name' => 'Rina', 'email' => 'rina@example.com', 'password' => 'secret-password'])->assignRole('super-admin');
});

/** The log as [source, user, action, type, label, changed], oldest first. */
function activityLines(): array
{
    return Activity::query()->orderBy('id')->get()->map(fn (Activity $a) => [
        $a->source, $a->user_name, $a->action, $a->subject_type, $a->subject_label, $a->properties['changed'] ?? null,
    ])->all();
}

it('records resource changes with the fields that changed, not their values', function () {
    actingAs($this->admin);
    post('/admin/shop/products', ['name' => 'Kettle', 'sku' => 'K-1', 'price' => 10, 'status' => 'draft'])->assertRedirect();
    $product = Product::query()->sole();

    put("/admin/shop/products/{$product->id}", ['name' => 'Kettle', 'sku' => 'K-1', 'price' => 12, 'status' => 'live'])->assertSessionHasNoErrors();
    put("/admin/shop/products/{$product->id}", ['name' => 'Kettle', 'sku' => 'K-1', 'price' => 12, 'status' => 'live']); // nothing changed
    post('/admin/shop/products/bulk', ['action' => 'deactivate', 'ids' => [$product->id]]);
    delete("/admin/shop/products/{$product->id}");

    expect(activityLines())->toBe([
        ['admin', 'Rina', 'created', 'products', 'Kettle', null],
        ['admin', 'Rina', 'updated', 'products', 'Kettle', ['price', 'status']],
        ['admin', 'Rina', 'deactivate', 'products', 'Kettle', null],
        ['admin', 'Rina', 'deleted', 'products', 'Kettle', null],
    ])->and(Activity::query()->first()->ip_address)->toBe('127.0.0.1')
        ->and(json_encode(Activity::query()->pluck('properties')))->not->toContain('12');
});

it('records users\' roles and passwords as changed, and settings', function () {
    $user = User::query()->create(['name' => 'Dewi', 'email' => 'dewi@example.com', 'password' => 'secret-password']);
    Role::findOrCreate('editor', 'web');
    actingAs($this->admin);

    put("/admin/users/{$user->id}", ['name' => 'Dewi', 'email' => 'dewi@example.com', 'password' => 'a-new-password', 'password_confirmation' => 'a-new-password', 'roles' => ['editor']])->assertSessionHasNoErrors();
    put('/admin/settings/outbox', ['from' => 'hello@example.com'])->assertSessionHasNoErrors();

    expect(activityLines())->toBe([
        ['security', 'Rina', 'roles changed', 'account', 'Dewi', null],
        ['admin', 'Rina', 'updated', 'users', 'Dewi', ['password', 'roles']],
        ['admin', 'Rina', 'updated', 'settings', 'Outbox settings', ['from']],
    ]);
});

it('records media from the page and from code, as the system without a user', function () {
    $media = app(UploadMedia::class)->handle(UploadedFile::fake()->image('logo.png'));
    actingAs($this->admin);
    delete("/admin/media/{$media->id}");
    post("/admin/media/{$media->id}/restore");
    put("/admin/media/{$media->id}", ['title' => 'Logo', 'alt' => 'Sun']);

    expect(activityLines())->toBe([
        ['system', null, 'uploaded', 'media', 'logo', null],
        ['admin', 'Rina', 'trashed', 'media', 'logo', null],
        ['admin', 'Rina', 'restored', 'media', 'logo', null],
        ['admin', 'Rina', 'updated', 'media', 'Logo', ['title', 'alt']],
    ]);
});

it('merges updates to the same thing in one request, and records sources', function () {
    actingAs($this->admin);
    $log = app(ActivityLog::class);
    $product = Product::query()->create(['name' => 'Pan', 'sku' => 'P-1', 'price' => 5]);

    $log->updated('products', $product, 'Pan', ['name']);
    $log->updated('products', $product, 'Pan', ['price', 'name']);
    $log->withSource('ai', 'Claude Desktop', fn () => $log->record('refunded', 'order', 7, 'Order #7', ['note' => 'Damaged']));
    $log->withoutLogging(fn () => $log->record('ignored', 'order'));
    config(['sunrice-admin.activity.enabled' => false]);
    $log->record('ignored', 'order');

    expect(Activity::query()->count())->toBe(2)
        ->and(Activity::query()->first()->properties)->toBe(['changed' => ['name', 'price']])
        ->and(Activity::query()->latest('id')->first())->source->toBe('ai')->via->toBe('Claude Desktop')->user_name->toBe('Rina')->subject_id->toBe('7');
});

it('merges by an id each request gets, so a worker\'s next request never continues the last one\'s lines', function () {
    actingAs($this->admin);
    $log = app(ActivityLog::class);
    $product = Product::query()->create(['name' => 'Pan', 'sku' => 'P-1', 'price' => 5]);

    $log->updated('products', $product, 'Pan', ['name']);
    expect(request()->attributes->get(ActivityLog::REQUEST_ID))->toBeString();

    // The same request object again, as a reused one in a long-running worker: a fresh id, no merging.
    request()->attributes->remove(ActivityLog::REQUEST_ID);
    $log->updated('products', $product, 'Pan', ['price']);

    expect(Activity::query()->where('action', 'updated')->count())->toBe(2);
});

it('shows the log to those allowed, with filters and links', function () {
    actingAs($this->admin);
    post('/admin/shop/products', ['name' => 'Kettle', 'sku' => 'K-1', 'price' => 10, 'status' => 'draft']);
    put('/admin/settings/outbox', ['from' => 'hello@example.com']);
    $product = Product::query()->sole();
    app(ActivityLog::class)->withSource('ai', 'Claude', fn () => app(ActivityLog::class)->record('noted', 'products', $product, 'Kettle'));

    get('/admin/activity')->assertInertia(fn (Assert $page) => $page
        ->component('Activity/Index', shouldExist: false)
        ->where('rows.total', 3)
        ->where('rows.data.0.via', 'Claude')
        ->where('rows.data.1.url', '/admin/settings/outbox')
        ->where('rows.data.2.url', "/admin/shop/products/{$product->id}/edit")
        ->where('rows.data.2.typeLabel', 'Product')
        ->where('types', [['value' => 'products', 'label' => 'Product'], ['value' => 'settings', 'label' => 'Settings']]));

    get('/admin/activity?source=ai')->assertInertia(fn (Assert $page) => $page->where('rows.total', 1));
    get("/admin/activity?type=products&subject={$product->id}")->assertInertia(fn (Assert $page) => $page->where('rows.total', 2));
    get('/admin/activity?search=outbox')->assertInertia(fn (Assert $page) => $page->where('rows.total', 1));
    get("/admin/activity?user={$this->admin->id}&from=".now()->toDateString())->assertInertia(fn (Assert $page) => $page->where('rows.total', 3));
    get('/admin/activity?to='.now()->subDay()->toDateString())->assertInertia(fn (Assert $page) => $page->where('rows.total', 0));
    get('/admin/activity?source=nope')->assertSessionHasErrors('source');

    actingAs(adminUser(Panel::ACCESS));
    get('/admin/activity')->assertForbidden();
});

it('prunes old lines, daily with the scheduler', function () {
    Activity::query()->insert([
        ['source' => 'system', 'action' => 'old', 'subject_type' => 'x', 'created_at' => now()->subDays(400)],
        ['source' => 'system', 'action' => 'new', 'subject_type' => 'x', 'created_at' => now()->subDays(10)],
    ]);

    artisan('sunrice:activity-prune')->expectsOutputToContain('Deleted 1 activity log line older than 365 days.')->assertSuccessful();
    artisan('sunrice:activity-prune', ['--days' => 5])->assertSuccessful();
    expect(Activity::query()->count())->toBe(0);

    $events = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command)->implode(' ');
    expect($events)->toContain('sunrice:activity-prune');
});

it('filters by action and pages by the chosen size', function () {
    actingAs($this->admin);
    foreach (range(1, 30) as $i) {
        app(ActivityLog::class)->record($i % 3 === 0 ? 'deleted' : 'created', 'products', $i, "Product {$i}");
    }

    get('/admin/activity?action=deleted')->assertInertia(fn (Assert $page) => $page
        ->where('rows.total', 10)
        ->where('query.action', 'deleted')
        ->where('actions', fn ($actions) => collect($actions)->contains('deleted') && collect($actions)->contains('created'))
        ->where('query.perPage', 50)
        ->where('perPageOptions', [25, 50, 100, 200]));
    get('/admin/activity?per_page=25')->assertInertia(fn (Assert $page) => $page->where('query.perPage', 25)->where('rows.lastPage', 2)->has('rows.data', 25));
    get('/admin/activity?per_page=7')->assertSessionHasErrors('per_page');
});

it('filters by IP address, exactly or by its start, from the filter or the search, security events too', function () {
    actingAs($this->admin);
    $at = fn (string $ip, string $action) => Activity::query()->create(['source' => 'admin', 'action' => $action, 'subject_type' => 'products', 'subject_label' => $action, 'ip_address' => $ip, 'created_at' => now()]);
    $at('192.168.1.20', 'created');
    $at('192.168.7.3', 'updated');
    $at('10.0.0.5', 'deleted');
    $at('2001:db8::1', 'renamed');
    app(SecurityLog::class)->record('login failed', null, 'mallory@example.com'); // from the test request's 127.0.0.1

    $actions = fn (string $query) => collect(get("/admin/activity?{$query}")->assertOk()->viewData('page')['props']['rows']['data'])->pluck('action')->sort()->values()->all();
    expect($actions('ip=192.168.1.20'))->toBe(['created'])
        ->and($actions('ip=192.168.'))->toBe(['created', 'updated'])
        ->and($actions('ip=2001:db8:'))->toBe(['renamed'])
        ->and($actions('ip=127.0.0.1'))->toBe(['login failed'])
        ->and($actions('search=192.168.'))->toBe(['created', 'updated'])
        ->and($actions('search=10.0.0.5'))->toBe(['deleted']);
    get('/admin/activity?ip=192.168.1.20')->assertInertia(fn (Assert $page) => $page->where('query.ip', '192.168.1.20')->where('rows.data.0.ip', '192.168.1.20'));
    get('/admin/activity?ip=x%27%20or%201')->assertSessionHasErrors('ip');
    expect(collect(Schema::getIndexes('sunrice_activity'))->contains(fn (array $index) => $index['columns'] === ['ip_address']))->toBeTrue();
});

it('remembers which columns of the log each user shows', function () {
    actingAs($this->admin);
    get('/admin/activity')->assertInertia(fn (Assert $page) => $page->where('preferences', ['key' => 'table.activity', 'columns' => null, 'perPage' => null]));
    \Pest\Laravel\putJson('/admin/preferences/table.activity', ['value' => ['columns' => ['who', 'what']]])->assertSuccessful();
    get('/admin/activity')->assertInertia(fn (Assert $page) => $page->where('preferences.columns', ['who', 'what']));
});

it('prunes old lines from the panel, with a permission, a security event and an alert', function () {
    Mail::fake();
    config(['sunrice-admin.security.alerts.emails' => ['security@example.com']]);
    app(ActivityLog::class)->record('created', 'products', 1, 'Old');
    app(ActivityLog::class)->record('created', 'products', 2, 'New');
    Activity::query()->where('subject_label', 'Old')->update(['created_at' => now()->subDays(40)]);

    $viewer = User::query()->create(['name' => 'Viewer', 'email' => 'viewer@example.com', 'password' => 'x']);
    $viewer->givePermissionTo([Panel::ACCESS, 'sunrice.activity.view']);
    actingAs($viewer);
    get('/admin/activity')->assertInertia(fn (Assert $page) => $page->where('prune', null));
    post('/admin/activity/prune', ['days' => 30])->assertForbidden();

    actingAs($this->admin);
    get('/admin/activity')->assertInertia(fn (Assert $page) => $page->where('prune', ['keepDays' => 365]));
    post('/admin/activity/prune', ['days' => 0])->assertSessionHasErrors('days');
    post('/admin/activity/prune', ['days' => 30])->assertSessionHas('success', 'Deleted 1 activity log line.');

    expect(Activity::query()->where('subject_label', 'Old')->exists())->toBeFalse()
        ->and(Activity::query()->where('subject_label', 'New')->exists())->toBeTrue();
    $event = Activity::query()->where('action', 'log pruned')->sole();
    expect($event->source)->toBe('security')
        ->and($event->subject_id)->toBe((string) $this->admin->id)
        ->and($event->properties['note'])->toBe('1 line older than 30 days');
    Mail::assertQueued(SecurityAlert::class, fn ($mail) => $mail->headline === 'The activity log was pruned');
    expect(app(Panel::class)->getPermissions())->toHaveKey('sunrice.activity.prune');
});

it('reads like sentences, with what settings changed from and to, and filters by who', function () {
    actingAs($this->admin);
    put('/admin/settings/outbox', ['from' => 'hello@example.com'])->assertSessionHasNoErrors();
    $dewi = User::query()->create(['name' => 'Dewi', 'email' => 'dewi@example.com', 'password' => 'x']);
    $security = app(SecurityLog::class);
    $security->record('logged in', $this->admin);
    $security->record('page refused', $this->admin);
    auth()->logout();
    $security->record('login failed', $dewi);
    actingAs($this->admin);

    expect(Activity::query()->where('subject_type', 'settings')->sole()->properties['values'])->toEqual(['from' => ['from' => 'shop@example.com', 'to' => 'hello@example.com']]); // MySQL's JSON keeps keys in its own order

    get('/admin/activity')->assertInertia(fn (Assert $page) => $page
        ->where('rows.data.0.action', 'login failed')
        ->where('rows.data.0.self', false)
        ->where('rows.data.1.self', true)
        ->where('rows.data.3.changed', ['From: shop@example.com → hello@example.com'])
        ->where('people', [['value' => (string) $this->admin->id, 'label' => 'Rina']]));
    get("/admin/activity?user={$this->admin->id}")->assertInertia(fn (Assert $page) => $page->where('rows.total', 3));

    // The dashboard says it in a sentence, and leaves security events to the log.
    expect(PanelWidgets::activity()->resolve($this->admin)['rows'])->toHaveCount(1)
        ->and(PanelWidgets::activity()->resolve($this->admin)['rows'][0]['title'])->toBe('Rina updated Outbox settings')
        ->and(Activity::query()->where('source', 'security')->orderByDesc('id')->get()->map(fn (Activity $line) => $line->sentence())->all())
        ->toBe(['Login failed for Dewi', 'Rina was refused a page', 'Rina logged in']);
});

it('names each type once in the filter', function () {
    app(ActivityLog::class)->record('updated', 'products', '1', 'Kettle');
    app(ActivityLog::class)->record('updated', 'product', '1', 'Legacy');
    actingAs($this->admin);

    get('/admin/activity')->assertInertia(fn (Assert $page) => $page
        ->where('types', fn ($types) => collect($types)->pluck('label')->duplicates()->isEmpty() && count($types) === 2));
});
