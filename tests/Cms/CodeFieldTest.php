<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Content;
use Sunrice\Cms\Fields\Field;
use Sunrice\Cms\Fields\FieldTypes;
use Sunrice\Cms\Fields\Schema;
use Sunrice\Cms\Fields\Types\Code;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Structure\ImportReport;
use Sunrice\Cms\Structure\StructureExporter;
use Sunrice\Cms\Structure\StructureImporter;
use Sunrice\Cms\Translation\TranslatableStrings;
use Workbench\App\Models\User;

use function Pest\Laravel\get;

const CODE_FIELD_SNIPPET = "<script>\n    alert('hi & bye');\n</script>";

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    app(SyncPermissions::class)->handle();
    Blueprint::query()->create(['handle' => 'snippet', 'title' => 'Snippet', 'fields' => [
        ['handle' => 'snippet', 'type' => 'code', 'label' => 'Snippet', 'config' => ['language' => 'html', 'max' => 200, 'placeholder' => '<div>…</div>']],
        ['handle' => 'settings_json', 'type' => 'code', 'translatable' => true, 'config' => ['language' => 'json']],
    ]]);
    $this->snippets = Collection::query()->create(['handle' => 'snippets', 'title' => 'Snippets', 'blueprint' => 'snippet', 'route' => '/snippets/{slug}']);
});

function codeEntry(array $data): EntryTranslation
{
    return app(PublishEntry::class)->handle(app(CreateEntry::class)->handle(test()->snippets, ['title' => 'Embed', 'data' => $data]));
}

function codeAgentToken(): string
{
    $role = Role::findOrCreate('code-agent', 'web')->givePermissionTo([Panel::ACCESS, 'sunrice.ai.connect', 'cms.entries.view', 'cms.entries.create', 'cms.entries.update', 'cms.blueprints.view']);
    $user = User::query()->create(['name' => 'Rina', 'email' => uniqid().'@example.com', 'password' => 'x'])->assignRole($role);

    return AiToken::issue((string) $user->id, 'Claude')[1];
}

function codeAgentTool(string $token, string $tool, array $arguments = []): array|string
{
    $response = test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => $arguments]], ['Authorization' => "Bearer {$token}"]);
    $text = $response->json('result.content.0.text') ?? $response->json('error.message');

    return $response->json('result.isError') === true || $response->json('error') !== null ? (string) $text : json_decode((string) $text, true);
}

it('is a built-in type: a string up to its maximum, shared by all languages, never sorted on', function () {
    $type = app(FieldTypes::class)->get('code');
    $field = Field::make('snippet', 'code', config: ['max' => 10]);

    expect($type)->toBeInstanceOf(Code::class)
        ->and($type->label())->toBe('Code')
        ->and($type->translatableByDefault())->toBeFalse()
        ->and($type->sortCast())->toBeNull()
        ->and($type->rules($field))->toBe(['string', 'max:10'])
        ->and($type->rules(Field::make('snippet', 'code')))->toBe(['string', 'max:65535'])
        ->and(array_map(fn (Field $setting) => $setting->handle, $type->settings()))->toBe(['language', 'max', 'placeholder'])
        // Indentation and inner spaces kept, Windows line endings made \n, blank is nothing.
        ->and($type->normalize("  <p>\r\n    x\r\n</p>\n", $field))->toBe("  <p>\n    x\n</p>\n")
        ->and($type->normalize("  \n ", $field))->toBeNull()
        ->and(Code::language(Field::make('a', 'code', config: ['language' => 'css'])))->toBe('css')
        ->and(Code::language(Field::make('a', 'code', config: ['language' => 'php'])))->toBe('plain');
});

it('validates and stores the code as typed', function () {
    $entry = codeEntry(['snippet' => CODE_FIELD_SNIPPET]);
    expect($entry->fresh()?->data['snippet'])->toBe(CODE_FIELD_SNIPPET);

    expect(fn () => app(SaveDraft::class)->handle($entry, ['data' => ['snippet' => str_repeat('x', 201)]]))->toThrow(ValidationException::class);
    expect(fn () => app(SaveDraft::class)->handle($entry, ['data' => ['snippet' => ['not' => 'code']]]))->toThrow(ValidationException::class);
});

