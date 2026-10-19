<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Globals\SaveGlobals;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\Fieldset;
use Sunrice\Cms\Models\GlobalSet;
use Sunrice\Cms\Models\Menu;
use Sunrice\Cms\Models\MenuItem;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Translation\Translator;
use Sunrice\Cms\Translation\Translators;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

/** "Translates" by prefixing the language, and remembers what it was sent. */
final class PrefixTranslator implements Translator
{
    /** @var list<array<string, string>> */
    public array $sent = [];

    public function translate(array $strings, string $from, string $to, ?string $context = null): array
    {
        $this->sent[] = $strings;

        return array_map(fn (string $text): string => str_starts_with($text, '<') ? preg_replace('/>([^<]+)</', ">[{$to}] $1<", $text, 1) : "[{$to}] {$text}", $strings);
    }
}

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    actingAs(adminUser());
    $this->translator = new PrefixTranslator;
    app()->instance(Translator::class, $this->translator);
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [
        ['handle' => 'intro', 'type' => 'text'],
        ['handle' => 'body', 'type' => 'rich_text'],
        ['handle' => 'code', 'type' => 'text', 'translatable' => false],
        ['handle' => 'links', 'type' => 'repeater', 'fields' => [
            ['handle' => 'link', 'type' => 'link'],
            ['handle' => 'sku', 'type' => 'text', 'translatable' => false],
        ]],
    ]]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post']);
});

function translatedPost(): Entry
{
    $post = app(CreateEntry::class)->handle(test()->blog, ['title' => 'Hello world', 'data' => [
        'intro' => 'Welcome', 'body' => '<p>Long text</p>', 'code' => 'A-1',
        'links' => [['link' => ['url' => 'https://example.com', 'label' => 'Read more'], 'sku' => 'X1']],
    ], 'seo' => ['description' => 'About things']]);
    app(PublishEntry::class)->handle($post->fresh() ?? $post);

    return $post->entry;
}

it('translates entries into their drafts, leaving what is live alone', function () {
    $entry = translatedPost();

    artisan('sunrice:translate', ['--only' => ['entries']])->assertSuccessful()->expectsOutputToContain('review and publish');

    $id = $entry->fresh()?->translation('id');
    expect($id?->status)->toBe('draft')->and($id?->title)->toBeNull()
        ->and($id?->draft_title)->toBe('[id] Hello world')
        ->and($id?->draft_slug)->toBe('id-hello-world')
        ->and($id?->draft_data['intro'])->toBe('[id] Welcome')
        ->and($id?->draft_data['body'])->toBe('<p>[id] Long text</p>')
        ->and($id?->draft_data['links'][0]['link'])->toMatchArray(['url' => 'https://example.com', 'label' => '[id] Read more'])
        ->and($id?->draft_data['links'][0]['sku'])->toBe('X1')
        ->and($id?->draft_data['seo']['description'])->toBe('[id] About things')
        ->and(collect($this->translator->sent)->flatten()->all())->not->toContain('A-1', 'X1', 'https://example.com');
});

it('leaves what is translated already, unless forced', function () {
    $entry = translatedPost();
    $id = app(CreateTranslation::class)->handle($entry, 'id');
    app(SaveDraft::class)->handle($id, ['title' => 'Halo dunia', 'data' => ['intro' => 'Selamat datang']]);

    artisan('sunrice:translate', ['--only' => ['entries']])->assertSuccessful();
    $id->refresh();
    expect($id->draft_title)->toBe('Halo dunia')->and($id->draft_slug)->toBe('hello-world')
        ->and($id->draft_data['intro'])->toBe('Selamat datang')
        ->and($id->draft_data['body'])->toBe('<p>[id] Long text</p>');

    artisan('sunrice:translate', ['--only' => ['entries'], '--force' => true])->expectsConfirmation('--force translates again, replacing translations people may have edited. Continue?', 'yes')->assertSuccessful();
    expect($id->fresh()?->draft_title)->toBe('[id] Hello world')->and($id->fresh()?->draft_data['intro'])->toBe('[id] Welcome');
});

it('counts without sending in a dry run', function () {
    translatedPost();

    artisan('sunrice:translate', ['--dry-run' => true, '--only' => ['entries']])->assertSuccessful()->expectsOutputToContain('Dry run: 5 to translate');

    expect($this->translator->sent)->toBe([])->and(Entry::query()->first()?->translations()->count())->toBe(1);
});

