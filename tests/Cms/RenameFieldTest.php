<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Sunrice\Cms\Actions\Blueprints\RenameFieldHandle;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Globals\SaveGlobals;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Actions\Terms\SaveTermContent;
use Sunrice\Cms\Fields\Fieldsets;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\ContentReference;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Fieldset;
use Sunrice\Cms\Models\GlobalSet;
use Sunrice\Cms\Models\Revision;
use Sunrice\Cms\Models\Taxonomy;

use function Pest\Laravel\artisan;

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    Fieldset::query()->create(['handle' => 'cta', 'title' => 'Call to action', 'fields' => [
        ['handle' => 'label', 'type' => 'text'],
        ['handle' => 'target', 'type' => 'entries', 'config' => ['collections' => ['pages']]],
    ]]);
    $this->page = Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => [
        ['handle' => 'hero', 'type' => 'group', 'fields' => [['handle' => 'title', 'type' => 'text'], ['handle' => 'note', 'type' => 'text']]],
        ['handle' => 'rows', 'type' => 'repeater', 'fields' => [['handle' => 'caption', 'type' => 'text']]],
        ['handle' => 'sections', 'type' => 'flexible', 'config' => ['blocks' => ['cta']]],
        ['handle' => 'related', 'type' => 'entries', 'config' => ['collections' => ['pages'], 'multiple' => true]],
    ]]);
    $this->pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page']);
    $this->about = app(CreateEntry::class)->handle($this->pages, ['title' => 'About'])->entry;
    $this->home = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->pages, ['title' => 'Home', 'data' => [
        'hero' => ['title' => 'Welcome home', 'note' => 'n'],
        'rows' => [['caption' => 'One'], ['caption' => 'Two']],
        'sections' => [['type' => 'cta', 'values' => ['label' => 'Read more', 'target' => $this->about->id]]],
        'related' => [$this->about->id],
    ]]));
    $id = app(CreateTranslation::class)->handle($this->home->entry, 'id');
    $this->homeId = app(SaveDraft::class)->handle($id, ['data' => ['hero' => ['title' => 'Selamat datang', 'note' => 'n']]]);
});

it('renames a field in a group, with drafts, live values, revisions and other languages', function () {
    $version = $this->home->fresh()->version;
    $counts = app(RenameFieldHandle::class)->handle($this->page, 'hero.title', 'heading');

    expect($counts)->toMatchArray(['entries' => 3, 'revisions' => 1]) // About's defaults too
        ->and($this->page->fresh()->schema()->field('hero.heading'))->not->toBeNull()
        ->and($this->page->fresh()->schema()->field('hero.title'))->toBeNull();
    $home = $this->home->fresh();
    expect($home->data['hero'])->toBe(['heading' => 'Welcome home', 'note' => 'n'])
        ->and($home->draft_data['hero'])->toBe(['heading' => 'Welcome home', 'note' => 'n'])
        ->and($home->version)->toBe($version + 1)
        ->and(Revision::query()->where('entry_translation_id', $home->id)->first()->data['hero'])->toBe(['heading' => 'Welcome home', 'note' => 'n'])
        ->and($this->homeId->fresh()->draft_data['hero']['heading'])->toBe('Selamat datang')
        ->and($home->values()['hero']['heading'])->toBe('Welcome home');
});

it('renames a field in a repeater and a top-level one, keeping references and search text', function () {
    app(RenameFieldHandle::class)->handle($this->page, 'rows.caption', 'text');
    app(RenameFieldHandle::class)->handle($this->page, 'related', 'links');

    $home = $this->home->fresh();
    expect(array_map(fn (array $row) => $row['text'] ?? null, $home->data['rows']))->toBe(['One', 'Two'])
        ->and($home->data['rows'][0])->not->toHaveKey('caption')
        ->and(array_keys($home->data))->toBe(['hero', 'rows', 'sections', 'links']) // in its place
        ->and($home->search_text)->toContain('Welcome home')->toContain('One')
        ->and(ContentReference::query()->where('source_id', $home->entry_id)->where('locale', 'en')->orderBy('field')->pluck('field')->all())->toBe(['links', 'sections.0.values.target']);
});

it('renames a fieldset field wherever it is used: blocks, includes, terms and globals', function () {
    Blueprint::query()->create(['handle' => 'footer', 'title' => 'Footer', 'fields' => [['handle' => 'cta', 'type' => 'fieldset', 'config' => ['fieldset' => 'cta']]]]);
    $set = GlobalSet::query()->create(['handle' => 'footer', 'title' => 'Footer', 'blueprint' => 'footer']);
    app(SaveGlobals::class)->handle($set, ['label' => 'Contact us']);
    $topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'blueprint' => 'footer']);
    $news = app(SaveTerm::class)->handle($topics, ['title' => 'News']);
    app(SaveTermContent::class)->handle($news, ['data' => ['label' => 'All news']]);

    $dry = app(RenameFieldHandle::class)->handle(Fieldset::query()->where('handle', 'cta')->first(), 'label', 'text', dryRun: true);
    expect($dry)->toMatchArray(['entries' => 2, 'revisions' => 1, 'globals' => 1, 'terms' => 1]) // both languages' blocks
        ->and($set->values()->first()->data)->toHaveKey('label');

    app(RenameFieldHandle::class)->handle(Fieldset::query()->where('handle', 'cta')->first(), 'label', 'text');
    expect($this->home->fresh()->data['sections'][0]['values'])->toMatchArray(['text' => 'Read more'])
        ->and($set->values()->first()->data)->toBe(['text' => 'Contact us', 'target' => null])
        ->and($news->translations()->first()->data['text'])->toBe('All news')
        ->and(Fieldset::query()->where('handle', 'cta')->first()->schema()->field('text'))->not->toBeNull();
});

