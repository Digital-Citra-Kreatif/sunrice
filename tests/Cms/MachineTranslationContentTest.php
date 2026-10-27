<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Translation\MessageSelector;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Collections\SaveListing;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\MachineTranslateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Menus\SaveMenu;
use Sunrice\Cms\Actions\Taxonomies\SaveIndexPage;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Actions\Terms\SaveTermContent;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Fields\CustomField;
use Sunrice\Cms\Fields\FieldTypes;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\CollectionListing;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Fieldset;
use Sunrice\Cms\Models\Form;
use Sunrice\Cms\Models\Menu;
use Sunrice\Cms\Models\MenuItem;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\TaxonomyListing;
use Sunrice\Cms\Models\TermTranslation;
use Sunrice\Cms\Settings\LocaleSettings;
use Sunrice\Cms\Settings\SiteSettings;
use Sunrice\Cms\Translation\Translator;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

/*
 * What `sunrice:translate` and the editor's "Translate from …" send to the
 * translation service and write back, against the current content model.
 * The service is a fake: "{TO}: " before each text, every text sent kept.
 */

/**
 * @param  Closure(): void|null  $during  run while "translating" (someone else saving meanwhile)
 * @return ArrayObject<int, string> the texts sent
 */
function fakeTranslator(?Closure $during = null): ArrayObject
{
    $sent = new ArrayObject;
    app()->instance(Translator::class, new class($sent, $during) implements Translator
    {
        /** @param ArrayObject<int, string> $sent */
        public function __construct(private readonly ArrayObject $sent, private ?Closure $during) {}

        public function translate(array $strings, string $from, string $to, ?string $context = null): array
        {
            foreach ($strings as $text) {
                $this->sent[] = $text;
            }
            if ($this->during !== null) {
                [$during, $this->during] = [$this->during, null];
                $during();
            }

            return array_map(fn (string $text): string => strtoupper($to).': '.$text, $strings);
        }
    });

    return $sent;
}

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id'], 'sunrice-cms.translation.driver' => null, 'inertia.testing.ensure_pages_exist' => false]);
    Http::preventStrayRequests();
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser());
    app(FieldTypes::class)->register(new class extends CustomField
    {
        public function type(): string
        {
            return 'headline';
        }

        public function baseType(): string
        {
            return 'text';
        }
    });
    Fieldset::query()->create(['handle' => 'quote', 'title' => 'Quote', 'fields' => [
        ['handle' => 'words', 'type' => 'textarea'],
        ['handle' => 'stars', 'type' => 'number'],
    ]]);
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [
        ['handle' => 'intro', 'type' => 'text', 'hint' => 'Shown in lists', 'hint_tooltip' => 'One line', 'width' => 50, 'config' => ['placeholder' => 'A short intro', 'prefix' => '»', 'suffix' => '«']],
        ['handle' => 'kicker', 'type' => 'headline'],
        ['handle' => 'featured', 'type' => 'terms', 'translatable' => true, 'config' => ['taxonomy' => 'topics', 'multiple' => true]],
        ['handle' => 'about', 'type' => 'group', 'fields' => [['handle' => 'note', 'type' => 'rich_text'], ['handle' => 'sku', 'type' => 'text', 'translatable' => false]]],
        ['handle' => 'more', 'type' => 'flexible', 'config' => ['blocks' => ['quote']]],
    ]]);
    $this->posts = Collection::query()->create(['handle' => 'posts', 'title' => 'Posts', 'blueprint' => 'post', 'route' => '/posts/{slug}', 'listing_route' => '/posts']);
    $this->topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'route' => '/topics/{slug}']);
    $this->topics->collections()->attach($this->posts);
    $this->news = app(SaveTerm::class)->handle($this->topics, ['title' => 'News']);
    $this->post = app(CreateEntry::class)->handle($this->posts, ['title' => 'Hello', 'terms' => ['topics' => [$this->news->id]], 'data' => [
        'intro' => 'Welcome', 'kicker' => 'Big news', 'featured' => [$this->news->id],
        'about' => ['note' => '<p>Read <em>this</em></p>', 'sku' => 'H-1'],
        'more' => [['id' => 'q1', 'type' => 'quote', 'values' => ['words' => 'Lovely', 'stars' => 5]]],
    ]]);
});

