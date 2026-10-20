<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Globals\SaveGlobals;
use Sunrice\Cms\Actions\Menus\SaveMenu;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\GlobalSet;
use Sunrice\Cms\Models\Menu;
use Sunrice\Cms\Models\MenuItem;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\TermTranslation;
use Sunrice\Cms\Translation\GeminiTranslator;
use Sunrice\Cms\Translation\OpenRouterTranslator;
use Sunrice\Cms\Translation\TranslatableStrings;
use Sunrice\Cms\Translation\Translator;
use Sunrice\Cms\Translation\TranslatorFactory;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/** Gemini answering each text with "ID: " before it (placeholders kept). */
function fakeGemini(?Closure $change = null): void
{
    Http::fake(['generativelanguage.googleapis.com/*' => function (Request $request) use ($change) {
        $texts = json_decode((string) data_get($request->data(), 'contents.0.parts.0.text'), true);
        $answer = array_map(fn (string $text): string => $change !== null ? $change($text) : 'ID: '.$text, $texts);

        return Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($answer, JSON_FORCE_OBJECT)]]]]]]);
    }]);
}

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id'], 'sunrice-cms.translation.driver' => 'gemini', 'sunrice-cms.translation.gemini_key' => 'secret']);
    Sleep::fake();
    actingAs(adminUser());
    Blueprint::query()->create(['handle' => 'dish', 'title' => 'Dish', 'fields' => [
        ['handle' => 'intro', 'type' => 'textarea'],
        ['handle' => 'body', 'type' => 'rich_text'],
        ['handle' => 'price', 'type' => 'number'],
        ['handle' => 'code', 'type' => 'text', 'translatable' => false],
        ['handle' => 'sides', 'type' => 'repeater', 'fields' => [['handle' => 'name', 'type' => 'text'], ['handle' => 'extra', 'type' => 'number']]],
    ]]);
    $this->dishes = Collection::query()->create(['handle' => 'dishes', 'title' => 'Dishes', 'blueprint' => 'dish', 'route' => '/menu/{slug}']);
    $this->coffee = app(CreateEntry::class)->handle($this->dishes, ['title' => 'Coffee', 'data' => [
        'intro' => 'Strong', 'body' => '<p>Hot <strong>and</strong> black</p>', 'price' => 30, 'code' => 'C1',
        'sides' => [['_id' => 's1', 'name' => 'Milk', 'extra' => 2]],
    ]]);
});

it('sends texts in batches with their HTML kept out of the model\'s hands', function () {
    config(['sunrice-cms.translation.batch_size' => 2]);
    $drop = false;
    fakeGemini(function (string $text) use (&$drop): string {
        return $drop ? str_replace('⟦1⟧', '', $text) : 'ID: '.$text;
    });
    $translated = app(TranslatorFactory::class)->make()->translate(['a' => 'One', 'b' => '<p>Two <em>2</em></p>', 'c' => 'Three'], 'en', 'id');

    expect($translated)->toBe(['a' => 'ID: One', 'b' => 'ID: <p>Two <em>2</em></p>', 'c' => 'ID: Three']);
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => $request->hasHeader('x-goog-api-key', 'secret')
        && str_contains($request->url(), 'models/gemini-2.5-flash:generateContent')
        && ! str_contains((string) data_get($request->data(), 'contents.0.parts.0.text'), '<em>'));

    // Placeholders that don't come back once each: not translated.
    $drop = true;
    expect(app(TranslatorFactory::class)->make()->translate(['b' => '<p>Two <em>2</em></p>', 'c' => 'Three'], 'en', 'id'))->toBe(['c' => 'Three']);
});

it('retries, and tells when the service fails', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
        ->push('busy', 503)
        ->push(['candidates' => [['content' => ['parts' => [['text' => '{"0": "Satu"}']]]]]])
        ->push(['error' => ['message' => 'Bad key']], 403)]);

    $gemini = app(TranslatorFactory::class)->make();
    expect($gemini->translate(['a' => 'One'], 'en', 'id'))->toBe(['a' => 'Satu'])
        ->and(fn () => $gemini->translate(['a' => 'One'], 'en', 'id'))->toThrow(RuntimeException::class, 'Gemini answered 403: Bad key');
});