it('translates terms, globals and menu labels', function () {
    $tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    $news = app(SaveTerm::class)->handle($tags, ['title' => 'News']);
    Blueprint::query()->create(['handle' => 'contact', 'title' => 'Contact', 'fields' => [['handle' => 'address', 'type' => 'textarea'], ['handle' => 'phone', 'type' => 'text', 'translatable' => false]]]);
    $contact = GlobalSet::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'contact']);
    app(SaveGlobals::class)->handle($contact, ['address' => '1 Main St', 'phone' => '555']);
    $menu = Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    $item = MenuItem::query()->create(['menu_id' => $menu->id, 'url' => '/about', 'labels' => ['en' => 'About us']]);
    $own = MenuItem::query()->create(['menu_id' => $menu->id, 'url' => '/contact', 'labels' => ['en' => 'Contact', 'id' => 'Kontak']]);

    artisan('sunrice:translate', ['--only' => ['terms', 'globals', 'menus']])->assertSuccessful();

    expect($news->fresh()?->translation('id')?->title)->toBe('[id] News')
        ->and($contact->fresh()?->data('id'))->toMatchArray(['address' => '[id] 1 Main St', 'phone' => '555'])
        ->and($item->fresh()?->labels)->toBe(['en' => 'About us', 'id' => '[id] About us'])
        ->and($own->fresh()?->labels['id'])->toBe('Kontak');
});

it('translates the app\'s language files', function () {
    $lang = sys_get_temp_dir().'/sunrice-lang-'.uniqid();
    File::ensureDirectoryExists("{$lang}/en");
    File::put("{$lang}/en/shop.php", "<?php return ['cart' => 'Cart', 'checkout' => ['title' => 'Pay :amount']];");
    File::put("{$lang}/en.json", json_encode(['Sign in' => 'Sign in', 'Hello' => 'Hello']));
    File::put("{$lang}/id.json", json_encode(['Hello' => 'Halo', 'Extra' => 'Tambahan']));
    app()->useLangPath($lang);

    artisan('sunrice:translate', ['--only' => ['lang']])->assertSuccessful();

    expect(require "{$lang}/id/shop.php")->toBe(['cart' => '[id] Cart', 'checkout' => ['title' => '[id] Pay :amount']])
        ->and(json_decode(File::get("{$lang}/id.json"), true))->toBe(['Sign in' => '[id] Sign in', 'Hello' => 'Halo', 'Extra' => 'Tambahan']);
    File::deleteDirectory($lang);
});

it('refuses unknown languages and parts', function () {
    artisan('sunrice:translate', ['--to' => ['fr']])->assertFailed()->expectsOutputToContain('Not one of the site\'s languages: fr');
    artisan('sunrice:translate', ['--only' => ['pages']])->assertFailed();
});

it('asks Gemini or OpenRouter, in batches', function () {
    config(['sunrice-cms.translation.gemini.key' => 'g-key', 'sunrice-cms.translation.openrouter.key' => 'o-key', 'sunrice-cms.translation.batch_size' => 2]);
    Http::fake([
        'generativelanguage.googleapis.com/*' => fn ($request) => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(array_map(fn (string $t) => "id:{$t}", json_decode($request['contents'][0]['parts'][0]['text'], true)))]]]]]]),
        'openrouter.ai/*' => fn ($request) => Http::response(['choices' => [['message' => ['content' => "```json\n".json_encode(array_map(fn (string $t) => "or:{$t}", json_decode($request['messages'][1]['content'], true)))."\n```"]]]]),
    ]);

    expect(app(Translators::class)->make('gemini')->translate(['a' => 'One', 'b' => 'Two', 'c' => 'Three'], 'en', 'id'))->toBe(['a' => 'id:One', 'b' => 'id:Two', 'c' => 'id:Three'])
        ->and(app(Translators::class)->make('openrouter', 'some/model')->translate(['a' => 'One'], 'en', 'id'))->toBe(['a' => 'or:One']);
    Http::assertSentCount(3);
    Http::assertSent(fn ($request) => $request->hasHeader('x-goog-api-key', 'g-key') && str_contains($request['systemInstruction']['parts'][0]['text'], 'from English (en) to Indonesian (id)'));
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer o-key') && $request['model'] === 'some/model');

    config(['sunrice-cms.translation.gemini.key' => null]);
    expect(fn () => app(Translators::class)->make('gemini'))->toThrow(RuntimeException::class, 'needs an API key');
});

it('translates inside flexible blocks', function () {
    Fieldset::query()->create(['handle' => 'quote', 'title' => 'Quote', 'fields' => [['handle' => 'text', 'type' => 'textarea'], ['handle' => 'cite', 'type' => 'text', 'translatable' => false]]]);
    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => [['handle' => 'blocks', 'type' => 'flexible', 'config' => ['blocks' => ['quote']]]]]);
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page']);
    $page = app(CreateEntry::class)->handle($pages, ['title' => 'About', 'data' => ['blocks' => [['type' => 'quote', 'values' => ['text' => 'Be kind', 'cite' => 'Ann']]]]]);

    artisan('sunrice:translate', ['--only' => ['entries'], '--collection' => ['pages']])->assertSuccessful();

    expect($page->entry->fresh()?->translation('id')?->draft_data['blocks'][0]['values'])->toEqual(['text' => '[id] Be kind', 'cite' => 'Ann'])
        ->and(Entry::query()->where('collection_id', $this->blog->id)->count())->toBe(0);
});