it('translates an entry\'s texts, nested ones and custom text types too, but not its terms or how fields present themselves', function () {
    $sent = fakeTranslator();

    artisan('sunrice:translate', ['--only' => ['entries']])->assertSuccessful()->expectsOutputToContain('Translated 5 texts');

    expect($sent->getArrayCopy())->toEqualCanonicalizing(['Hello', 'Welcome', 'Big news', '<p>Read <em>this</em></p>', 'Lovely']);
    $id = EntryTranslation::query()->where('locale', 'id')->sole();
    expect($id->status)->toBe('draft')
        ->and($id->draft_data['kicker'])->toBe('ID: Big news')
        ->and($id->draft_data['about'])->toBe(['note' => 'ID: <p>Read <em>this</em></p>'] + $id->draft_data['about'])
        ->and($id->draft_data['about'])->not->toHaveKey('sku')
        ->and($id->draft_data['more'][0]['values'])->toMatchArray(['words' => 'ID: Lovely'])
        // A translatable terms field: ids, copied, never sent.
        ->and($id->draft_data['featured'])->toBe([$this->news->id])
        // The entry's own terms (every language's, not published yet) are left alone.
        ->and($id->entry->fresh()?->draftTermIds())->toBe([$this->news->id]);
});

it('translates a row or block from the one of the same id, never from another one at its place', function () {
    fakeTranslator();
    app(SaveDraft::class)->handle($this->post, ['data' => ['more' => [
        ['id' => 'q1', 'type' => 'quote', 'values' => ['words' => 'Lovely', 'stars' => 5]],
        ['id' => 'q2', 'type' => 'quote', 'values' => ['words' => 'Superb', 'stars' => 4]],
    ]]]);
    $id = app(CreateTranslation::class)->handle($this->post->entry, 'id');
    // Its own second block, not yet written: the main language's second block is another one.
    app(SaveDraft::class)->handle($id, ['data' => ['more' => [
        ['id' => 'q1', 'type' => 'quote', 'values' => ['words' => '']],
        ['id' => 'q9', 'type' => 'quote', 'values' => ['words' => '']],
    ]]]);

    app(MachineTranslateEntry::class)->handle($id->fresh() ?? $id);

    $more = ($id->fresh() ?? $id)->draft_data['more'];
    expect($more[0]['values']['words'])->toBe('ID: Lovely')
        ->and($more[1]['id'])->toBe('q9')->and($more[1]['values']['words'] ?? null)->toBeEmpty();
});

it('leaves collections that aren\'t translatable out, without failing or counting them', function () {
    $sent = fakeTranslator();
    $this->posts->update(['translatable' => false]);

    artisan('sunrice:translate', ['--only' => ['entries'], '--dry-run' => true])->assertSuccessful()->expectsOutputToContain('Dry run: 0 texts would be translated');
    artisan('sunrice:translate', ['--only' => ['entries']])->assertSuccessful();

    expect($sent)->toHaveCount(0)
        ->and(EntryTranslation::query()->where('locale', 'id')->exists())->toBeFalse();
});

it('translates a live translation into its draft only, counting its version up', function () {
    fakeTranslator();
    $id = app(CreateTranslation::class)->handle($this->post->entry, 'id');
    $id = app(PublishEntry::class)->handle(app(SaveDraft::class)->handle($id, ['title' => 'Halo', 'slug' => 'halo', 'version' => $id->version]));
    expect($id->hasUnpublishedChanges())->toBeFalse();
    $version = $id->fresh()?->version;

    app(MachineTranslateEntry::class)->handle($id->fresh() ?? $id, $version);

    $id = $id->fresh();
    expect($id?->only(['title', 'slug', 'draft_title', 'draft_slug', 'status']))->toBe(['title' => 'Halo', 'slug' => 'halo', 'draft_title' => 'Halo', 'draft_slug' => 'halo', 'status' => 'published'])
        ->and($id?->data['intro'] ?? null)->toBe('Welcome')
        ->and($id?->draft_data['intro'])->toBe('ID: Welcome')
        ->and($id?->version)->toBe($version + 1)
        ->and($id?->hasUnpublishedChanges())->toBeTrue()
        ->and($id?->isOutdated())->toBeFalse();
});

it('never writes over a save made while it was translating', function () {
    $id = app(CreateTranslation::class)->handle($this->post->entry, 'id');
    fakeTranslator(function () use ($id): void {
        app(SaveDraft::class)->handle($id->fresh() ?? $id, ['data' => ['intro' => 'Selamat datang']]);
    });

    artisan('sunrice:translate', ['--only' => ['entries']])->assertFailed()->expectsOutputToContain('Someone else saved this entry');

    expect($id->fresh()?->draft_data['intro'])->toBe('Selamat datang')
        ->and($id->fresh()?->draft_title)->toBe('Hello');
});

