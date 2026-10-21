<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Globals\SaveGlobals;
use Sunrice\Cms\Actions\Menus\SaveMenu;
use Sunrice\Cms\Actions\Settings\SwitchMainLanguage;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Fields\HydrationContext;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\GlobalSet;
use Sunrice\Cms\Models\Menu;
use Sunrice\Cms\Models\MenuItem;
use Sunrice\Cms\Models\Redirect;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\TermTranslation;
use Sunrice\Cms\Support\Locales;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    actingAs(adminUser());
    Blueprint::query()->create(['handle' => 'dish', 'title' => 'Dish', 'fields' => [
        ['handle' => 'intro', 'type' => 'textarea'],
        ['handle' => 'price', 'type' => 'number'],
        ['handle' => 'sides', 'type' => 'repeater', 'fields' => [['handle' => 'name', 'type' => 'text'], ['handle' => 'extra', 'type' => 'number']]],
    ]]);
    $this->dishes = Collection::query()->create(['handle' => 'dishes', 'title' => 'Dishes', 'blueprint' => 'dish', 'route' => '/menu/{slug}']);
    $this->coffee = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->dishes, ['title' => 'Coffee', 'data' => [
        'intro' => 'Strong', 'price' => 30, 'sides' => [['_id' => 's1', 'name' => 'Milk', 'extra' => 2]],
    ]]));
    $kopi = app(CreateTranslation::class)->handle($this->coffee->entry, 'id');
    app(SaveDraft::class)->handle($kopi, ['title' => 'Kopi', 'slug' => 'kopi', 'data' => ['intro' => 'Kental', 'sides' => [['_id' => 's1', 'name' => 'Susu']]]]);
    $this->kopi = app(PublishEntry::class)->handle($kopi->fresh() ?? $kopi);
});

it('reports what a switch would change, and refuses one that would leave content behind', function () {
    $tea = app(CreateEntry::class)->handle($this->dishes, ['title' => 'Tea', 'data' => ['price' => 10]]);

    expect(app(SwitchMainLanguage::class)->report('id'))->toMatchArray(['entries' => 2, 'missing_entries' => 1, 'terms' => 0, 'missing_terms' => 0])
        ->and(fn () => app(SwitchMainLanguage::class)->handle('id'))->toThrow(ValidationException::class, '--copy-missing')
        ->and(fn () => app(SwitchMainLanguage::class)->handle('en'))->toThrow(ValidationException::class, 'already')
        ->and(fn () => app(SwitchMainLanguage::class)->handle('fr'))->toThrow(ValidationException::class);

    artisan('sunrice:switch-main-language', ['locale' => 'id', '--dry-run' => true, '--copy-missing' => true])->assertSuccessful()->expectsOutputToContain('Dry run');
    artisan('sunrice:switch-main-language', ['locale' => 'id', '--force' => true])->assertFailed();
    expect(Locales::default())->toBe('en')->and($tea->fresh()?->locale)->toBe('en');
});