it('refuses handles that cannot be used', function (string $from, string $to, string $message) {
    expect(fn () => app(RenameFieldHandle::class)->handle($this->page, $from, $to))->toThrow(ValidationException::class, $message);
})->with([
    'missing' => ['nothing', 'x', 'has no field'],
    'bad' => ['related', 'Not Ok', 'lowercase letters'],
    'taken' => ['hero.title', 'note', 'already is a field'],
    'reserved' => ['related', 'slug', 'used by content itself'],
    'moved' => ['hero.title', 'rows.title', 'stays where the field is'],
]);

it('renames from the command, with a dry run first', function () {
    artisan('sunrice:rename-field', ['definition' => 'page', 'old' => 'hero.title', 'new' => 'heading', '--dry-run' => true])
        ->expectsOutputToContain('Dry run')->assertSuccessful();
    expect(EntryTranslation::query()->find($this->home->id)->data['hero'])->toHaveKey('title');

    artisan('sunrice:rename-field', ['definition' => 'blueprint:page', 'old' => 'hero.title', 'new' => 'heading'])->assertSuccessful();
    expect(EntryTranslation::query()->find($this->home->id)->data['hero'])->toHaveKey('heading');

    artisan('sunrice:rename-field', ['definition' => 'page', 'old' => 'hero.heading', 'new' => 'Bad'])->assertFailed();
    artisan('sunrice:rename-field', ['definition' => 'nope', 'old' => 'a', 'new' => 'b'])->assertFailed();
});

it('refuses a handle a blueprint including the fieldset already has where it is included', function () {
    Fieldset::query()->create(['handle' => 'meta', 'title' => 'Meta', 'fields' => [['handle' => 'title', 'type' => 'text']]]);
    Blueprint::query()->create(['handle' => 'article', 'title' => 'Article', 'fields' => [
        ['handle' => 'meta', 'type' => 'fieldset', 'config' => ['fieldset' => 'meta']],
        ['handle' => 'name', 'type' => 'text'],
    ]]);
    $meta = Fieldset::query()->where('handle', 'meta')->firstOrFail();

    expect(fn () => app(RenameFieldHandle::class)->handle($meta, 'title', 'name'))
        ->toThrow(ValidationException::class, 'The blueprint "article" includes the fieldset "meta" and already has a field "name".');
    expect($meta->fresh()->fields[0]['handle'])->toBe('title');
    artisan('sunrice:rename-field', ['definition' => 'fieldset:meta', 'old' => 'title', 'new' => 'name'])
        ->expectsOutputToContain('"article"')->assertFailed();
});

it('finds the collision through nested includes, at the level they expand into', function () {
    Fieldset::query()->create(['handle' => 'meta', 'title' => 'Meta', 'fields' => [['handle' => 'title', 'type' => 'text']]]);
    Fieldset::query()->create(['handle' => 'wrap', 'title' => 'Wrap', 'fields' => [
        ['handle' => 'box', 'type' => 'group', 'fields' => [['handle' => 'meta', 'type' => 'fieldset', 'config' => ['fieldset' => 'meta']]]],
    ]]);
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [
        ['handle' => 'name', 'type' => 'text'],
        ['handle' => 'wrap', 'type' => 'fieldset', 'config' => ['fieldset' => 'wrap']],
    ]]);
    $meta = Fieldset::query()->where('handle', 'meta')->firstOrFail();

    // "post" has "name" at the top, where "meta" doesn't land: fine so far.
    expect(app(RenameFieldHandle::class)->handle($meta, 'title', 'name', dryRun: true))->toBeArray();

    // "wrap" gets a "name" next to where "meta" lands: refused, naming a blueprint that has it there.
    $wrap = Fieldset::query()->where('handle', 'wrap')->firstOrFail();
    $wrap->fields = [['handle' => 'box', 'type' => 'group', 'fields' => [
        ['handle' => 'meta', 'type' => 'fieldset', 'config' => ['fieldset' => 'meta']],
        ['handle' => 'name', 'type' => 'text'],
    ]]];
    $wrap->save();
    app(Fieldsets::class)->flush();

    expect(fn () => app(RenameFieldHandle::class)->handle($meta, 'title', 'name'))
        ->toThrow(ValidationException::class, 'The blueprint "post" includes the fieldset "meta" and already has a field "name" in "box".');
});

it('ignores fieldsets used as blocks and includes at other levels', function () {
    Fieldset::query()->create(['handle' => 'meta', 'title' => 'Meta', 'fields' => [['handle' => 'title', 'type' => 'text']]]);
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [
        ['handle' => 'name', 'type' => 'text'],
        ['handle' => 'extra', 'type' => 'group', 'fields' => [['handle' => 'meta', 'type' => 'fieldset', 'config' => ['fieldset' => 'meta']]]],
        ['handle' => 'blocks', 'type' => 'flexible', 'config' => ['blocks' => ['meta']]],
    ]]);

    app(RenameFieldHandle::class)->handle(Fieldset::query()->where('handle', 'meta')->firstOrFail(), 'title', 'name');
    expect(Fieldset::query()->where('handle', 'meta')->firstOrFail()->fields[0]['handle'])->toBe('name');
});
