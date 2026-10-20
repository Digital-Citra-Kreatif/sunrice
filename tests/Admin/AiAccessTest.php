<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Mail\SecurityAlert;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Permissions\Permission;
use Sunrice\Admin\Security\AccountGuard;
use Sunrice\Tests\Admin\Fixtures\ShopReadTool;
use Sunrice\Tests\Admin\Fixtures\ShopWriteTool;
use Sunrice\Tests\Admin\Fixtures\TestPlugin;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Mail::fake();
    usePanel(fn (Panel $panel) => $panel->plugin(new TestPlugin('shop', register: fn (Panel $p) => $p
        ->permissions(Permission::make('shop.products.update', 'Edit products'))
        ->tools(ShopReadTool::class, ShopWriteTool::class)
        ->aiInstructions('The shop sells kettles.'))));
    app(SyncPermissions::class)->handle();
    Role::findOrCreate('agent-user', 'web')->givePermissionTo([Panel::ACCESS, 'sunrice.ai.connect', 'shop.products.update']);
    $this->rina = User::query()->create(['name' => 'Rina', 'email' => 'rina@example.com', 'password' => 'secret-password'])->assignRole('agent-user');
});

function mcp(string $token, string $method, array $params = [], string $ip = '127.0.0.1'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], ['Authorization' => "Bearer {$token}"]);
}

function toolNames(TestResponse $response): array
{
    return collect($response->json('result.tools'))->pluck('name')->sort()->values()->all();
}

it('creates tokens on the AI access page, shows each once, and revokes them', function () {
    actingAs($this->rina);
    get('/admin/ai')->assertInertia(fn (Assert $page) => $page->component('Ai/Index', shouldExist: false)
        ->where('endpoint', fn ($url) => str_ends_with($url, '/mcp'))->where('tokens', [])->where('created', null));

    post('/admin/ai/tokens', ['name' => 'Claude', 'read_only' => true, 'expires_in_days' => 30])->assertRedirect();
    $token = AiToken::query()->sole();
    expect($token)->read_only->toBeTrue()->and($token->expires_at->isFuture())->toBeTrue();

    get('/admin/ai')->assertInertia(fn (Assert $page) => $page
        ->where('created.name', 'Claude')->where('created.token', fn ($plain) => str_starts_with($plain, 'sra_') && hash('sha256', $plain) === $token->token_hash)
        ->where('tokens.0.readOnly', true));
    get('/admin/ai')->assertInertia(fn (Assert $page) => $page->where('created', null)); // shown once

    post('/admin/ai/tokens', ['name' => '', 'expires_in_days' => 7])->assertSessionHasErrors(['name', 'expires_in_days']);
    delete("/admin/ai/tokens/{$token->id}")->assertSessionHas('success');
    expect(AiToken::query()->count())->toBe(0)
        ->and(Activity::query()->where('source', 'security')->pluck('action')->all())->toContain('token created', 'token revoked');

    actingAs(adminUser(Panel::ACCESS));
    get('/admin/ai')->assertForbidden();
});

it('lets an agent in with a valid token only, as its owner', function () {
    [$token, $plain] = AiToken::issue((string) $this->rina->id, 'Claude');

    postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
    mcp('sra_wrong', 'tools/list')->assertUnauthorized();
    expect(toolNames(mcp($plain, 'tools/list')->assertOk()))->toBe(['delete_record', 'get_activity', 'get_panel_info', 'get_record', 'get_settings', 'list_media', 'list_records', 'run_action', 'save_record', 'shop_read', 'shop_write', 'update_settings']);

    $info = json_decode(mcp($plain, 'tools/call', ['name' => 'get_panel_info', 'arguments' => []])->json('result.content.0.text'), true);
    expect($info)->panel->toBe('Admin')->you->toBe(['name' => 'Rina', 'email' => 'rina@example.com'])->read_only_token->toBeFalse()
        ->and(collect($info['permissions'])->pluck('name')->all())->toContain('sunrice.ai.connect', 'shop.products.update');

    $token->forceFill(['expires_at' => now()->subMinute()])->save();
    mcp($plain, 'tools/list')->assertUnauthorized();

    [, $other] = AiToken::issue((string) $this->rina->id, 'Cursor');
    app(AccountGuard::class)->lock($this->rina);
    mcp($other, 'tools/list')->assertForbidden();

    $nobody = adminUser(Panel::ACCESS);
    [, $plain] = AiToken::issue((string) $nobody->id, 'X');
    mcp($plain, 'tools/list')->assertForbidden();
});

it('records an agent\'s changes as the AI agent\'s, and gives the instructions', function () {
    [, $plain] = AiToken::issue((string) $this->rina->id, 'Claude Desktop');

    mcp($plain, 'tools/call', ['name' => 'shop_write', 'arguments' => []])->assertOk()->assertJsonPath('result.isError', false);
    $line = Activity::query()->where('action', 'updated')->sole();
    expect($line)->source->toBe('ai')->via->toBe('Claude Desktop')->user_name->toBe('Rina');

    $init = mcp($plain, 'initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 't', 'version' => '1']]);
    expect($init->json('result.instructions'))->toContain('get_panel_info')->toContain('The shop sells kettles.')
        ->and($init->json('result.serverInfo.name'))->toBe('Admin');
});

it('keeps read-only tokens to reading, even for super admins', function () {
    $this->rina->assignRole('super-admin');
    [, $plain] = AiToken::issue((string) $this->rina->id, 'Reader', readOnly: true);

    expect(toolNames(mcp($plain, 'tools/list')))->toBe(['get_activity', 'get_panel_info', 'get_record', 'get_settings', 'list_media', 'list_records', 'shop_read']);
    mcp($plain, 'tools/call', ['name' => 'shop_write', 'arguments' => []])->assertJsonPath('error.code', -32602);

    $info = json_decode(mcp($plain, 'tools/call', ['name' => 'get_panel_info', 'arguments' => []])->json('result.content.0.text'), true);
    expect($info['read_only_token'])->toBeTrue()
        ->and(collect($info['permissions'])->pluck('name')->all())->not->toContain('shop.products.update', 'sunrice.users.update')->toContain('sunrice.users.view')
        ->and(collect($info['resources'])->firstWhere('key', 'users')['can'])->toBe(['view']);
});

it('records a token used from a new address, alerts, and lets admins revoke a user\'s tokens', function () {
    $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'x'])->assignRole('super-admin');
    [, $plain] = AiToken::issue((string) $this->rina->id, 'Claude');
    mcp($plain, 'tools/list', ip: '10.0.0.1');
    mcp($plain, 'tools/list', ip: '10.0.0.2');

    expect(Activity::query()->where('action', 'token used from new IP')->sole()->properties['note'])->toBe('"Claude" from 10.0.0.2 (before: 10.0.0.1)');
    Mail::assertQueued(SecurityAlert::class, fn (SecurityAlert $mail) => $mail->headline === 'An AI access token was used from a new address');

    actingAs($admin);
    post("/admin/users/{$this->rina->id}/actions/revoke-tokens")->assertSessionHas('success', '1 AI access token revoked.');
    mcp($plain, 'tools/list')->assertUnauthorized();
});

it('makes plugins name their tools after themselves, and can turn AI access off', function () {
    $panel = Panel::make()->plugin(new TestPlugin('blog', register: fn (Panel $p) => $p->tools(ShopReadTool::class)));
    expect(fn () => $panel->boot())->toThrow(PanelMisconfigured::class, 'start with its id ("blog_…")');

    $off = Panel::make()->aiAccess(false);
    $off->boot();
    expect(array_keys($off->getPermissions()))->not->toContain('sunrice.ai.connect');
});
