<?php

declare(strict_types=1);

use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Fields\Field;
use Sunrice\Cms\Fields\Schema;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Support\HtmlSanitizer;
use Sunrice\Cms\Translation\LlmTranslator;
use Sunrice\Cms\Translation\Translator;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;

/*
 * The image styles of config sunrice-admin.rich_text.image_classes in the
 * CMS's rich text: kept when saved, offered to the editor, kept through
 * machine translation.
 */

beforeEach(function () {
    config(['sunrice-admin.rich_text.image_classes' => ['rounded' => 'Rounded', 'shadow' => 'Shadow']]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser());
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [
        ['handle' => 'body', 'type' => 'rich_text'],
        ['handle' => 'about', 'type' => 'group', 'fields' => [['handle' => 'note', 'type' => 'rich_text']]],
    ]]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}']);
});

it('keeps the configured image styles when an entry is saved, drops other classes, and shows them on the site', function () {
    $html = '<p><img src="/media/saw.jpg" alt="A saw" class="sunrice-img sunrice-img-w-50 rounded wide shadow" data-media-id="3"></p>';
    $translation = app(CreateEntry::class)->handle($this->blog, ['title' => 'Pictures', 'data' => ['body' => '<p>Draft</p>']]);
    $translation = app(SaveDraft::class)->handle($translation, ['data' => ['body' => $html, 'about' => ['note' => '<img src="/a.jpg" class="shadow hero">']]]);

    expect($translation->draft_data['body'])->toContain('class="sunrice-img sunrice-img-w-50 rounded shadow"')->not->toContain('wide')
        ->and($translation->draft_data['about']['note'])->toContain('class="shadow"')->not->toContain('hero');

    app(PublishEntry::class)->handle($translation);
    get('/blog/pictures')->assertOk()->assertSee('class="sunrice-img sunrice-img-w-50 rounded shadow"', false)->assertDontSee('wide', false);
});

it('drops a style taken out of config the next time the text is saved, but shows saved text as it is', function () {
    $translation = app(CreateEntry::class)->handle($this->blog, ['title' => 'Pictures', 'data' => ['body' => '<p><img src="/a.jpg" alt="A" class="rounded shadow"></p>']]);
    expect(HtmlSanitizer::sanitize('<img src="/a.jpg" class="rounded">'))->toContain('class="rounded"');
    app(PublishEntry::class)->handle($translation);

    // The shared sanitiser reads the config each time.
    config(['sunrice-admin.rich_text.image_classes' => ['shadow' => 'Shadow']]);
    get('/blog/pictures')->assertOk()->assertSee('class="rounded shadow"', false);

    $saved = app(SaveDraft::class)->handle(EntryTranslation::query()->sole(), ['data' => ['body' => (string) $translation->fresh()?->draft_data['body']]]);
    expect($saved->draft_data['body'])->toContain('class="shadow"')->not->toContain('rounded');
});

it('hands rich text fields the image styles, nested ones too', function () {
    $fields = Schema::make([Field::make('body', 'rich_text'), Field::make('intro', 'text')])->toAdmin();

    expect($fields[0]['imageStyles'])->toBe([['class' => 'rounded', 'label' => 'Rounded'], ['class' => 'shadow', 'label' => 'Shadow']])
        ->and($fields[1])->not->toHaveKey('imageStyles');

    $nested = Schema::make(Field::listFromArray([['handle' => 'about', 'type' => 'group', 'fields' => [['handle' => 'note', 'type' => 'rich_text']]]]))->toAdmin();
    expect($nested[0]['fields'][0]['imageStyles'])->toHaveCount(2);
});

it('keeps image styles through machine translation', function () {
    config(['sunrice-cms.locales' => ['en', 'id'], 'sunrice-cms.translation.driver' => null, 'inertia.testing.ensure_pages_exist' => false]);
    // An LLM translator sends the tags masked and puts them back as they were.
    [$masked, $tags] = LlmTranslator::mask('<p>A <img src="/a.jpg" class="sunrice-img sunrice-img-left rounded"> saw</p>');
    expect($masked)->not->toContain('rounded')->and(LlmTranslator::unmask($masked, $tags))->toContain('class="sunrice-img sunrice-img-left rounded"');

    app()->instance(Translator::class, new class implements Translator
    {
        public function translate(array $strings, string $from, string $to, ?string $context = null): array
        {
            return array_map(fn (string $text): string => str_replace('Hello', 'Halo', $text), $strings);
        }
    });
    app(CreateEntry::class)->handle($this->blog, ['title' => 'Hello', 'data' => ['body' => '<p>Hello <img src="/a.jpg" alt="A" class="rounded shadow"></p>']]);

    artisan('sunrice:translate', ['--only' => ['entries']])->assertSuccessful();

    expect(EntryTranslation::query()->where('locale', 'id')->sole()->draft_data['body'])->toBe('<p>Halo <img src="/a.jpg" alt="A" class="rounded shadow" /></p>');
});