it('makes the configured translator, or none', function () {
    expect(app(TranslatorFactory::class)->configured())->toBeTrue()
        ->and(app(TranslatorFactory::class)->make())->toBeInstanceOf(GeminiTranslator::class);

    config(['sunrice-cms.translation.driver' => 'openrouter', 'sunrice-cms.translation.openrouter_key' => 'or-key', 'sunrice-cms.translation.model' => 'some/model']);
    Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => '{"0": "Satu"}']]]])]);
    expect(app(TranslatorFactory::class)->make())->toBeInstanceOf(OpenRouterTranslator::class)
        ->and(app(TranslatorFactory::class)->make()->translate(['a' => 'One'], 'en', 'id'))->toBe(['a' => 'Satu']);
    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer or-key') && data_get($request->data(), 'model') === 'some/model');

    config(['sunrice-cms.translation.driver' => null]);
    expect(app(TranslatorFactory::class)->configured())->toBeFalse()
        ->and(fn () => app(TranslatorFactory::class)->make())->toThrow(RuntimeException::class, 'SUNRICE_TRANSLATE_DRIVER');

    // An app's own translator.
    $own = new class implements Translator
    {
        public function translate(array $strings, string $from, string $to, ?string $context = null): array
        {
            return array_map(strrev(...), $strings);
        }
    };
    app()->instance(Translator::class, $own);
    expect(app(TranslatorFactory::class)->configured())->toBeTrue()
        ->and(app(TranslatorFactory::class)->make())->toBe($own);
});

it('finds the text to translate, in rows too, but not the shared fields', function () {
    $strings = TranslatableStrings::collect($this->dishes->schema(), $this->coffee->draft_data);

    expect(array_keys($strings))->toBe(['intro', 'body', 'sides.0.name'])
        ->and($strings['body'])->toBe(['source' => '<p>Hot <strong>and</strong> black</p>', 'current' => '<p>Hot <strong>and</strong> black</p>', 'rich' => true]);
});

it('translates entries into their drafts, only what isn\'t translated yet', function () {
    fakeGemini();
    artisan('sunrice:translate', ['--dry-run' => true])->assertSuccessful()->expectsOutputToContain('4 texts would be translated');
    Http::assertNothingSent();

    artisan('sunrice:translate', ['--only' => ['entries']])->assertSuccessful()->expectsOutputToContain('Translated 4 texts');
    $id = EntryTranslation::query()->where('locale', 'id')->firstOrFail();
    expect($id->only(['draft_title', 'draft_slug', 'status']))->toBe(['draft_title' => 'ID: Coffee', 'draft_slug' => 'id-coffee', 'status' => 'draft'])
        ->and($id->draft_data['intro'])->toBe('ID: Strong')
        ->and($id->draft_data['body'])->toBe('ID: <p>Hot <strong>and</strong> black</p>')
        ->and($id->draft_data['sides'][0])->toMatchArray(['_id' => 's1', 'name' => 'ID: Milk'])->not->toHaveKey('extra')
        ->and($id->draft_data)->not->toHaveKeys(['price', 'code'])
        ->and($id->title)->toBeNull();

    // What's translated stays; a text still the main language's is translated.
    $id->forceFill(['draft_data' => ['intro' => 'Kental'] + $id->draft_data])->save();
    $this->coffee->forceFill(['draft_data' => ['intro' => 'Very strong', 'body' => '<p>New</p>'] + $this->coffee->draft_data])->save();
    artisan('sunrice:translate', ['--to' => ['id']])->assertSuccessful()->expectsOutputToContain('Translated 0 texts; 4 were translated already');

    artisan('sunrice:translate', ['--force' => true, '--collection' => ['dishes']])->assertSuccessful();
    expect($id->fresh()?->draft_data['intro'])->toBe('ID: Very strong')
        ->and($id->fresh()?->draft_title)->toBe('ID: Coffee');

    artisan('sunrice:translate', ['--to' => ['fr']])->assertFailed();
    artisan('sunrice:translate', ['--only' => ['pages']])->assertFailed();
});