it('gives templates the string, escaped with {{ }} and on the CMS\'s own page', function () {
    codeEntry(['snippet' => CODE_FIELD_SNIPPET]);
    $values = Content::entries('snippets')->get()->first()?->values() ?? [];

    expect($values['snippet'])->toBe(CODE_FIELD_SNIPPET)
        ->and(Blade::render('<p>{{ $values[\'snippet\'] }}</p>', ['values' => $values]))->toBe('<p>&lt;script&gt;
    alert(&#039;hi &amp; bye&#039;);
&lt;/script&gt;</p>');

    $page = (string) get('/snippets/embed')->assertOk()->getContent();
    expect($page)->toContain('&lt;script&gt;')->not->toContain("<script>\n    alert");
});

it('tells templates which fields are code, and the CMS\'s own page shows them as code blocks', function () {
    codeEntry(['snippet' => CODE_FIELD_SNIPPET, 'settings_json' => '{"a": 1}']);

    $response = get('/snippets/embed')->assertOk()->assertViewHas('code', ['snippet' => 'html', 'settings_json' => 'json']);
    expect((string) $response->getContent())
        ->toContain("<pre><code class=\"language-html\">&lt;script&gt;\n    alert(&#039;hi &amp; bye&#039;);\n&lt;/script&gt;</code></pre>")
        ->toContain('<pre><code class="language-json">{&quot;a&quot;: 1}</code></pre>')
        ->not->toContain('<p>&lt;script&gt;');
});

it('prints a code block: <pre><code class="language-…">, escaped', function () {
    $html = Blade::render('<x-sunrice-cms::code-block :value="$code" language="html" class="example" />', ['code' => CODE_FIELD_SNIPPET]);

    expect(trim($html))->toBe('<pre class="example"><code class="language-html">&lt;script&gt;
    alert(&#039;hi &amp; bye&#039;);
&lt;/script&gt;</code></pre>');

    expect(trim(Blade::render('<x-sunrice-cms::code-block :value="$code" />', ['code' => '{"a": 1}'])))->toBe('<pre><code>{&quot;a&quot;: 1}</code></pre>')
        // A language that isn't a plain name is left out, never printed into the attribute.
        ->and(trim(Blade::render('<x-sunrice-cms::code-block value="x" language=\'html" onclick="x\' />')))->toBe('<pre><code>x</code></pre>')
        ->and(trim(Blade::render('<x-sunrice-cms::code-block :value="null" language="css" />')))->toBe('');
});

it('lists the type for agents and saves and reads its value as a string', function () {
    $token = codeAgentToken();

    $types = collect(codeAgentTool($token, 'cms_field_types')['fieldTypes'])->keyBy('type');
    expect($types['code'])->toMatchArray(['label' => 'Code', 'hasChildren' => false, 'translatable' => false])
        ->and(array_column($types['code']['settings'], 'handle'))->toBe(['language', 'max', 'placeholder'])
        ->and(array_column($types['code']['settings'][0]['config']['options'], 'value'))->toBe(Code::LANGUAGES);

    $saved = codeAgentTool($token, 'cms_save_entry', ['collection' => 'snippets', 'title' => 'From the agent', 'data' => ['snippet' => CODE_FIELD_SNIPPET]]);
    expect(codeAgentTool($token, 'cms_get_entry', ['id' => $saved['id']])['values']['data']['snippet'])->toBe(CODE_FIELD_SNIPPET)
        ->and(codeAgentTool($token, 'cms_save_entry', ['id' => $saved['id'], 'data' => ['snippet' => str_repeat('x', 201)]]))->toContain('200');
});

it('round-trips through a structure file', function () {
    codeEntry(['snippet' => CODE_FIELD_SNIPPET, 'settings_json' => '{"a": 1}']);
    $exported = json_decode(json_encode(app(StructureExporter::class)->export(content: true), JSON_THROW_ON_ERROR), true);

    $blueprint = collect($exported['blueprints'])->firstWhere('handle', 'snippet');
    expect($blueprint['fields'][0])->toMatchArray(['handle' => 'snippet', 'type' => 'code', 'config' => ['language' => 'html', 'max' => 200, 'placeholder' => '<div>…</div>']])
        ->and($exported['entries'][0]['translations']['en']['data'])->toBe(['snippet' => CODE_FIELD_SNIPPET, 'settings_json' => '{"a": 1}']);

    expect(app(StructureImporter::class)->import($exported)->items(ImportReport::REFUSED))->toBe([]);
    $exported = json_decode(json_encode(app(StructureExporter::class)->export(content: true), JSON_THROW_ON_ERROR), true);
    expect(collect($exported['blueprints'])->firstWhere('handle', 'snippet')['fields'][1])->toMatchArray(['handle' => 'settings_json', 'type' => 'code', 'translatable' => true, 'config' => ['language' => 'json']])
        ->and($exported['entries'][0]['translations']['en']['data'])->toBe(['snippet' => CODE_FIELD_SNIPPET, 'settings_json' => '{"a": 1}']);
    $again = app(StructureImporter::class)->import($exported);
    expect($again->status('blueprint', 'snippet'))->toBe(ImportReport::UNCHANGED)
        ->and($again->status('entry', 'snippets/embed'))->toBe(ImportReport::UNCHANGED);
});

it('is never machine-translated, even when translated per language', function () {
    $schema = Schema::make([new Field('title', 'text', translatable: true), new Field('data', 'code', translatable: true)]);

    [$target, $strings] = TranslatableStrings::pairs($schema, [], ['title' => 'Hello', 'data' => '{"greeting": "Hello"}']);

    expect(array_keys($strings))->toBe(['title'])
        // The translation starts with the code as it is.
        ->and($target['data'])->toBe('{"greeting": "Hello"}');
});
