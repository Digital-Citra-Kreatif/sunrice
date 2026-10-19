<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SeedRoles;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Ai\ErrorLog;
use Sunrice\Admin\Panel;
use Workbench\App\Models\User;

beforeEach(function () {
    Mail::fake();
    $this->root = sys_get_temp_dir().'/sunrice-dev-'.uniqid();
    File::ensureDirectoryExists("{$this->root}/views/cms");
    File::ensureDirectoryExists("{$this->root}/logs");
    File::put("{$this->root}/views/cms/article.blade.php", '<h1>{{ $title }}</h1>');
    File::put("{$this->root}/views/welcome.blade.php", 'Hello');
    config([
        'sunrice-admin.ai.developer_tools.enabled' => true,
        'sunrice-admin.ai.developer_tools.views_path' => "{$this->root}/views",
        'sunrice-admin.ai.developer_tools.logs_path' => "{$this->root}/logs",
        'sunrice-admin.ai.developer_tools.commands' => ['view:clear', 'cache:*'],
    ]);
    usePanel();
    app(SyncPermissions::class)->handle();
    Role::findOrCreate('developer', 'web')->givePermissionTo([
        Panel::ACCESS, 'sunrice.ai.connect', 'sunrice.views.view', 'sunrice.views.update', 'sunrice.commands.run', 'sunrice.logs.view',
    ]);
    $this->dev = User::query()->create(['name' => 'Dewi', 'email' => 'dewi@example.com', 'password' => 'x'])->assignRole('developer');
    [, $this->token] = AiToken::issue((string) $this->dev->id, 'Claude');
});

afterEach(function () {
    File::deleteDirectory($this->root);
    File::deleteDirectory(storage_path('app/sunrice/view-versions'));
});

/** Call a tool with this token; the decoded JSON it returned, or the error text. */
function devTool(string $token, string $tool, array $arguments = []): array|string
{
    $response = test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => $arguments]], ['Authorization' => "Bearer {$token}"]);
    $text = $response->json('result.content.0.text') ?? $response->json('error.message');

    return $response->json('result.isError') === true || $response->json('error') !== null ? (string) $text : json_decode((string) $text, true);
}

function devToolNames(string $token): array
{
    $response = test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => []], ['Authorization' => "Bearer {$token}"]);

    return collect($response->json('result.tools'))->pluck('name')->all();
}

it('lists and reads views, and refuses names that leave the views folder', function () {
    expect(devToolNames($this->token))->toContain('list_views', 'read_view', 'save_view', 'delete_view', 'run_command', 'read_log')
        ->and(array_column(devTool($this->token, 'list_views')['views'], 'name'))->toBe(['cms.article', 'welcome'])
        ->and(array_column(devTool($this->token, 'list_views', ['prefix' => 'cms'])['views'], 'name'))->toBe(['cms.article'])
        ->and(devTool($this->token, 'read_view', ['name' => 'cms/article.blade.php']))->toMatchArray(['name' => 'cms.article', 'content' => '<h1>{{ $title }}</h1>']);

    foreach (['../.env', '/etc/passwd', 'cms/../../secret', 'Cms.Article', ''] as $name) {
        expect(devTool($this->token, 'read_view', ['name' => $name]))->toContain('Give a view\'s name')
            ->and(devTool($this->token, 'save_view', ['name' => $name, 'content' => 'x']))->toContain('Give a view\'s "name"');
    }
});

it('saves views that compile, keeps the earlier version, and logs the change', function () {
    $saved = devTool($this->token, 'save_view', ['name' => 'cms.article', 'content' => '<h2>{{ $title }}</h2>']);
    expect($saved['created'])->toBeFalse()->and($saved['version'])->toBeString()
        ->and(File::get("{$this->root}/views/cms/article.blade.php"))->toBe('<h2>{{ $title }}</h2>');

    $read = devTool($this->token, 'read_view', ['name' => 'cms.article']);
    expect($read['versions'])->toBe([$saved['version']])
        ->and(devTool($this->token, 'read_view', ['name' => 'cms.article', 'version' => $saved['version']])['content'])->toBe('<h1>{{ $title }}</h1>');

    expect(devTool($this->token, 'save_view', ['name' => 'cms.landing', 'content' => '@if($x) yes @endif']))->toMatchArray(['created' => true, 'version' => null])
        ->and(devTool($this->token, 'save_view', ['name' => 'cms.broken', 'content' => '@if($x) {{ $a + }} @endif']))->toContain('doesn\'t compile')
        ->and(is_file("{$this->root}/views/cms/broken.blade.php"))->toBeFalse();

    expect(Activity::query()->where('subject_type', 'view')->orderBy('id')->get(['action', 'subject_id', 'source'])->toArray())->toBe([
        ['action' => 'updated', 'subject_id' => 'cms.article', 'source' => 'ai'],
        ['action' => 'created', 'subject_id' => 'cms.landing', 'source' => 'ai'],
    ]);
});

