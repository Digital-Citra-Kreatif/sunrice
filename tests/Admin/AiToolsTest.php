<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Ai\AiToken;
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

    callTool($this->token, 'save_record', ['resource' => 'products', 'id' => (string) $this->kettle->id, 'values' => ['price' => 31]]);
    $activity = callTool($this->token, 'get_activity', ['source' => 'ai', 'mine' => true]);
    expect($activity['activity'][0])->toMatchArray(['source' => 'ai', 'via' => 'Claude', 'action' => 'updated', 'name' => 'Kettle', 'changed' => ['price']]);

    $this->rina->roles()->detach();
    $this->rina->givePermissionTo([Panel::ACCESS, 'sunrice.ai.connect']);
    expect(callTool($this->token, 'get_activity'))->toContain('permission to view the activity log')
        ->and(callTool($this->token, 'list_media'))->toContain('permission to view the media library');
});
