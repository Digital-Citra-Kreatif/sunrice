<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Server\Registrar;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Ai\PanelServer;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Media\MediaUsages;
use Sunrice\Admin\Panel;
use Sunrice\Tests\Admin\Fixtures\Product;
use Sunrice\Tests\Admin\Fixtures\ProductResource;
use Sunrice\Tests\Admin\Fixtures\ShopSettings;
use Sunrice\Tests\Admin\Fixtures\TestPlugin;
use Workbench\App\Models\User;

beforeEach(function () {
    Mail::fake();
    Storage::fake('public');
    Product::createTables();
    usePanel(fn (Panel $panel) => $panel->plugin(new TestPlugin('shop', register: fn (Panel $p) => $p
        ->resources(ProductResource::class)->settings(ShopSettings::class))));
    app(SyncPermissions::class)->handle();
    Role::findOrCreate('agent', 'web')->givePermissionTo([
        Panel::ACCESS, 'sunrice.ai.connect', 'shop.products.view', 'shop.products.create', 'shop.products.update',
        'shop.settings.shop', 'sunrice.media.view', 'sunrice.activity.view',
    ]);
    $this->rina = User::query()->create(['name' => 'Rina', 'email' => 'rina@example.com', 'password' => 'x'])->assignRole('agent');
    [, $this->token] = AiToken::issue((string) $this->rina->id, 'Claude');
    $this->kettle = Product::query()->create(['name' => 'Kettle', 'sku' => 'K-1', 'price' => 25, 'status' => 'draft', 'active' => true]);
    Product::query()->create(['name' => 'Pan', 'sku' => 'P-1', 'price' => 8, 'status' => 'live']);
});

/** Call a tool; the decoded JSON it returned, or the error text. */
function callTool(string $token, string $tool, array $arguments = []): array|string
{
    $response = test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => $arguments]], ['Authorization' => "Bearer {$token}"]);
    $text = $response->json('result.content.0.text') ?? $response->json('error.message');

    return $response->json('result.isError') === true || $response->json('error') !== null ? (string) $text : json_decode((string) $text, true);
}

it('lists records like the screen: search, filters, sort, pages', function () {
    $list = callTool($this->token, 'list_records', ['resource' => 'products', 'sort' => '-price']);
    expect($list['total'])->toBe(2)
        ->and(array_column($list['rows'], 'title'))->toBe(['Kettle', 'Pan'])
        ->and($list['rows'][0]['columns']['price'])->toEqual(25)
        ->and(array_column($list['filters'], 'name'))->toBe(['status', 'active', 'price']);

    expect(callTool($this->token, 'list_records', ['resource' => 'products', 'filters' => ['status' => 'live']])['total'])->toBe(1)
        ->and(callTool($this->token, 'list_records', ['resource' => 'products', 'search' => 'kett'])['rows'][0]['id'])->toBe($this->kettle->id)
        ->and(callTool($this->token, 'list_records', ['resource' => 'users']))->toContain('No such resource');
});

it('reads a record\'s fields, values and actions', function () {
    $record = callTool($this->token, 'get_record', ['resource' => 'products', 'id' => (string) $this->kettle->id]);

    expect($record['values'])->toMatchArray(['name' => 'Kettle', 'sku' => 'K-1', 'status' => 'draft', 'active' => true])
        ->and(collect($record['fields'])->firstWhere('name', 'status'))->toMatchArray(['type' => 'select', 'required' => true])
        ->and(array_column($record['actions'], 'key'))->toBe(['publish'])
        ->and($record['can'])->toBe(['update' => true, 'delete' => false]);

    $create = callTool($this->token, 'get_record', ['resource' => 'products']);
    expect(array_column($create['fields'], 'name'))->not->toContain('notes')
        ->and($create['values']['status'])->toBe('draft');
});