it('translates terms: their title, fields and SEO, keeping a slug of their own', function () {
    $sent = fakeTranslator();
    app(SaveTermContent::class)->handle($this->news, ['seo' => ['title' => 'All the news', 'description' => 'What happened']]);
    $sport = app(SaveTerm::class)->handle($this->topics, ['title' => 'Sport']);
    app(SaveTerm::class)->handle($this->topics, ['title' => 'Sport', 'slug' => 'olahraga', 'locale' => 'id'], $sport);
    $music = app(SaveTerm::class)->handle($this->topics, ['title' => 'Music']);
    app(SaveTerm::class)->handle($this->topics, ['title' => 'Music', 'locale' => 'id'], $music);

    artisan('sunrice:translate', ['--only' => ['terms']])->assertSuccessful();

    $translation = fn ($term) => TermTranslation::query()->where('term_id', $term->id)->where('locale', 'id')->sole();
    expect($translation($this->news)->only(['title', 'slug']))->toBe(['title' => 'ID: News', 'slug' => 'id-news'])
        ->and($translation($this->news)->data['seo'])->toMatchArray(['title' => 'ID: All the news', 'description' => 'ID: What happened'])
        ->and($translation($sport)->only(['title', 'slug']))->toBe(['title' => 'ID: Sport', 'slug' => 'olahraga'])
        ->and($translation($music)->only(['title', 'slug']))->toBe(['title' => 'ID: Music', 'slug' => 'id-music'])
        ->and($sent->getArrayCopy())->toEqualCanonicalizing(['News', 'All the news', 'What happened', 'Sport', 'Music']);
});

it('translates listing pages and taxonomy index pages, shared and per collection', function () {
    $sent = fakeTranslator();
    Blueprint::query()->create(['handle' => 'index', 'title' => 'Index', 'fields' => [
        ['handle' => 'heading', 'type' => 'text', 'translatable' => true],
        ['handle' => 'columns', 'type' => 'number'],
    ]]);
    $this->posts->update(['listing_blueprint' => 'index']);
    $this->topics->update(['index_route' => '/topics', 'index_blueprint' => 'index']);
    $this->topics->collections()->updateExistingPivot($this->posts->id, ['index_page' => true]);
    app(SaveListing::class)->handle($this->posts->fresh() ?? $this->posts, ['heading' => 'Latest posts', 'columns' => 3], 'en', null, ['title' => 'Our blog']);
    app(SaveIndexPage::class)->handle($this->topics->fresh() ?? $this->topics, null, ['heading' => 'All topics']);
    app(SaveIndexPage::class)->handle($this->topics->fresh() ?? $this->topics, $this->posts, ['heading' => 'Topics of posts']);
    // Translated already: kept.
    app(SaveIndexPage::class)->handle($this->topics->fresh() ?? $this->topics, $this->posts, ['heading' => 'Topik tulisan'], 'id');

    artisan('sunrice:translate', ['--only' => ['pages'], '--dry-run' => true])->assertSuccessful()->expectsOutputToContain('Dry run: 3 texts would be translated, 1 are translated already');
    expect($sent)->toHaveCount(0);

    artisan('sunrice:translate', ['--only' => ['pages']])->assertSuccessful()->expectsOutputToContain('Translated 3 texts');
    $listing = CollectionListing::query()->where('locale', 'id')->sole();
    $index = fn (?int $collection) => TaxonomyListing::query()->where('locale', 'id')->where('collection_id', $collection)->sole()->data;
    expect($listing->data)->toMatchArray(['heading' => 'ID: Latest posts'])->not->toHaveKey('columns')
        ->and($listing->data['seo']['title'])->toBe('ID: Our blog')
        ->and($index(null))->toMatchArray(['heading' => 'ID: All topics'])
        ->and($index($this->posts->id))->toMatchArray(['heading' => 'Topik tulisan']);

    // A page switched off is left alone.
    $this->topics->update(['index_route' => null]);
    artisan('sunrice:translate', ['--only' => ['pages'], '--force' => true, '--dry-run' => true])->assertSuccessful()->expectsOutputToContain('Dry run: 3 texts would be translated');
});

