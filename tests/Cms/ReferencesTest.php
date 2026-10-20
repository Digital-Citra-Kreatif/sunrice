<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\BulkEntries;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\ForceDeleteEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Entries\TrashEntry;
use Sunrice\Cms\Actions\Globals\SaveGlobals;
use Sunrice\Cms\Actions\Terms\DeleteTerm;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Actions\Terms\SaveTermContent;
use Sunrice\Cms\Actions\Terms\TrashTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\ContentReference;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\Fieldset;
use Sunrice\Cms\Models\GlobalSet;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\Term;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser('sunrice.access', 'cms.entries.view', 'cms.entries.update', 'cms.entries.delete', 'cms.terms.manage'));
    Fieldset::query()->create(['handle' => 'links', 'title' => 'Links', 'fields' => [['handle' => 'target', 'type' => 'entries', 'config' => ['collections' => ['pages']]]]]);
    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => [
        ['handle' => 'related', 'type' => 'entries', 'config' => ['collections' => ['pages'], 'multiple' => true]],
        ['handle' => 'topics', 'type' => 'terms', 'config' => ['taxonomy' => 'topics', 'multiple' => true]],
        ['handle' => 'sections', 'type' => 'flexible', 'config' => ['blocks' => ['links']]],
    ]]);
    Blueprint::query()->create(['handle' => 'topic', 'title' => 'Topic', 'fields' => [['handle' => 'feature', 'type' => 'entries', 'config' => ['collections' => ['pages']]]]]);
    $this->pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page']);
    $this->topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'blueprint' => 'topic']);
    $this->pages->taxonomies()->attach($this->topics);
    $this->news = app(SaveTerm::class)->handle($this->topics, ['title' => 'News']);
    $this->about = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->pages, ['title' => 'About']))->entry;
});

function refPage(Collection $collection, string $title, array $data = []): Entry
{
    return app(CreateEntry::class)->handle($collection, ['title' => $title, 'data' => $data])->entry;
}

it('records which entries and terms an entry points at, nested ones too, on draft save and publish', function () {
    $home = refPage($this->pages, 'Home');
    $translation = $home->translations->first();
    DB::enableQueryLog();
    app(SaveDraft::class)->handle($translation, ['data' => [
        'related' => [$this->about->id], 'topics' => [$this->news->id],
        'sections' => [['type' => 'links', 'values' => ['target' => $this->about->id]]],
    ]]);
    $writes = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'sunrice_references') && ! str_starts_with(strtolower($q['query']), 'select'));
    expect($writes)->toHaveCount(2); // one delete, one insert

    $rows = ContentReference::query()->orderBy('field')->get(['source_type', 'source_id', 'locale', 'target_type', 'target_id', 'field'])->toArray();
    expect($rows)->toBe([
        ['source_type' => 'entry', 'source_id' => $home->id, 'locale' => 'en', 'target_type' => 'entry', 'target_id' => $this->about->id, 'field' => 'related'],
        ['source_type' => 'entry', 'source_id' => $home->id, 'locale' => 'en', 'target_type' => 'entry', 'target_id' => $this->about->id, 'field' => 'sections.0.values.target'],
        ['source_type' => 'entry', 'source_id' => $home->id, 'locale' => 'en', 'target_type' => 'term', 'target_id' => $this->news->id, 'field' => 'topics'],
    ]);
    expect($this->about->referencedBy())->toHaveCount(2)
        ->and($this->news->referencedBy()->pluck('source_id')->all())->toBe([$home->id]);

    // Published, then the draft drops the link: the live version still points at it.
    app(PublishEntry::class)->handle($translation->fresh());
    app(SaveDraft::class)->handle($translation->fresh(), ['data' => ['related' => [], 'sections' => [], 'topics' => []]]);
    expect($this->about->referencedBy())->toHaveCount(2);
    app(PublishEntry::class)->handle($translation->fresh());
    expect($this->about->referencedBy())->toHaveCount(0)->and($this->news->referencedBy())->toHaveCount(0);
});

