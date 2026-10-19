<?php

declare(strict_types=1);

use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Panel;
use Workbench\App\Models\User;

it('is off unless turned on: no developer tools, no permissions for them', function () {
    usePanel();
    app(SyncPermissions::class)->handle();
    Role::findOrCreate('agent', 'web')->givePermissionTo([Panel::ACCESS, 'sunrice.ai.connect']);
    $user = User::query()->create(['name' => 'Dewi', 'email' => 'dewi@example.com', 'password' => 'x'])->assignRole('agent');
    [, $token] = AiToken::issue((string) $user->id, 'Claude');

    $tools = test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => []], ['Authorization' => "Bearer {$token}"])
        ->json('result.tools.*.name');
    $call = test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'run_command', 'arguments' => ['command' => 'view:clear']]], ['Authorization' => "Bearer {$token}"]);

    expect(array_keys(app(Panel::class)->getPermissions()))->not->toContain('sunrice.views.view', 'sunrice.views.update', 'sunrice.commands.run', 'sunrice.logs.view')
        ->and($tools)->toContain('get_panel_info')->not->toContain('list_views', 'read_view', 'save_view', 'delete_view', 'run_command', 'read_log')
        ->and($call->json('result.content.0.text') ?? $call->json('error.message'))->not->toContain('exit_code');
});