it('creates and changes records, only the fields sent, validated like the screen', function () {
    $saved = callTool($this->token, 'save_record', ['resource' => 'products', 'id' => (string) $this->kettle->id, 'values' => ['price' => 30]]);
    expect($saved)->toMatchArray(['saved' => true, 'changed' => ['price']])
        ->and($this->kettle->fresh())->price->toEqual(30)->active->toEqual(true)->name->toBe('Kettle');

    expect(callTool($this->token, 'save_record', ['resource' => 'products', 'id' => (string) $this->kettle->id, 'values' => ['status' => 'sold', 'sku' => 'P-1']]))
        ->toBe('Not saved: The SKU has already been taken. The selected Status is invalid.');

    $created = callTool($this->token, 'save_record', ['resource' => 'products', 'values' => ['name' => 'Wok', 'sku' => 'W-1', 'price' => 40]]);
    expect(Product::query()->find($created['id']))->name->toBe('Wok')->status->toBe('draft');

    $lines = Activity::query()->where('source', 'ai')->orderBy('id')->get();
    expect($lines->map(fn ($a) => [$a->action, $a->subject_label, $a->via, $a->user_name])->all())->toBe([
        ['updated', 'Kettle', 'Claude', 'Rina'],
        ['created', 'Wok', 'Claude', 'Rina'],
    ]);
});

it('refuses what the user may not do: deleting, and anything with a read-only token', function () {
    expect(callTool($this->token, 'delete_record', ['resource' => 'products', 'id' => (string) $this->kettle->id]))->toContain('permission to delete');

    $this->rina->givePermissionTo('shop.products.delete');
    expect(callTool($this->token, 'delete_record', ['resource' => 'products', 'id' => (string) $this->kettle->id]))->toBe(['deleted' => true, 'title' => 'Kettle']);

    [, $reader] = AiToken::issue((string) $this->rina->id, 'Reader', readOnly: true);
    expect(collect(callTool($reader, 'get_panel_info')['permissions'])->pluck('name')->all())->not->toContain('shop.products.create', 'shop.products.update', 'shop.products.delete');
    expect(callTool($reader, 'save_record', ['resource' => 'products', 'values' => ['name' => 'X']]))->toContain('not found')
        ->and(callTool($reader, 'get_record', ['resource' => 'products']))->toContain('permission to create');
});

it('runs row and bulk actions', function () {
    $pan = Product::query()->where('sku', 'P-1')->sole();
    expect(callTool($this->token, 'run_action', ['resource' => 'products', 'action' => 'publish', 'ids' => [(string) $this->kettle->id]]))
        ->toBe(['done' => 1, 'messages' => ['Published.']])
        ->and(callTool($this->token, 'run_action', ['resource' => 'products', 'action' => 'publish', 'ids' => [(string) $pan->id]]))->toBe('It is already live.')
        ->and(callTool($this->token, 'run_action', ['resource' => 'products', 'action' => 'deactivate', 'ids' => [(string) $this->kettle->id, (string) $pan->id]]))->toMatchArray(['done' => 2])
        ->and(Product::query()->where('active', true)->count())->toBe(0)
        ->and(callTool($this->token, 'run_action', ['resource' => 'products', 'action' => 'explode', 'ids' => ['1']]))->toContain('no action "explode"');
});

it('reads and changes settings, keeping secrets hidden and the rest as they are', function () {
    app(ShopSettings::class)->fill(['paymentKey' => 'sk_live'])->save();

    $settings = callTool($this->token, 'get_settings', ['section' => 'shop']);
    expect($settings['values'])->toMatchArray(['name' => 'Shop', 'currency' => 'IDR', 'paymentKey' => '(set)'])
        ->and(json_encode($settings))->not->toContain('sk_live');

    expect(callTool($this->token, 'update_settings', ['section' => 'shop', 'values' => ['name' => 'Toko']]))->toBe(['saved' => true, 'changed' => ['name']])
        ->and(app(ShopSettings::class))->name->toBe('Toko')->paymentKey->toBe('sk_live')->guestCheckout->toBeTrue()
        ->and(callTool($this->token, 'update_settings', ['section' => 'shop', 'values' => ['currency' => 'EUR']]))->toContain('Not saved:')
        ->and(callTool($this->token, 'get_settings', ['section' => 'nope']))->toContain('No such settings section');
});

