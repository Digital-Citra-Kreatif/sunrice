<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Settings\CodeSettings;
use Sunrice\Cms\Structure\StructureImporter;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    app(SyncPermissions::class)->handle();
    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => []]);
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page', 'route' => '/{slug}']);
    app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($pages, ['title' => 'About']));
    app(CodeSettings::class)->fill(['head' => '<script src="https://stats.example.com/a.js"></script>', 'bodyEnd' => '<div id="chat"></div>'])->save();
});

function siteCodeAgent(array $permissions): string
{
    $role = Role::findOrCreate('site-code-agent', 'web')->givePermissionTo([Panel::ACCESS, 'sunrice.ai.connect', ...$permissions]);
    $user = User::query()->create(['name' => 'Rina', 'email' => uniqid().'@example.com', 'password' => 'x'])->assignRole($role);

    return AiToken::issue((string) $user->id, 'Claude')[1];
}

function siteCodeTool(string $token, string $tool, array $arguments = []): array|string
{
    $response = test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => $arguments]], ['Authorization' => "Bearer {$token}"]);
    $text = $response->json('result.content.0.text') ?? $response->json('error.message');

    return $response->json('result.isError') === true || $response->json('error') !== null ? (string) $text : json_decode((string) $text, true);
}

it('edits the site code in code editors, under a warning about the risk', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.settings.code'));

    get('/admin/settings/code')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('section.warning', fn (string $warning) => str_contains($warning, 'in every visitor\'s browser') && str_contains($warning, 'SUNRICE_SITE_CODE=false'))
        ->where('form.0.fields.0', fn ($field) => $field['type'] === 'code' && $field['language'] === 'html' && $field['maxLength'] === 20000 && $field['help'] !== null)
        ->where('form.0.fields.1.type', 'code')->where('form.0.fields.2.help', fn ($help) => is_string($help) && str_contains($help, '</body>'))
        ->where('sections', fn ($sections) => in_array('code', array_column(is_array($sections) ? $sections : $sections->all(), 'key'), true)));

    get('/about')->assertSee('<script src="https://stats.example.com/a.js"></script>', false)->assertSee('<div id="chat"></div>', false);
});

it('shows the warning in Indonesian', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.settings.code'));
    app()->setLocale('id');

    expect(__(CodeSettings::warning()))->not->toBe(CodeSettings::warning())->toContain('SUNRICE_SITE_CODE=false');
});

it('turns the site code off: no page, no menu item, nothing printed, the code kept', function () {
    config(['sunrice-cms.site.custom_code' => false]);
    actingAs(adminUser(Panel::ACCESS, 'cms.settings.code', 'cms.settings.site'));

    get('/admin/settings/code')->assertNotFound();
    put('/admin/settings/code', ['head' => '<script>evil()</script>'])->assertNotFound();
    get('/admin/settings/site')->assertInertia(fn (Assert $page) => $page
        ->where('sections', fn ($sections) => ! in_array('code', array_column(is_array($sections) ? $sections : $sections->all(), 'key'), true)));

    // Its only section: the Settings item goes too.
    actingAs(adminUser(Panel::ACCESS, 'cms.settings.code'));
    $items = collect(app(Panel::class)->navigationFor(auth()->user()))->flatMap(fn (array $group) => $group['items']);
    expect($items->pluck('key')->all())->not->toContain('settings');

    $page = (string) get('/about')->getContent();
    expect($page)->not->toContain('stats.example.com')->not->toContain('<div id="chat">')
        ->and(app(CodeSettings::class)->at('head'))->toBe('')
        ->and(Blade::render('<x-sunrice-cms::code place="body-end" />'))->toBe('')
        ->and(app(CodeSettings::class)->head)->toBe('<script src="https://stats.example.com/a.js"></script>');

    // Back on: as it was.
    config(['sunrice-cms.site.custom_code' => true]);
    get('/about')->assertSee('<div id="chat"></div>', false);
    get('/admin/settings/code')->assertOk();
});

it('reads the switch from the environment\'s words', function (mixed $value, bool $on) {
    config(['sunrice-cms.site.custom_code' => $value]);

    expect(CodeSettings::available())->toBe($on);
})->with([[false, false], ['false', false], ['0', false], ['off', false], [true, true], ['true', true], ['1', true], [null, true]]);

it('keeps agents away from the site code while it is off', function () {
    $token = siteCodeAgent(['cms.settings.code', 'cms.blueprints.view']);
    expect(siteCodeTool($token, 'get_settings', ['section' => 'code'])['values']['bodyEnd'])->toBe('<div id="chat"></div>')
        ->and(siteCodeTool($token, 'cms_get_template_guide')['site_code'])->toBeTrue();

    config(['sunrice-cms.site.custom_code' => false]);
    expect(siteCodeTool($token, 'get_settings', ['section' => 'code']))->toContain('No such settings section')
        ->and(siteCodeTool($token, 'update_settings', ['section' => 'code', 'values' => ['head' => '<script>evil()</script>']]))->toContain('No such settings section')
        ->and(array_column(siteCodeTool($token, 'get_panel_info')['settings'], 'key'))->not->toContain('code')
        ->and(siteCodeTool($token, 'cms_get_template_guide')['site_code'])->toBeFalse()
        ->and(app(CodeSettings::class)->head)->toBe('<script src="https://stats.example.com/a.js"></script>');
});

it('never imports site code from a structure file', function (bool $on) {
    config(['sunrice-cms.site.custom_code' => $on]);

    expect(fn () => app(StructureImporter::class)->import(['sunrice' => 1, 'settings' => ['code' => ['head' => '<script>evil()</script>']]]))
        ->toThrow(Exception::class, 'Only the site\'s settings');
    expect(app(CodeSettings::class)->head)->toBe('<script src="https://stats.example.com/a.js"></script>');
})->with([true, false]);