it('records what term pages and global sets point at', function () {
    app(SaveTermContent::class)->handle($this->news, ['data' => ['feature' => $this->about->id]]);
    Blueprint::query()->create(['handle' => 'footer', 'title' => 'Footer', 'fields' => [['handle' => 'legal', 'type' => 'entries', 'config' => ['collections' => ['pages']]]]]);
    $footer = GlobalSet::query()->create(['handle' => 'footer', 'title' => 'Footer', 'blueprint' => 'footer']);
    app(SaveGlobals::class)->handle($footer, ['legal' => $this->about->id]);

    expect($this->about->referencedBy()->map(fn ($r) => [$r->source_type, $r->field])->all())->toBe([['term', 'feature'], ['global', 'legal']]);

    // Saved again (a version update, not a model save): replaced.
    app(SaveGlobals::class)->handle($footer, ['legal' => null]);
    expect($this->about->referencedBy())->toHaveCount(1);
});

it('lists where an entry is used in its editor', function () {
    refPage($this->pages, 'Home', ['related' => [$this->about->id]]);

    get(route('sunrice.admin.cms.entries.edit', $this->about->id))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('usedBy', 1)
        ->where('usedBy.0.label', 'Pages: Home (EN)')
        ->where('usedBy.0.field', 'related'));
});

it('lists where a term is used on its page and in the list', function () {
    refPage($this->pages, 'Home', ['topics' => [$this->news->id]]);

    get(route('sunrice.admin.cms.terms.edit', $this->news->id))->assertOk()->assertInertia(fn (Assert $page) => $page->where('usedBy.0.label', 'Pages: Home (EN)'));
    get(route('sunrice.admin.cms.terms.index', 'topics'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('terms.0.usedBy', ['Pages: Home (EN)']));
});

it('refuses to delete a referenced entry for good unless forced', function () {
    $home = refPage($this->pages, 'Home', ['related' => [$this->about->id]]);
    app(TrashEntry::class)->handle($this->about);

    expect(fn () => app(ForceDeleteEntry::class)->handle($this->about->fresh()))->toThrow(ValidationException::class, 'Pages: Home (EN)');
    // Bulk deletes skip it.
    expect(app(BulkEntries::class)->handle($this->pages, 'delete', [$this->about->id], auth()->user()))->toBe(0);
    delete(route('sunrice.admin.cms.entries.force-delete', $this->about->id))->assertSessionHasErrors('entry');
    expect(Entry::withTrashed()->find($this->about->id))->not->toBeNull();

    delete(route('sunrice.admin.cms.entries.force-delete', $this->about->id), ['force' => 1])->assertSessionHasNoErrors();
    expect(Entry::withTrashed()->find($this->about->id))->toBeNull();
    // The source still holds the id: the reference stays, for sunrice:orphans.
    expect(ContentReference::query()->where('source_id', $home->id)->count())->toBe(1);
});

it('lets an entry that only points at itself go', function () {
    $translation = $this->about->translations->first();
    app(SaveDraft::class)->handle($translation, ['data' => ['related' => [$this->about->id]]]);
    app(TrashEntry::class)->handle($this->about);
    app(ForceDeleteEntry::class)->handle($this->about->fresh());

    expect(ContentReference::query()->count())->toBe(0);
});

it('refuses to delete a referenced term for good unless forced', function () {
    refPage($this->pages, 'Home', ['topics' => [$this->news->id]]);
    app(TrashTerm::class)->handle($this->news);

    expect(fn () => app(DeleteTerm::class)->handle($this->news->fresh()))->toThrow(ValidationException::class, 'Pages: Home (EN)');
    delete(route('sunrice.admin.cms.terms.force-delete', $this->news->id))->assertSessionHasErrors('term');
    delete(route('sunrice.admin.cms.terms.force-delete', $this->news->id), ['force' => 1])->assertSessionHasNoErrors();
    expect(Term::withTrashed()->find($this->news->id))->toBeNull();
});

it('forgets what a deleted entry or term pointed at', function () {
    app(SaveTermContent::class)->handle($this->news, ['data' => ['feature' => $this->about->id]]);
    $home = refPage($this->pages, 'Home', ['related' => [$this->about->id]]);
    app(TrashEntry::class)->handle($home);
    app(ForceDeleteEntry::class)->handle($home->fresh());
    app(TrashTerm::class)->handle($this->news);
    app(DeleteTerm::class)->handle($this->news->fresh());

    expect($this->about->referencedBy())->toHaveCount(0);
});