it('lists media and reads the activity log', function () {
    $this->actingAs($this->rina);
    app(UploadMedia::class)->handle(UploadedFile::fake()->image('logo.png'));
    auth()->logout();

    $media = callTool($this->token, 'list_media', ['type' => 'image']);
    expect($media['files'][0])->toMatchArray(['title' => 'logo', 'mimeType' => 'image/png'])->toHaveKey('url');
    // Searched for what's typed: % and _ aren't wildcards (they'd list every file).
    expect(callTool($this->token, 'list_media', ['search' => 'log'])['total'])->toBe(1)
        ->and(callTool($this->token, 'list_media', ['search' => '%'])['total'])->toBe(0)
        ->and(callTool($this->token, 'list_media', ['search' => '_'])['total'])->toBe(0);

    callTool($this->token, 'save_record', ['resource' => 'products', 'id' => (string) $this->kettle->id, 'values' => ['price' => 31]]);
    $activity = callTool($this->token, 'get_activity', ['source' => 'ai', 'mine' => true]);
    expect($activity['activity'][0])->toMatchArray(['source' => 'ai', 'via' => 'Claude', 'action' => 'updated', 'name' => 'Kettle', 'changed' => ['price']]);

    $this->rina->roles()->detach();
    $this->rina->givePermissionTo([Panel::ACCESS, 'sunrice.ai.connect']);
    expect(callTool($this->token, 'get_activity'))->toContain('permission to view the activity log')
        ->and(callTool($this->token, 'list_media'))->toContain('permission to view the media library');
});

it('reads the developer docs, and no file outside them', function () {
    $list = callTool($this->token, 'read_docs');
    $panel = collect($list['sections'])->firstWhere('section', 'Panel');
    expect(collect($panel['pages'])->firstWhere('page', 'panel/ai'))->toBe(['page' => 'panel/ai', 'title' => 'AI access']);

    // The page comes as Markdown text, not JSON.
    $page = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'read_docs', 'arguments' => ['page' => 'panel/ai']]], ['Authorization' => "Bearer {$this->token}"]);
    expect($page->json('result.content.0.text'))->toStartWith('# AI access');
    foreach (['panel/../../composer', '../panel/ai', 'panel/ai.md', 'panel/nope', 'shop/ai'] as $page) {
        expect(callTool($this->token, 'read_docs', ['page' => $page]))->toContain('No such page');
    }

    expect(fn () => Panel::make()->docs('Shop', __DIR__)->docs('Shop', __DIR__))->toThrow(PanelMisconfigured::class, 'Two docs are called "Shop"');
});

it('reads the error log grouped, newest first, with secrets masked', function () {
    $dir = storage_path('framework/testing/ai-logs');
    File::ensureDirectoryExists($dir);
    File::put($dir.'/laravel.log', implode("\n", [
        '[2026-10-01 10:00:00] production.ERROR: Undefined variable $title {"exception":"[object] (ErrorException(code: 0): Undefined variable $title at '.base_path('resources/views/cms/entry.blade.php').':12)',
        '[stacktrace]',
        '#0 '.base_path('vendor/laravel/framework/src/View.php').'(10): x()',
        '#1 '.base_path('app/Http/Kernel.php').'(5): y()',
        '"}',
        '[2026-10-01 11:00:00] production.ERROR: Undefined variable $title {"exception":"[object] (ErrorException(code: 0): Undefined variable $title at '.base_path('resources/views/cms/entry.blade.php').':12)',
        '"}',
        '[2026-10-01 12:00:00] production.WARNING: Slow query',
        '[2026-10-01 13:00:00] production.ERROR: Mail failed password=hunter2 Bearer abc.def token: sra_abcdefghijk',
        '',
    ]));
    config(['sunrice-admin.ai.log_path' => $dir]);

    expect(callTool($this->token, 'read_error_log'))->toContain('permission to read the error log');
    $this->rina->givePermissionTo('sunrice.ai.error-log');

    $log = callTool($this->token, 'read_error_log');
    expect($log['files'])->toBe(['storage/framework/testing/ai-logs/laravel.log'])
        ->and($log['errors'])->toHaveCount(2)
        ->and($log['errors'][0]['message'])->toBe('Mail failed password=[masked] Bearer [masked] token: [masked]')
        ->and($log['errors'][1])->toMatchArray(['count' => 2, 'first_seen' => '2026-10-01 10:00:00', 'last_seen' => '2026-10-01 11:00:00', 'exception' => 'ErrorException', 'at' => 'resources/views/cms/entry.blade.php:12']);
    expect(callTool($this->token, 'read_error_log', ['level' => 'warning'])['errors'])->toHaveCount(3)
        ->and(callTool($this->token, 'read_error_log', ['search' => 'slow', 'level' => 'debug'])['errors'][0]['message'])->toBe('Slow query')
        ->and(callTool($this->token, 'read_error_log', ['level' => 'loud']))->toContain('level');

    // Read-only tokens may read it; the site may turn it off.
    [, $reader] = AiToken::issue((string) $this->rina->id, 'Reader', readOnly: true);
    expect(callTool($reader, 'read_error_log')['errors'])->toHaveCount(2);
    config(['sunrice-admin.ai.error_log' => false]);
    expect(callTool($this->token, 'read_error_log'))->toContain('turned off');
    File::deleteDirectory($dir);
});

