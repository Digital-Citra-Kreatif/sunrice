<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Install\ScaffoldCollections;
use Sunrice\Cms\Actions\Install\WriteAgentsFile;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Content;
use Sunrice\Cms\Structure\StructureImporter;
use Workbench\App\Models\User;

use function Pest\Laravel\get;

/*
 * The guide for AI agents (docs/building-a-site.md), the Laravel Boost
 * guideline and the AGENTS.md section name components, helpers, config
 * keys, commands and MCP tools: each must exist, so the guide can't drift
 * from the code. Its worked example is imported and rendered.
 */

const GUIDE = __DIR__.'/../../packages/cms/docs/building-a-site.md';
const BOOST_GUIDELINE = __DIR__.'/../../packages/cms/resources/boost/guidelines/core.blade.php';
const AGENTS_NOTES = __DIR__.'/../../packages/cms/stubs/agents.md';

/** @return list<string> the texts the claims are read from */
function agentGuideTexts(): array
{
    return array_map(fn (string $file): string => (string) file_get_contents($file), [GUIDE, BOOST_GUIDELINE, AGENTS_NOTES]);
}

/** @return list<string> every distinct first capture of $pattern in the texts */
function agentGuideMatches(string $pattern): array
{
    $found = [];
    foreach (agentGuideTexts() as $text) {
        preg_match_all($pattern, $text, $matches);
        array_push($found, ...$matches[1]);
    }
    $found = array_values(array_unique($found));
    sort($found);

    return $found;
}

/** A fenced block of the guide's worked example, by the file its info string names (```blade resources/views/…). */
function guideExample(string $file): string
{
    preg_match('/^```[a-z]+ '.preg_quote($file, '/').'\n(.*?)\n```$/ms', (string) file_get_contents(GUIDE), $match);

    return $match[1] ?? throw new RuntimeException("No example {$file} in the guide.");
}

/** @return list<string> the names (`protected string $name = '…'`) of every MCP tool the packages have */
function packageToolNames(): array
{
    $names = [];
    foreach (glob(dirname(__DIR__, 2).'/packages/*/src/Ai/{,Tools/}*.php', GLOB_BRACE) ?: [] as $file) {
        if (preg_match("/protected string \\\$name = '([a-z_]+)'/", (string) file_get_contents($file), $match) === 1) {
            $names[] = $match[1];
        }
    }

    return $names;
}

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
});

it('names only components the CMS has', function () {
    $components = agentGuideMatches('/<x-sunrice-cms::([a-z-]+)/');
    $aliases = Blade::getClassComponentAliases();

    expect($components)->toContain('seo', 'link', 'entries', 'form', 'language-switcher', 'code');
    foreach ($components as $component) {
        expect(isset($aliases["sunrice-cms::{$component}"]) || View::exists("sunrice-cms::components.{$component}"))
            ->toBeTrue("<x-sunrice-cms::{$component}> doesn't exist");
    }
});

it('names only methods of the Cms facade and helpers that exist', function () {
    $methods = agentGuideMatches('/\bCms::([a-zA-Z]+)\(/');
    $functions = agentGuideMatches('/\b(cms_[a-z_]+)\(/');

    expect($methods)->toContain('entries', 'menu', 'global', 'page', 'site')
        ->and($functions)->toContain('cms_dir', 'cms_page', 'cms_nonce');
    foreach ($methods as $method) {
        expect(method_exists(Content::class, $method))->toBeTrue("Cms::{$method}() doesn't exist");
    }
    foreach ($functions as $function) {
        expect(function_exists($function))->toBeTrue("{$function}() doesn't exist");
    }
});

it('names only config keys there are', function () {
    $keys = agentGuideMatches('/`(sunrice-(?:cms|admin)\.[a-z_]+(?:\.[a-z_]+)*)`/');

    expect($keys)->toContain('sunrice-cms.locales', 'sunrice-admin.media.image_sizes');
    foreach ($keys as $key) {
        expect(config()->has($key))->toBeTrue("config {$key} doesn't exist");
    }
});

it('names only commands and MCP tools there are', function () {
    $signatures = [];
    foreach (glob(dirname(__DIR__, 2).'/packages/*/src/{Console,Console/Commands}/*.php', GLOB_BRACE) ?: [] as $file) {
        if (preg_match("/\\\$signature = '(sunrice:[a-z-]+)/", (string) file_get_contents($file), $match) === 1) {
            $signatures[] = $match[1];
        }
    }
    $commands = agentGuideMatches('/\b(sunrice:[a-z-]+)/');
    $tools = packageToolNames();
    $named = agentGuideMatches('/`((?:cms|get|list|save|update|upload|read|delete|run|manage)_[a-z_]+)`/');

    expect($commands)->toContain('sunrice:import-structure', 'sunrice:make-templates', 'sunrice:agents')
        ->and(array_diff($commands, $signatures))->toBe([])
        ->and($named)->toContain('cms_render_page', 'cms_save_blueprint', 'save_record', 'read_docs', 'get_panel_info')
        ->and(array_values(array_diff($named, $tools, ['cms_nonce', 'cms_dir', 'cms_page', 'cms_menu', 'cms_global', 'cms_entries', 'cms_locale_urls', 'cms_body_class'])))->toBe([]);
});