it('translates what visitors read outside entries, as Content → Translations lists it', function () {
    $sent = fakeTranslator();
    app(SiteSettings::class)->fill(['description' => 'A friendly blog'])->save();
    $this->topics->translate('title', 'id', 'Topik')->save();
    Blueprint::query()->create(['handle' => 'contact', 'title' => 'Contact', 'fields' => [
        ['handle' => 'name', 'type' => 'text', 'label' => 'Your name', 'instructions' => 'As on your ID', 'hint' => 'Required', 'config' => ['placeholder' => 'Jane Doe']],
        ['handle' => 'topic', 'type' => 'select', 'label' => 'Topic', 'config' => ['options' => [['value' => 'sales', 'label' => 'Sales']]]],
    ]]);
    Form::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'contact', 'message' => 'Thank you!']);
    $menu = Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    app(SaveMenu::class)->handle($menu, [['url' => '/about', 'labels' => ['en' => 'About us']]]);

    artisan('sunrice:translate', ['--only' => ['texts', 'menus']])->assertSuccessful()->expectsOutputToContain('Translated 8 texts; 1 were translated already');

    // The menu's label once (menus), and nothing editors alone see (hints, placeholders).
    expect($sent->getArrayCopy())->toEqualCanonicalizing(['A friendly blog', 'Posts', 'Thank you!', 'Your name', 'As on your ID', 'Topic', 'Sales', 'About us']);
    $fields = Blueprint::query()->where('handle', 'contact')->sole()->fields;
    expect(app(SiteSettings::class)->descriptionIn('id'))->toBe('ID: A friendly blog')
        ->and($this->posts->fresh()?->titleIn('id'))->toBe('ID: Posts')
        ->and($this->topics->fresh()?->titleIn('id'))->toBe('Topik')
        ->and(Form::query()->sole()->translations)->toBe(['id' => ['message' => 'ID: Thank you!']])
        ->and($fields[0]['translations'])->toBe(['id' => ['label' => 'ID: Your name', 'instructions' => 'ID: As on your ID']])
        ->and($fields[1]['translations'])->toBe(['id' => ['label' => 'ID: Topic', 'options' => ['sales' => 'ID: Sales']]])
        ->and(MenuItem::query()->sole()->labels)->toBe(['en' => 'About us', 'id' => 'ID: About us']);

    // They are the main language's: from another, left out.
    artisan('sunrice:translate', ['--only' => ['texts'], '--from' => 'id', '--to' => ['en']])->assertSuccessful()->expectsOutputToContain('main language only');
});

it('translates into a language added later in Settings → Languages, parts given comma-separated', function () {
    fakeTranslator();
    app(LocaleSettings::class)->fill(['languages' => "en: English\nid: Bahasa Indonesia\nfr: Français"])->save();

    artisan('sunrice:translate', ['--to' => ['fr'], '--only' => ['entries,terms']])->assertSuccessful();

    expect(EntryTranslation::query()->where('locale', 'fr')->sole()->draft_title)->toBe('FR: Hello')
        ->and(TermTranslation::query()->where('locale', 'fr')->sole()->title)->toBe('FR: News');
    artisan('sunrice:translate', ['--only' => ['entries,everything']])->assertFailed();
});

it('translates language files with nested keys, placeholders and plurals kept', function () {
    $sent = fakeTranslator();
    $lang = sys_get_temp_dir().'/sunrice-lang-'.uniqid();
    File::ensureDirectoryExists("{$lang}/en");
    File::put("{$lang}/en/site.php", "<?php return ['nav' => ['home' => 'Home'], 'posts' => '{0} No posts|{1} One post|[2,*] :count posts', 'by' => 'By :Name on :date'];");
    app()->useLangPath($lang);

    artisan('sunrice:translate', ['--only' => ['lang']])->assertSuccessful();

    expect(require "{$lang}/id/site.php")->toBe(['nav' => ['home' => 'ID: Home'], 'posts' => '{0} ID: No posts|{1} ID: One post|[2,*] ID: :count posts', 'by' => 'ID: By :Name on :date'])
        ->and($sent->getArrayCopy())->toContain('No posts', ':count posts')->not->toContain('[2,*] :count posts');
    // Laravel still picks the right choice.
    expect((new MessageSelector)->choose((require "{$lang}/id/site.php")['posts'], 5, 'id'))->toBe('ID: :count posts');

    // A placeholder that doesn't come back: not used.
    app()->instance(Translator::class, new class implements Translator
    {
        public function translate(array $strings, string $from, string $to, ?string $context = null): array
        {
            return array_map(fn (string $text): string => str_replace(':count', ':jumlah', $text), $strings);
        }
    });
    File::put("{$lang}/en/more.php", "<?php return ['left' => ':count left', 'ok' => 'OK'];");
    artisan('sunrice:translate', ['--only' => ['lang'], '--to' => ['id']])->assertFailed();
    expect(require "{$lang}/id/more.php")->toBe(['ok' => 'OK']);
    File::deleteDirectory($lang);
});

it('doesn\'t add a language for an AI agent that may not fill it in', function () {
    fakeTranslator();
    app(SyncPermissions::class)->handle();
    $role = Role::findOrCreate('agent', 'web')->givePermissionTo([Panel::ACCESS, 'sunrice.ai.connect', 'cms.entries.view', 'cms.entries.create']);
    $user = User::query()->create(['name' => 'Rina', 'email' => 'agent@example.com', 'password' => 'x'])->assignRole($role);
    $token = AiToken::issue((string) $user->id, 'Claude')[1];
    $answer = test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'cms_translate_entry', 'arguments' => ['id' => $this->post->entry_id, 'locale' => 'id', 'machine' => true]]], ['Authorization' => "Bearer {$token}"]);

    expect($answer->json('result.isError'))->toBeTrue()
        ->and((string) $answer->json('result.content.0.text'))->toContain('edit this translation')
        ->and(EntryTranslation::query()->where('locale', 'id')->exists())->toBeFalse();
});