it('runs only allowed commands, with their own options, and logs it', function () {
    expect(callTool($this->token, 'run_command', ['command' => 'list']))->toContain('permission to run commands');
    $this->rina->givePermissionTo('sunrice.ai.run-commands');
    config(['sunrice-admin.ai.commands' => ['sunrice:activity-prune' => ['--days'], 'view:clear', 'no:such-command']]);

    expect(array_column(callTool($this->token, 'run_command', ['command' => 'list'])['commands'], 'command'))->toBe(['sunrice:activity-prune', 'view:clear'])
        ->and(callTool($this->token, 'run_command', ['command' => 'migrate:fresh']))->toContain('isn\'t one of the commands')
        ->and(callTool($this->token, 'run_command', ['command' => 'sunrice:activity-prune', 'options' => ['--env' => 'local']]))->toContain('doesn\'t take "--env"')
        ->and(callTool($this->token, 'run_command', ['command' => 'view:clear', 'options' => ['--nope' => true]]))->toContain('doesn\'t take "--nope"')
        ->and(callTool($this->token, 'run_command', ['command' => 'sunrice:activity-prune', 'options' => ['--days' => ['x' => ['y']]]]))->toContain('Give "--days"');

    $run = callTool($this->token, 'run_command', ['command' => 'sunrice:activity-prune', 'options' => ['--days' => 30]]);
    expect($run['exit_code'])->toBe(0)->and($run['output'])->toContain('older than 30 days');
    expect(Activity::query()->where('action', 'ran command')->sole())->subject_label->toBe('sunrice:activity-prune')->source->toBe('ai')
        ->and(Activity::query()->where('action', 'ran command')->sole()->properties['options'])->toBe(['--days']);
});

it('uploads media from base64 and public addresses, with every upload check', function () {
    $png = base64_encode((string) UploadedFile::fake()->image('dot.png', 2, 2)->getContent());
    expect(callTool($this->token, 'upload_media', ['content_base64' => $png, 'filename' => 'dot.png']))->toContain('permission to upload media');
    $this->rina->givePermissionTo('sunrice.media.upload');

    $file = callTool($this->token, 'upload_media', ['content_base64' => $png, 'filename' => '../../dot.png', 'alt' => 'A dot']);
    expect($file)->toMatchArray(['uploaded' => true, 'filename' => 'dot.png', 'title' => 'dot', 'alt' => 'A dot', 'mimeType' => 'image/png', 'width' => 2])
        ->and(Media::query()->sole()->uploaded_by)->toBe((string) $this->rina->id)
        ->and(Activity::query()->where('action', 'uploaded')->sole()->source)->toBe('ai');

    expect(callTool($this->token, 'upload_media', ['content_base64' => base64_encode('<?php echo 1;'), 'filename' => 'x.php']))->toContain('.php files can\'t be uploaded')
        ->and(callTool($this->token, 'upload_media', ['content_base64' => base64_encode('<html><script>alert(1)</script></html>'), 'filename' => 'page.png']))->toContain('web page or a script')
        ->and(callTool($this->token, 'upload_media', ['content_base64' => '***', 'filename' => 'a.png']))->toContain('valid base64')
        ->and(callTool($this->token, 'upload_media', ['filename' => 'a.png']))->toContain('url');

    Http::fake(['93.184.216.34/*' => Http::response(base64_decode($png), 200)]);
    expect(callTool($this->token, 'upload_media', ['url' => 'http://93.184.216.34/photos/sun.png'])['filename'])->toBe('sun.png');
    foreach (['http://127.0.0.1/a.png', 'http://localhost/a.png', 'http://10.1.2.3/a.png', 'http://169.254.169.254/latest'] as $inside) {
        expect(callTool($this->token, 'upload_media', ['url' => $inside]))->toContain('public web addresses');
    }
    expect(callTool($this->token, 'upload_media', ['url' => 'file:///etc/passwd']))->toContain('url');
    Http::assertSentCount(1);
});