it('compiles the Boost guideline as Blade, printing it as written', function () {
    $source = (string) file_get_contents(BOOST_GUIDELINE);
    $rendered = Blade::render($source);

    expect(trim($rendered))->toBe(trim(str_replace(['@verbatim', '@endverbatim'], '', $source)))
        ->and($rendered)->toContain('<x-sunrice-cms::seo />', '{{ cms_dir() }}', '@bodyClass', 'vendor/sunrice/cms/docs/building-a-site.md');
});

it('is read by read_docs, and the template guide and the instructions point to it', function () {
    app(SyncPermissions::class)->handle();
    $role = Role::findOrCreate('guide-reader', 'web')->givePermissionTo([Panel::ACCESS, 'sunrice.ai.connect', 'cms.blueprints.view']);
    $user = User::query()->create(['name' => 'Rina', 'email' => 'guide@example.com', 'password' => 'x'])->assignRole($role);
    $token = AiToken::issue((string) $user->id, 'Claude', readOnly: true)[1];
    $call = fn (string $tool, array $arguments = []) => test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => $arguments]], ['Authorization' => "Bearer {$token}"])
        ->json('result.content.0.text');

    expect($call('read_docs', ['page' => 'cms/building-a-site']))->toBe(file_get_contents(GUIDE))
        ->and(json_decode((string) $call('cms_get_template_guide'), true)['guide'])->toContain('read_docs "cms/building-a-site"')
        ->and(implode("\n", app(Panel::class)->getAiInstructions()))->toContain('read_docs "cms/building-a-site"');
});

it('builds the worked example: imported, then rendered with its layout and home template', function () {
    $views = storage_path('framework/testing/guide-views');
    (new Filesystem)->deleteDirectory($views);
    mkdir("{$views}/cms/collections/pages", 0755, true);
    file_put_contents("{$views}/cms/layout.blade.php", guideExample('resources/views/cms/layout.blade.php'));
    file_put_contents("{$views}/cms/collections/pages/home.blade.php", guideExample('resources/views/cms/collections/pages/home.blade.php'));
    View::addLocation($views);

    try {
        app(StructureImporter::class)->import(json_decode(guideExample('site.json'), true, flags: JSON_THROW_ON_ERROR));

        get('/')->assertOk()
            ->assertSee('<title>Calm websites | Acme Studio</title>', false)
            ->assertSee('<meta name="description" content="We design and build calm websites.">', false)
            ->assertSee('We build calm websites')
            ->assertSee('<a href="'.url('/contact').'" class="button">Get in touch</a>', false)
            ->assertSeeInOrder(['Design', 'Build', 'Care'])
            ->assertSee('href="'.url('/blog/hello').'"', false)
            ->assertSee('Why we started the studio.')
            // The menu, the global set and the site's name in the layout.
            ->assertSee('href="'.url('/blog').'"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Jl. Contoh 1, Denpasar')
            ->assertSee('href="tel:+62361000000"', false)
            ->assertSee('href="https://instagram.com/example" target="_blank"', false)
            ->assertSee('Acme Studio');
        get('/contact')->assertOk();
        get('/blog')->assertOk()->assertSee('Hello');
    } finally {
        (new Filesystem)->deleteDirectory($views);
    }

    $lang = sys_get_temp_dir().'/sunrice-guide-lang-'.uniqid().'.php';
    file_put_contents($lang, guideExample('lang/en/site.php'));
    $words = require $lang;
    unlink($lang);
    expect($words)->toHaveKeys(['menu', 'from_the_blog', 'not_found']);
});

