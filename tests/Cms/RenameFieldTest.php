<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Sunrice\Cms\Actions\Blueprints\RenameFieldHandle;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Globals\SaveGlobals;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Actions\Terms\SaveTermContent;
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
    expectStored($home->data['hero'], ['heading' => 'Welcome home', 'note' => 'n']);
    expectStored($home->draft_data['hero'], ['heading' => 'Welcome home', 'note' => 'n']);
    expectStored(Revision::query()->where('entry_translation_id', $home->id)->first()->data['hero'], ['heading' => 'Welcome home', 'note' => 'n']);
    expect($home->version)->toBe($version + 1)
        ->and($this->homeId->fresh()->draft_data['hero']['heading'])->toBe('Selamat datang')
        ->and($home->values()['hero']['heading'])->toBe('Welcome home');
});

it('renames a field in a repeater and a top-level one, keeping references and search text', function () {
    app(RenameFieldHandle::class)->handle($this->page, 'rows.caption', 'text');
    app(RenameFieldHandle::class)->handle($this->page, 'related', 'links');

    $home = $this->home->fresh();
    expect(array_map(fn (array $row) => $row['text'] ?? null, $home->data['rows']))->toBe(['One', 'Two'])
        ->and($home->data['rows'][0])->not->toHaveKey('caption')
        ->and($home->search_text)->toContain('Welcome home')->toContain('One')
        ->and(ContentReference::query()->where('source_id', $home->entry_id)->where('locale', 'en')->orderBy('field')->pluck('field')->all())->toBe(['links', 'sections.0.values.target']);
    $keys = array_keys($home->data); // in its place, where the database keeps the order (see expectStored())
    DB::getDriverName() === 'mysql' ? expect($keys)->toEqualCanonicalizing(['hero', 'rows', 'sections', 'links']) : expect($keys)->toBe(['hero', 'rows', 'sections', 'links']);
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

/**
 * A stored JSON object is what's expected, its keys in the order written,
 * except on MySQL, whose JSON type keeps keys in an order of its own
 * (shortest first): there only the keys and values count.
 */
function expectStored(array $actual, array $expected): void
{
    DB::getDriverName() === 'mysql' ? expect($actual)->toEqual($expected) : expect($actual)->toBe($expected);
}