it('translates terms, globals and menu labels', function () {
    fakeGemini();
    $tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    $news = app(SaveTerm::class)->handle($tags, ['title' => 'News']);
    Blueprint::query()->create(['handle' => 'contact', 'title' => 'Contact', 'fields' => [['handle' => 'address', 'type' => 'text'], ['handle' => 'open', 'type' => 'toggle']]]);
    $contact = GlobalSet::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'contact']);
    app(SaveGlobals::class)->handle($contact, ['address' => 'Main St', 'open' => true]);
    $menu = Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    app(SaveMenu::class)->handle($menu, [['url' => '/about', 'labels' => ['en' => 'About us']]]);

    artisan('sunrice:translate', ['--only' => ['terms', 'globals', 'menus']])->assertSuccessful();

    expect(TermTranslation::query()->where('term_id', $news->id)->where('locale', 'id')->value('title'))->toBe('ID: News')
        ->and($contact->fresh()?->data('id'))->toBe(['address' => 'ID: Main St', 'open' => true])
        ->and(MenuItem::query()->firstOrFail()->labels)->toBe(['en' => 'About us', 'id' => 'ID: About us'])
        ->and(EntryTranslation::query()->where('locale', 'id')->exists())->toBeFalse();
});

it('translates the app\'s language files', function () {
    fakeGemini();
    $lang = sys_get_temp_dir().'/sunrice-lang-'.uniqid();
    File::ensureDirectoryExists("{$lang}/en");
    File::put("{$lang}/en/shop.php", "<?php return ['cart' => 'Cart', 'checkout' => ['title' => 'Pay :amount']];");
    File::put("{$lang}/en.json", json_encode(['Sign in' => 'Sign in', 'Hello' => 'Hello']));
    File::put("{$lang}/id.json", json_encode(['Hello' => 'Halo', 'Extra' => 'Tambahan']));
    app()->useLangPath($lang);

    artisan('sunrice:translate', ['--only' => ['lang']])->assertSuccessful();

    expect(require "{$lang}/id/shop.php")->toBe(['cart' => 'ID: Cart', 'checkout' => ['title' => 'ID: Pay :amount']])
        ->and(json_decode(File::get("{$lang}/id.json"), true))->toBe(['Sign in' => 'ID: Sign in', 'Hello' => 'Halo', 'Extra' => 'Tambahan']);
    File::deleteDirectory($lang);
});

it('translates from the main language in the editor, when a service is set up', function () {
    fakeGemini();
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.translate'));
    $id = app(CreateTranslation::class)->handle($this->coffee->entry, 'id');
    $entry = $this->coffee->entry_id;

    get(entryEditUrl($entry))->assertInertia(fn (Assert $page) => $page->where('machineTranslate', null));
    get(entryEditUrl($entry, 'id'))->assertInertia(fn (Assert $page) => $page->where('machineTranslate', $id->id));

    // Translators may not edit the main language.
    post("/admin/cms/entries/{$this->coffee->entry_id}/translations/{$this->coffee->locale}/translate")->assertForbidden();
    post("/admin/cms/entries/{$id->entry_id}/translations/id/translate", ['version' => $id->version + 1])->assertSessionHasErrors('version');
    post("/admin/cms/entries/{$id->entry_id}/translations/id/translate", ['version' => $id->version])->assertRedirect()->assertSessionHas('success');
    expect($id->fresh()?->draft_data['intro'])->toBe('ID: Strong');

    config(['sunrice-cms.translation.driver' => null]);
    get(entryEditUrl($entry, 'id'))->assertInertia(fn (Assert $page) => $page->where('machineTranslate', null));
    post("/admin/cms/entries/{$id->entry_id}/translations/id/translate")->assertNotFound();
});

it('machine-translates with the cms_translate_entry tool', function () {
    fakeGemini();
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    app(SyncPermissions::class)->handle();
    $role = Role::findOrCreate('agent', 'web')->givePermissionTo([Panel::ACCESS, 'sunrice.ai.connect', 'cms.entries.view', 'cms.entries.create', 'cms.entries.update']);
    $user = User::query()->create(['name' => 'Rina', 'email' => 'agent@example.com', 'password' => 'x'])->assignRole($role);
    $token = AiToken::issue((string) $user->id, 'Claude')[1];
    $call = fn (array $arguments) => test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'cms_translate_entry', 'arguments' => $arguments]], ['Authorization' => "Bearer {$token}"]);

    $result = json_decode((string) $call(['id' => $this->coffee->entry_id, 'locale' => 'id', 'machine' => true])->json('result.content.0.text'), true);
    expect($result)->toMatchArray(['added' => true, 'machine_translated' => 4, 'locale' => 'id', 'title' => 'ID: Coffee']);

    config(['sunrice-cms.translation.driver' => null]);
    expect($call(['id' => $this->coffee->entry_id, 'locale' => 'id', 'machine' => true])->json('result.isError'))->toBeTrue();
});
