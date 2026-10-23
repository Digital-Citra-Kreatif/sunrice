<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Blueprints\SaveBlueprint;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\DiscardChanges;
use Sunrice\Cms\Actions\Entries\MachineTranslateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\RestoreRevision;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Fieldsets\SaveFieldset;
use Sunrice\Cms\Admin\RevisionList;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Fields\HydrationContext;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Fieldset;
use Sunrice\Cms\Models\Revision;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 * A field switched from translated to shared: what other languages stored
 * for it stays stored, out of sight, through every save, and comes back
 * when it's translated again.
 */

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id'], 'inertia.testing.ensure_pages_exist' => false]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.export'));
});

/** The dish blueprint, its tagline translated or shared; the code is always shared. */
function saveDishBlueprint(bool $taglineTranslated): void
{
    app(SaveBlueprint::class)->handle(['title' => 'Dish', 'fields' => [
        ['handle' => 'intro', 'type' => 'textarea'],
        ['handle' => 'tagline', 'type' => 'text', 'translatable' => $taglineTranslated ? null : false],
        ['handle' => 'code', 'type' => 'text', 'translatable' => false],
    ]], Blueprint::query()->where('handle', 'dish')->firstOrFail());
}

function idDish(EntryTranslation $translation): EntryTranslation
{
    return EntryTranslation::query()->where('entry_id', $translation->entry_id)->where('locale', 'id')->firstOrFail();
}