it('edits and organises media: folders, moving, trash, usage rules', function () {
    $this->actingAs($this->rina);
    $logo = app(UploadMedia::class)->handle(UploadedFile::fake()->image('logo.png'));
    auth()->logout();

    expect(callTool($this->token, 'update_media', ['id' => $logo->id, 'alt' => 'Our logo']))->toContain('permission to edit media')
        ->and(callTool($this->token, 'manage_media', ['action' => 'folders']))->toBe(['folders' => []])
        ->and(callTool($this->token, 'manage_media', ['action' => 'trash', 'id' => $logo->id]))->toContain('permission to delete media');
    $this->rina->givePermissionTo('sunrice.media.update', 'sunrice.media.delete');

    expect(callTool($this->token, 'update_media', ['id' => $logo->id, 'alt' => 'Our logo']))->toMatchArray(['saved' => true, 'alt' => 'Our logo', 'title' => 'logo'])
        ->and(callTool($this->token, 'update_media', ['id' => $logo->id, 'alt' => str_repeat('a', 300)]))->toContain('250');

    $brand = callTool($this->token, 'manage_media', ['action' => 'create_folder', 'name' => 'Brand'])['folder'];
    $old = callTool($this->token, 'manage_media', ['action' => 'create_folder', 'name' => 'Old', 'parent_id' => $brand['id']])['folder'];
    expect(callTool($this->token, 'manage_media', ['action' => 'create_folder', 'name' => 'Brand']))->toContain('already a folder')
        ->and(callTool($this->token, 'manage_media', ['action' => 'folders'])['folders'])->toBe([['id' => $brand['id'], 'name' => 'Brand', 'depth' => 0], ['id' => $old['id'], 'name' => 'Old', 'depth' => 1]])
        ->and(callTool($this->token, 'manage_media', ['action' => 'move', 'ids' => [$logo->id], 'folder_id' => $old['id']]))->toBe(['moved' => 1, 'folder' => 'Old'])
        ->and(callTool($this->token, 'manage_media', ['action' => 'delete_folder', 'folder_id' => $old['id']]))->toMatchArray(['deleted' => true])
        ->and($logo->fresh()->folder_id)->toBe($brand['id']);

    app(MediaUsages::class)->sync('shop.product', '1', [$logo->id], 'Product: Kettle');
    expect(callTool($this->token, 'manage_media', ['action' => 'usages', 'id' => $logo->id])['usages'][0]['label'])->toBe('Product: Kettle')
        ->and(callTool($this->token, 'manage_media', ['action' => 'delete', 'id' => $logo->id]))->toContain('Trash the file first')
        ->and(callTool($this->token, 'manage_media', ['action' => 'trash', 'id' => $logo->id]))->toBe(['trashed' => true, 'id' => $logo->id])
        ->and(callTool($this->token, 'manage_media', ['action' => 'delete', 'id' => $logo->id]))->toContain('is used by Product: Kettle')
        ->and(callTool($this->token, 'manage_media', ['action' => 'restore', 'id' => $logo->id]))->toMatchArray(['restored' => true, 'trashed' => false]);
    callTool($this->token, 'manage_media', ['action' => 'trash', 'id' => $logo->id]);
    expect(callTool($this->token, 'manage_media', ['action' => 'delete', 'id' => $logo->id, 'force' => true]))->toBe(['deleted' => true, 'id' => $logo->id])
        ->and(Media::withTrashed()->count())->toBe(0);

    expect(Activity::query()->where('source', 'ai')->pluck('action')->all())->toContain('updated', 'created', 'moved', 'trashed', 'restored', 'deleted');
});