it('deletes views, keeping them to bring back', function () {
    $deleted = devTool($this->token, 'delete_view', ['name' => 'welcome']);
    expect(is_file("{$this->root}/views/welcome.blade.php"))->toBeFalse()
        ->and(devTool($this->token, 'read_view', ['name' => 'welcome', 'version' => $deleted['version']])['content'])->toBe('Hello')
        ->and(devTool($this->token, 'delete_view', ['name' => 'welcome']))->toContain('No view welcome');
});

it('runs only the commands the app allows, and logs each run', function () {
    $run = devTool($this->token, 'run_command', ['command' => 'view:clear']);
    expect($run)->toMatchArray(['command' => 'view:clear --no-interaction', 'exit_code' => 0])
        ->and(devTool($this->token, 'run_command', ['command' => 'cache:clear'])['exit_code'])->toBe(0);

    foreach (['migrate:fresh --force', 'db:wipe', 'view:clear; rm -rf /', '--env=x view:clear', "view:clear\ndb:wipe"] as $line) {
        expect(devTool($this->token, 'run_command', ['command' => $line]))->toContain('Not run');
    }
    expect(Activity::query()->where('subject_type', 'command')->pluck('subject_label')->all())->toBe(['view:clear --no-interaction', 'cache:clear --no-interaction']);
});

it('reads the error log, newest first, by level and text, with secrets hidden', function () {
    File::put("{$this->root}/logs/laravel.log", implode("\n", [
        '[2026-10-01 10:00:00] production.INFO: Started',
        '[2026-10-01 10:01:00] production.ERROR: Undefined variable $title (View: cms/article.blade.php) {"exception":"[object] (ErrorException(code: 0): Undefined variable $title',
        '#0 /var/www/vendor/laravel/framework/src/Foundation/Bootstrap/HandleExceptions.php(255)',
        '[2026-10-01 10:02:00] production.CRITICAL: Payment failed for Bearer abc.def password=hunter2 sra_TOKEN123',
        '',
    ]));

    $log = devTool($this->token, 'read_log');
    expect($log['file'])->toBe('laravel.log')
        ->and(array_column($log['entries'], 'level'))->toBe(['critical', 'error'])
        ->and($log['entries'][0]['message'])->not->toContain('abc.def', 'hunter2', 'TOKEN123')->toContain('[hidden]')
        ->and($log['entries'][1]['details'])->toContain('HandleExceptions.php');

    expect(array_column(devTool($this->token, 'read_log', ['level' => 'debug'])['entries'], 'level'))->toBe(['critical', 'error', 'info'])
        ->and(count(devTool($this->token, 'read_log', ['search' => 'cms/article'])['entries']))->toBe(1)
        ->and(devTool($this->token, 'read_log', ['file' => '../../.env']))->toContain('No log file')
        ->and(ErrorLog::redact('{"password":"s3cret","name":"x"}'))->toBe('{"password":"[hidden]","name":"x"}');
});

it('checks each permission, and gives read-only tokens only reading', function () {
    Role::findOrCreate('viewer', 'web')->givePermissionTo([Panel::ACCESS, 'sunrice.ai.connect', 'sunrice.views.view']);
    $viewer = User::query()->create(['name' => 'Vina', 'email' => 'vina@example.com', 'password' => 'x'])->assignRole('viewer');
    [, $token] = AiToken::issue((string) $viewer->id, 'Claude');
    expect(devTool($token, 'read_view', ['name' => 'welcome'])['content'])->toBe('Hello')
        ->and(devTool($token, 'save_view', ['name' => 'welcome', 'content' => 'x']))->toContain('permission')
        ->and(devTool($token, 'run_command', ['command' => 'view:clear']))->toContain('permission')
        ->and(devTool($token, 'read_log'))->toContain('permission');

    [, $readOnly] = AiToken::issue((string) $this->dev->id, 'Viewer', readOnly: true);
    expect(devToolNames($readOnly))->toContain('list_views', 'read_view', 'read_log')->not->toContain('save_view', 'delete_view', 'run_command');
});

it('never gives its permissions through "*" in a default role', function () {
    app(SeedRoles::class)->handle();

    $administrator = Role::findByName('administrator', 'web');
    expect($administrator->hasPermissionTo('sunrice.access'))->toBeTrue()
        ->and($administrator->permissions->pluck('name')->intersect(['sunrice.views.view', 'sunrice.views.update', 'sunrice.commands.run', 'sunrice.logs.view'])->all())->toBe([]);
});