it('moves the shared values, labels and addresses to the new main language', function () {
    $tea = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->dishes, ['title' => 'Tea', 'data' => ['price' => 10]]));
    $tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags', 'route' => '/tags/{slug}']);
    $news = app(SaveTerm::class)->handle($tags, ['title' => 'News']);
    Blueprint::query()->create(['handle' => 'contact', 'title' => 'Contact', 'fields' => [['handle' => 'address', 'type' => 'text'], ['handle' => 'open', 'type' => 'toggle']]]);
    $contact = GlobalSet::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'contact']);
    app(SaveGlobals::class)->handle($contact, ['address' => 'Main St', 'open' => true]);
    app(SaveGlobals::class)->handle($contact, ['address' => 'Jalan Utama'], 'id');
    $menu = Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    app(SaveMenu::class)->handle($menu, [['entry_id' => $this->coffee->entry_id, 'labels' => ['en' => 'Our coffee']]]);
    Redirect::query()->create(['from_path' => '/old-coffee', 'to_url' => '/menu/coffee']);
    Redirect::query()->create(['from_path' => '/id/kopi-lama', 'to_url' => '/id/menu/kopi']);

    artisan('sunrice:switch-main-language', ['locale' => 'id', '--copy-missing' => true, '--force' => true])->assertSuccessful()
        ->expectsOutputToContain('is the main language now');

    expect(Locales::all())->toBe(['id', 'en']);

    // Entries: the new main language holds the shared values, live and in the draft; the old one only its text.
    $kopi = EntryTranslation::query()->findOrFail($this->kopi->id);
    $coffee = EntryTranslation::query()->findOrFail($this->coffee->id);
    expect($kopi->data)->toMatchArray(['intro' => 'Kental', 'price' => 30])
        ->and($kopi->data['sides'][0])->toMatchArray(['name' => 'Susu', 'extra' => 2])
        ->and($kopi->draft_data['price'])->toBe(30)
        ->and($kopi->isMain())->toBeTrue()
        ->and($coffee->data)->not->toHaveKey('price')
        ->and($coffee->data['sides'][0])->not->toHaveKey('extra')
        ->and($coffee->draft_data)->not->toHaveKey('price')
        ->and($coffee->values(new HydrationContext('en'))['price'])->toBe(30)
        ->and($coffee->search_text)->toContain('Strong');

    // The entry only in English got an Indonesian copy, as the main one.
    $teh = EntryTranslation::query()->where('entry_id', $tea->entry_id)->where('locale', 'id')->firstOrFail();
    expect($teh->only(['title', 'slug', 'status']))->toBe(['title' => 'Tea', 'slug' => 'tea', 'status' => 'published'])
        ->and($teh->data['price'])->toBe(10);

    // Terms and globals keep what each language showed.
    expect(TermTranslation::query()->where('term_id', $news->id)->where('locale', 'id')->value('title'))->toBe('News');
    $contact->unsetRelation('values');
    expect($contact->data('id'))->toBe(['address' => 'Jalan Utama', 'open' => true])
        ->and($contact->data('en'))->toBe(['address' => 'Main St', 'open' => true])
        ->and($contact->values()->where('locale', 'en')->firstOrFail()->data)->toBe(['address' => 'Main St']);

    // A menu label the new main language left empty takes the old one's.
    expect(MenuItem::query()->firstOrFail()->labels)->toBe(['en' => 'Our coffee', 'id' => 'Our coffee']);

    // Addresses: the new main language's are plain, the old one's are prefixed, and redirects follow.
    expect(Redirect::query()->pluck('to_url', 'from_path')->all())->toMatchArray([
        '/en/old-coffee' => '/en/menu/coffee',
        '/kopi-lama' => '/menu/kopi',
        '/menu/coffee' => '/en/menu/coffee',
    ])
        // Where the new main language's page has the same address, it's served there.
        ->and(Redirect::query()->whereIn('from_path', ['/menu/tea', '/tags/news'])->exists())->toBeFalse();

    get('/menu/kopi')->assertOk()->assertSee('Kopi');
    get('/en/menu/coffee')->assertOk()->assertSee('Coffee');
    get('/menu/coffee')->assertRedirect('/en/menu/coffee');
    get('/id/menu/kopi')->assertRedirect('/menu/kopi');
});

it('rethrows what stopped a switch even when putting the languages back fails too', function () {
    $failed = false;
    EntryTranslation::saving(function () use (&$failed): void {
        $failed = true;

        throw new RuntimeException('The switch broke.');
    });
    DB::listen(function (QueryExecuted $query) use (&$failed): void {
        if ($failed && str_contains($query->sql, 'sunrice_settings') && ! str_starts_with(strtolower($query->sql), 'select')) {
            throw new LogicException('The settings could not be saved.');
        }
    });

    expect(fn () => app(SwitchMainLanguage::class)->handle('id'))->toThrow(RuntimeException::class, 'The switch broke.');
});

it('swaps titles and thank-you messages with the main language', function () {
    $this->dishes->translate('title', 'id', 'Hidangan')->save();
    $tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    Blueprint::query()->create(['handle' => 'contact', 'title' => 'Contact', 'fields' => [['handle' => 'name', 'type' => 'text']]]);
    $form = Sunrice\Cms\Models\Form::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'contact', 'message' => 'Thanks!']);
    $form->translate('message', 'id', 'Terima kasih!')->save();
    app(Sunrice\Cms\Settings\SiteSettings::class)->fill(['description' => 'Good food.', 'descriptions' => 'id: Makanan enak.'])->save();

    app(SwitchMainLanguage::class)->handle('id');
    app()->forgetInstance(Sunrice\Cms\Settings\SiteSettings::class);
    expect(array_intersect_key(app(Sunrice\Cms\Settings\SiteSettings::class)->toArray(), ['description' => 1, 'descriptions' => 1]))->toBe(['description' => 'Makanan enak.', 'descriptions' => 'en: Good food.']);

    expect($this->dishes->fresh()?->only(['title', 'translations']))->toBe(['title' => 'Hidangan', 'translations' => ['en' => ['title' => 'Dishes']]])
        ->and($this->dishes->fresh()?->titleIn('en'))->toBe('Dishes')
        // Not translated: as it was, in every language.
        ->and($tags->fresh()?->only(['title', 'translations']))->toBe(['title' => 'Tags', 'translations' => null])
        ->and($form->fresh()?->successMessage('id'))->toBe('Terima kasih!')
        ->and($form->fresh()?->successMessage('en'))->toBe('Thanks!');
});