it('keeps another language\'s value of a field made shared through every save, out of sight, and brings it back', function () {
    Blueprint::query()->create(['handle' => 'dish', 'title' => 'Dish', 'fields' => []]);
    saveDishBlueprint(true);
    $dishes = Collection::query()->create(['handle' => 'dishes', 'title' => 'Dishes', 'blueprint' => 'dish', 'route' => '/menu/{slug}']);
    $main = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($dishes, ['title' => 'Coffee', 'data' => ['intro' => 'Strong', 'tagline' => 'Brewed daily', 'code' => 'C1']]));
    $id = app(CreateTranslation::class)->handle($main->entry, 'id');
    app(SaveDraft::class)->handle($id, ['title' => 'Kopi', 'data' => ['intro' => 'Kuat', 'tagline' => 'Diseduh setiap hari']]);
    app(PublishEntry::class)->handle(idDish($main));
    // A change to it not published yet when the field is made shared.
    app(SaveDraft::class)->handle(idDish($main), ['data' => ['tagline' => 'Diseduh baru']]);

    saveDishBlueprint(false);

    // Saved with other input for the shared fields: ignored, and what it had stays stored.
    app(SaveDraft::class)->handle(idDish($main), ['data' => ['intro' => 'Kuat sekali', 'tagline' => 'Dibobol', 'code' => 'X9']]);
    $id = idDish($main);
    expect($id->draft_data)->toBe(['intro' => 'Kuat sekali', 'tagline' => 'Diseduh baru'])
        ->and($id->data)->toBe(['intro' => 'Kuat', 'tagline' => 'Diseduh setiap hari']);

    // Out of sight: values, the editor, its "Changed" markers and the comparison with the live version.
    expect($id->values(new HydrationContext('id'))['tagline'])->toBe('Brewed daily')
        ->and($id->completeData(draft: true)['tagline'])->toBe('Brewed daily')
        ->and(RevisionList::changedFromLive($id))->toBe(['intro'])
        ->and(array_column(RevisionList::compareLive($id), 'label'))->toBe(['Intro']);
    get(entryEditUrl($main->entry_id, 'id'))->assertInertia(fn (Assert $page) => $page
        ->where('values.data.tagline', 'Brewed daily')->where('sharedValues.tagline', 'Brewed daily')
        ->where('entry.live.changed', ['intro']));
    get('/id/menu/coffee')->assertOk()->assertSee('Brewed daily')->assertDontSee('Diseduh');

    // Published: the live version keeps it too; search text and the revision summaries don't show it.
    app(PublishEntry::class)->handle($id);
    $id = idDish($main);
    expect($id->data)->toBe(['intro' => 'Kuat sekali', 'tagline' => 'Diseduh baru'])
        ->and($id->search_text)->not->toContain('Diseduh')->toContain('Brewed daily')
        ->and(RevisionList::summaries($id)[0]['changed'])->toBe(['Intro']);
    get('/id/menu/coffee')->assertOk()->assertSee('Kuat sekali')->assertSee('Brewed daily')->assertDontSee('Diseduh');
    expect(get('/admin/cms/collections/dishes/entries/export?locale=id')->streamedContent())->toContain('Brewed daily')->not->toContain('Diseduh');

    // Discarded changes, a restored revision (published before it was shared) and a machine translation keep it.
    app(SaveDraft::class)->handle($id, ['data' => ['intro' => 'Sebentar']]);
    app(DiscardChanges::class)->handle(idDish($main));
    expect(idDish($main)->draft_data['tagline'])->toBe('Diseduh baru');
    $first = Revision::query()->where('entry_translation_id', $id->id)->orderBy('id')->firstOrFail();
    expect($first->data['tagline'])->toBe('Diseduh setiap hari');
    app(RestoreRevision::class)->handle($first);
    expect(idDish($main)->draft_data)->toBe(['intro' => 'Kuat', 'tagline' => 'Diseduh baru']);

    config(['sunrice-cms.translation.driver' => 'gemini', 'sunrice-cms.translation.gemini_key' => 'secret']);
    Sleep::fake();
    Http::fake(['generativelanguage.googleapis.com/*' => function (Request $request) {
        $texts = json_decode((string) data_get($request->data(), 'contents.0.parts.0.text'), true);

        return Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(array_map(fn (string $text): string => 'ID: '.$text, $texts), JSON_FORCE_OBJECT)]]]]]]);
    }]);
    // Its intro as the main language's: untranslated, so it's sent.
    app(SaveDraft::class)->handle(idDish($main), ['data' => ['intro' => 'Strong']]);
    app(MachineTranslateEntry::class)->handle(idDish($main));
    Http::assertSent(fn (Request $request) => str_contains((string) json_encode($request->data()), 'Strong'));
    Http::assertNotSent(fn (Request $request) => str_contains((string) json_encode($request->data()), 'Brewed') || str_contains((string) json_encode($request->data()), 'Diseduh'));
    expect(idDish($main)->draft_data)->toBe(['intro' => 'ID: Strong', 'tagline' => 'Diseduh baru']);
    app(PublishEntry::class)->handle(idDish($main));

    // Translated again: its own value shows again, in the draft and live.
    saveDishBlueprint(true);
    $id = idDish($main);
    expect($id->completeData(draft: true)['tagline'])->toBe('Diseduh baru')
        ->and($id->completeData()['tagline'])->toBe('Diseduh baru')
        // The always-shared code was never stored for it.
        ->and($id->draft_data)->not->toHaveKey('code')
        ->and($id->data)->not->toHaveKey('code');
    get(entryEditUrl($main->entry_id, 'id'))->assertInertia(fn (Assert $page) => $page->where('values.data.tagline', 'Diseduh baru'));
    get('/id/menu/'.$id->slug)->assertOk()->assertSee('Diseduh baru')->assertDontSee('Brewed daily');
});