it('writes the Sunrice section of AGENTS.md, replacing only it on another run', function () {
    $dir = sys_get_temp_dir().'/sunrice-agents-'.uniqid();
    mkdir($dir);
    $file = new WriteAgentsFile($dir);

    try {
        expect($file->pending())->toBe(WriteAgentsFile::CREATED)
            ->and($file->handle())->toBe(['agents' => WriteAgentsFile::CREATED, 'claude' => WriteAgentsFile::CLAUDE_CREATED])
            ->and(file_get_contents("{$dir}/AGENTS.md"))->toBe(WriteAgentsFile::section()."\n")
            ->and(file_get_contents("{$dir}/CLAUDE.md"))->toBe("@AGENTS.md\n")
            ->and($file->handle())->toBe(['agents' => WriteAgentsFile::UNCHANGED, 'claude' => WriteAgentsFile::CLAUDE_IMPORTS]);

        // The app's own notes around an older section: only the section changes.
        $old = "# Acme\n\nOur rules.\n\n".WriteAgentsFile::START."\nOld notes.\n".WriteAgentsFile::END."\n\n## More of ours\n";
        file_put_contents("{$dir}/AGENTS.md", $old);
        file_put_contents("{$dir}/CLAUDE.md", "# Claude\n\nOur own instructions.\n");
        expect($file->pending())->toBe(WriteAgentsFile::UPDATED)
            ->and($file->handle())->toBe(['agents' => WriteAgentsFile::UPDATED, 'claude' => WriteAgentsFile::CLAUDE_OWN])
            ->and(file_get_contents("{$dir}/AGENTS.md"))->toBe("# Acme\n\nOur rules.\n\n".WriteAgentsFile::section()."\n\n## More of ours\n")
            // Its own CLAUDE.md is never changed: the note says what to add.
            ->and(file_get_contents("{$dir}/CLAUDE.md"))->toBe("# Claude\n\nOur own instructions.\n")
            ->and(WriteAgentsFile::claudeNote(WriteAgentsFile::CLAUDE_OWN))->toContain('@AGENTS.md');

        // A file without a section gets it at the end.
        file_put_contents("{$dir}/AGENTS.md", "# Acme\n\nOur rules.\n");
        expect($file->handle()['agents'])->toBe(WriteAgentsFile::ADDED)
            ->and(file_get_contents("{$dir}/AGENTS.md"))->toBe("# Acme\n\nOur rules.\n\n".WriteAgentsFile::section()."\n");

        // A start marker without its end: nothing is written.
        file_put_contents("{$dir}/AGENTS.md", "# Acme\n\n".WriteAgentsFile::START."\nhalf\n");
        expect(fn () => $file->handle())->toThrow(RuntimeException::class, 'without');
        expect(file_get_contents("{$dir}/AGENTS.md"))->toBe("# Acme\n\n".WriteAgentsFile::START."\nhalf\n");
    } finally {
        (new Filesystem)->deleteDirectory($dir);
    }
});

it('offers the section on install, and writes it with sunrice:agents', function () {
    $dir = sys_get_temp_dir().'/sunrice-agents-'.uniqid();
    mkdir("{$dir}/app/vendor/composer", 0755, true);
    file_put_contents("{$dir}/app/vendor/composer/installed.json", json_encode(['packages' => []]));
    app()->instance(PackageManifest::class, new PackageManifest(new Filesystem, "{$dir}/app", "{$dir}/app/packages.php"));
    Storage::fake('public');
    app()->instance(WriteAgentsFile::class, new WriteAgentsFile($dir));

    try {
        $this->artisan('sunrice:install', ['--no-user' => true])
            ->expectsConfirmation(ScaffoldCollections::QUESTIONS['pages'], 'no')
            ->expectsConfirmation(ScaffoldCollections::QUESTIONS['posts'], 'no')
            ->expectsConfirmation(WriteAgentsFile::QUESTION_ADD, 'no')
            ->expectsOutputToContain('php artisan sunrice:agents')
            ->assertSuccessful();
        expect(is_file("{$dir}/AGENTS.md"))->toBeFalse();

        file_put_contents("{$dir}/AGENTS.md", "# Acme\n");
        $this->artisan('sunrice:install', ['--no-user' => true])
            ->expectsConfirmation(ScaffoldCollections::QUESTIONS['pages'], 'no')
            ->expectsConfirmation(ScaffoldCollections::QUESTIONS['posts'], 'no')
            ->expectsConfirmation(WriteAgentsFile::QUESTION_ADD, 'yes')
            ->expectsOutputToContain('Sunrice section added')
            ->assertSuccessful();
        expect(file_get_contents("{$dir}/AGENTS.md"))->toBe("# Acme\n\n".WriteAgentsFile::section()."\n");

        // Up to date: not asked again.
        $this->artisan('sunrice:install', ['--no-user' => true])
            ->expectsConfirmation(ScaffoldCollections::QUESTIONS['pages'], 'no')
            ->expectsConfirmation(ScaffoldCollections::QUESTIONS['posts'], 'no')
            ->doesntExpectOutputToContain('AGENTS.md')
            ->assertSuccessful();

        unlink("{$dir}/AGENTS.md");
        $this->artisan('sunrice:agents')->expectsOutputToContain('created, with the Sunrice section')->assertSuccessful();
        $this->artisan('sunrice:agents')->expectsOutputToContain('has the Sunrice section already')->assertSuccessful();
        expect(file_get_contents("{$dir}/AGENTS.md"))->toBe(WriteAgentsFile::section()."\n");
    } finally {
        (new Filesystem)->deleteDirectory($dir);
    }
});