it('refuses to create a token from the console when AI access is off, but still lists and revokes', function () {
    [$old] = AiToken::issue((string) $this->rina->id, 'Before');
    $count = AiToken::query()->count();
    $off = Panel::make()->aiAccess(false);
    $off->boot();
    app()->instance(Panel::class, $off);

    $this->artisan('sunrice:ai-token', ['email' => 'rina@example.com'])->expectsOutputToContain('AI access is turned off')->assertFailed();
    expect(AiToken::query()->count())->toBe($count);
    $this->artisan('sunrice:ai-token', ['email' => 'rina@example.com', '--list' => true])->expectsOutputToContain('Before')->assertSuccessful();
    $this->artisan('sunrice:ai-token', ['email' => 'rina@example.com', '--revoke' => (string) $old->id])->assertSuccessful();
});

it('creates, lists and revokes tokens from the console', function () {
    $this->artisan('sunrice:ai-token', ['email' => 'nobody@example.com'])->assertFailed();
    $this->artisan('sunrice:ai-token', ['email' => 'rina@example.com', '--name' => 'Cursor', '--read-only' => true, '--expires' => '30'])
        ->expectsOutputToContain('shown only once')->assertSuccessful();
    $token = AiToken::query()->where('name', 'Cursor')->sole();
    expect($token->read_only)->toBeTrue()->and($token->expires_at?->isSameDay(now()->addDays(30)))->toBeTrue()
        ->and(Activity::query()->where('action', 'token created')->exists())->toBeTrue();

    $this->artisan('sunrice:ai-token', ['email' => 'rina@example.com', '--expires' => 'soon'])->assertFailed();
    $this->artisan('sunrice:ai-token', ['email' => 'rina@example.com', '--expires' => 'never'])->assertSuccessful();
    expect(AiToken::query()->where('name', 'AI agent')->sole()->expires_at)->toBeNull();

    $this->artisan('sunrice:ai-token', ['email' => 'rina@example.com', '--list' => true])->expectsOutputToContain('Cursor')->assertSuccessful();
    $this->artisan('sunrice:ai-token', ['email' => 'rina@example.com', '--revoke' => '999'])->assertFailed();
    $this->artisan('sunrice:ai-token', ['email' => 'rina@example.com', '--revoke' => (string) $token->id])->assertSuccessful();
    expect(AiToken::query()->whereKey($token->id)->exists())->toBeFalse();
});

it('serves a local agent over stdio as the configured user, and refuses without one', function () {
    expect(app(Registrar::class)->getLocalServer('sunrice'))->not->toBeNull();

    expect(fn () => PanelServer::actAsLocalUser())->toThrow(RuntimeException::class, 'sunrice-admin.ai.local_user');
    config(['sunrice-admin.ai.local_user' => 'nobody@example.com']);
    expect(fn () => PanelServer::actAsLocalUser())->toThrow(RuntimeException::class, 'No account');
    $plain = User::query()->create(['name' => 'Plain', 'email' => 'plain@example.com', 'password' => 'x']);
    config(['sunrice-admin.ai.local_user' => 'plain@example.com']);
    expect(fn () => PanelServer::actAsLocalUser())->toThrow(RuntimeException::class, 'may not open the panel');

    config(['sunrice-admin.ai.local_user' => 'rina@example.com']);
    expect(PanelServer::actAsLocalUser()->getAuthIdentifier())->toBe($this->rina->id)
        ->and(auth()->id())->toBe($this->rina->id);
});