it('keeps another language\'s values of shared fields inside groups, rows and blocks, by id', function () {
    $quote = fn (bool $translated) => app(SaveFieldset::class)->handle(['title' => 'Quote', 'fields' => [
        ['handle' => 'text', 'type' => 'text'],
        ['handle' => 'caption', 'type' => 'text', 'translatable' => $translated ? null : false],
    ]], Fieldset::query()->where('handle', 'quote')->first() ?? new Fieldset(['handle' => 'quote']));
    $menu = fn (bool $translated) => app(SaveBlueprint::class)->handle(['title' => 'Menu', 'fields' => [
        ['handle' => 'hero', 'type' => 'group', 'fields' => [['handle' => 'title', 'type' => 'text'], ['handle' => 'sub', 'type' => 'text', 'translatable' => $translated ? null : false]]],
        ['handle' => 'dishes', 'type' => 'repeater', 'fields' => [['handle' => 'name', 'type' => 'text'], ['handle' => 'note', 'type' => 'text', 'translatable' => $translated ? null : false]]],
        ['handle' => 'blocks', 'type' => 'flexible', 'config' => ['blocks' => ['quote']]],
    ]], Blueprint::query()->where('handle', 'menu')->firstOrFail());
    Fieldset::query()->create(['handle' => 'quote', 'title' => 'Quote', 'fields' => []]);
    Blueprint::query()->create(['handle' => 'menu', 'title' => 'Menu', 'fields' => []]);
    $quote(true);
    $menu(true);
    $menus = Collection::query()->create(['handle' => 'menus', 'title' => 'Menus', 'blueprint' => 'menu']);
    $main = app(CreateEntry::class)->handle($menus, ['title' => 'Lunch', 'data' => [
        'hero' => ['title' => 'Lunch', 'sub' => 'Daily'],
        'dishes' => [['_id' => 'r1', 'name' => 'Soup', 'note' => 'Hot']],
        'blocks' => [['id' => 'b1', 'type' => 'quote', 'values' => ['text' => 'Great', 'caption' => 'A guest']]],
    ]]);
    app(PublishEntry::class)->handle($main);
    $id = app(CreateTranslation::class)->handle($main->entry, 'id');
    app(SaveDraft::class)->handle($id, ['data' => [
        'hero' => ['title' => 'Makan siang', 'sub' => 'Harian'],
        'dishes' => [['_id' => 'r1', 'name' => 'Sup', 'note' => 'Panas']],
        'blocks' => [['id' => 'b1', 'type' => 'quote', 'values' => ['text' => 'Enak', 'caption' => 'Seorang tamu']]],
    ]]);
    // Its own values, as stored.
    $own = fn (array $data): array => [$data['hero']['sub'] ?? null, $data['dishes'][0]['note'] ?? null, $data['blocks'][0]['values']['caption'] ?? null];
    expect($own(EntryTranslation::query()->findOrFail($id->id)->draft_data))->toBe(['Harian', 'Panas', 'Seorang tamu']);

    $quote(false);
    $menu(false);
    // Saved and published with other input for them: they stay as stored, and show the main language's.
    app(SaveDraft::class)->handle(EntryTranslation::query()->findOrFail($id->id), ['data' => [
        'hero' => ['title' => 'Makan siang', 'sub' => 'X'],
        'dishes' => [['_id' => 'r1', 'name' => 'Sup', 'note' => 'X']],
        'blocks' => [['id' => 'b1', 'type' => 'quote', 'values' => ['text' => 'Enak', 'caption' => 'X']]],
    ]]);
    app(PublishEntry::class)->handle(EntryTranslation::query()->findOrFail($id->id));
    $id = EntryTranslation::query()->findOrFail($id->id);
    expect($own($id->draft_data))->toBe(['Harian', 'Panas', 'Seorang tamu'])
        ->and($own($id->data ?? []))->toBe(['Harian', 'Panas', 'Seorang tamu'])
        ->and($own($id->completeData()))->toBe(['Daily', 'Hot', 'A guest'])
        ->and($own($id->completeData(draft: true)))->toBe(['Daily', 'Hot', 'A guest']);

    $quote(true);
    $menu(true);
    $id = EntryTranslation::query()->findOrFail($id->id);
    expect($own($id->completeData()))->toBe(['Harian', 'Panas', 'Seorang tamu'])
        ->and($own($id->completeData(draft: true)))->toBe(['Harian', 'Panas', 'Seorang tamu']);
});
