<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Menus\SaveMenu;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Menus;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Form;
use Sunrice\Cms\Models\Menu;
use Sunrice\Cms\Models\MenuItem;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Settings\SiteSettings;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false, 'sunrice-cms.locales' => ['en', 'id'], 'sunrice-cms.locale_names' => ['id' => 'Bahasa Indonesia']]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Blueprint::query()->create(['handle' => 'contact_form', 'title' => 'Contact form', 'fields' => [
        ['handle' => 'name', 'type' => 'text', 'label' => 'Name', 'instructions' => 'Your full name'],
        ['handle' => 'kind', 'type' => 'select', 'label' => 'Property type', 'config' => ['options' => [['value' => 'house', 'label' => 'House'], ['value' => 'villa.big', 'label' => 'Villa']]]],
        ['handle' => 'about', 'type' => 'group', 'label' => 'About you', 'fields' => [['handle' => 'phone', 'type' => 'text', 'label' => 'Phone']]],
    ]]);
    $this->form = Form::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'contact_form', 'message' => 'Thanks!']);
    $this->agents = Collection::query()->create(['handle' => 'agents', 'title' => 'Agents', 'route' => '/team/{slug}', 'listing_route' => '/team']);
    $this->areas = Taxonomy::query()->create(['handle' => 'areas', 'title' => 'Areas']);
    $this->menu = Menu::query()->create(['handle' => 'main', 'title' => 'Main']);
    app(SaveMenu::class)->handle($this->menu, [['collection_id' => $this->agents->id, 'labels' => ['en' => 'Our team']], ['url' => '/contact', 'labels' => ['en' => 'Contact']]]);
    app(SiteSettings::class)->fill(['description' => 'Premium homes.', 'descriptions' => 'fr: Maisons.'])->save();
    $this->translator = adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.translate');
});

it('lists what visitors read outside entries, for translators only', function () {
    actingAs(adminUser(Panel::ACCESS, 'cms.entries.view'));
    get('/admin/cms/translations')->assertForbidden();
    expect(collect(app(Panel::class)->navigationFor(auth()->user()))->pluck('items')->flatten(1)->pluck('key'))->not->toContain('cms-translations');

    actingAs($this->translator);
    expect(collect(app(Panel::class)->navigationFor($this->translator))->pluck('items')->flatten(1)->pluck('key'))->toContain('cms-translations');
    get('/admin/cms/translations')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Cms/Translations/Edit')
        ->where('locale', 'id')
        ->where('locales', [['code' => 'id', 'name' => 'Bahasa Indonesia']])
        ->where('sections.0.texts.0', ['key' => 'site|description', 'label' => 'Description', 'source' => 'Premium homes.', 'value' => '', 'multiline' => true])
        ->where('sections.1.texts', fn ($texts) => collect($texts)->pluck('key')->all() === ["collection|{$this->agents->id}|title", "taxonomy|{$this->areas->id}|title"])
        ->where('sections.2.title', 'Form: Contact')
        ->where('sections.2.texts', function ($texts) {
            $keys = collect($texts)->pluck('key')->all();
            $id = Blueprint::query()->where('handle', 'contact_form')->value('id');

            return $keys === ["form|{$this->form->id}|message", "field|{$id}|name|label", "field|{$id}|name|instructions", "field|{$id}|kind|label",
                "field|{$id}|kind|option|house", "field|{$id}|kind|option|villa.big", "field|{$id}|about|label", "field|{$id}|about.phone|label"];
        })
        ->where('sections.3.title', 'Menu: Main')
        ->where('sections.3.texts.0.source', 'Our team'));
});

it('saves only the translations, and the site shows them', function () {
    actingAs($this->translator);
    $blueprint = Blueprint::query()->where('handle', 'contact_form')->firstOrFail();
    $item = MenuItem::query()->where('collection_id', $this->agents->id)->firstOrFail();

    put('/admin/cms/translations', ['locale' => 'id', 'texts' => [
        'site|description' => "Rumah\npremium.",
        "collection|{$this->agents->id}|title" => 'Tim',
        "taxonomy|{$this->areas->id}|title" => 'Kawasan',
        "form|{$this->form->id}|message" => 'Terima kasih!',
        "field|{$blueprint->id}|name|label" => 'Nama',
        "field|{$blueprint->id}|name|instructions" => 'Nama lengkap Anda',
        "field|{$blueprint->id}|kind|option|villa.big" => 'Vila',
        "field|{$blueprint->id}|about.phone|label" => 'Telepon',
        "menu|{$item->id}|label" => 'Tim kami',
        'nonsense|1|x' => 'ignored',
    ]])->assertSessionHasNoErrors()->assertSessionHas('success', 'Translations saved.');

    $schema = $blueprint->fresh()->schema();
    expect((new SiteSettings)->descriptionIn('id'))->toBe('Rumah premium.')
        ->and((new SiteSettings)->descriptionIn('fr'))->toBe('Maisons.')
        ->and((new SiteSettings)->description)->toBe('Premium homes.')
        ->and($this->agents->fresh()->titleIn('id'))->toBe('Tim')
        ->and($this->agents->fresh()->title)->toBe('Agents')
        ->and($this->areas->fresh()->titleIn('id'))->toBe('Kawasan')
        ->and($this->form->fresh()->successMessage('id'))->toBe('Terima kasih!')
        ->and($this->form->fresh()->message)->toBe('Thanks!')
        ->and($schema->field('name')?->labelIn('id'))->toBe('Nama')
        ->and($schema->field('name')?->label)->toBe('Name')
        ->and($schema->field('name')?->instructionsIn('id'))->toBe('Nama lengkap Anda')
        ->and($schema->field('kind')?->optionLabel('villa.big', 'id'))->toBe('Vila')
        ->and($schema->field('kind')?->optionLabel('house', 'id'))->toBe('House')
        ->and($schema->field('about.phone')?->labelIn('id'))->toBe('Telepon')
        ->and(array_column(app(Menus::class)->get('main', 'id'), 'label'))->toBe(['Tim kami', 'Contact'])
        ->and(Activity::query()->where('action', 'translated')->exists())->toBeTrue();

    // Emptied: forgotten, the main language's shows again.
    put('/admin/cms/translations', ['locale' => 'id', 'texts' => ["field|{$blueprint->id}|name|label" => '', "collection|{$this->agents->id}|title" => '', "menu|{$item->id}|label" => '']])->assertSessionHasNoErrors();
    expect($blueprint->fresh()->schema()->field('name')?->labelIn('id'))->toBe('Name')
        ->and($blueprint->fresh()->schema()->field('name')?->instructionsIn('id'))->toBe('Nama lengkap Anda')
        ->and($this->agents->fresh()->translations)->toBeNull()
        ->and(MenuItem::query()->find($item->id)->labels)->toBe(['en' => 'Our team']);
    put('/admin/cms/translations', ['locale' => 'id', 'texts' => ["field|{$blueprint->id}|name|label" => '']])->assertSessionHas('success', 'Nothing changed.');
});

it('refuses the main language, unknown languages and long texts', function () {
    actingAs($this->translator);
    put('/admin/cms/translations', ['locale' => 'en', 'texts' => ["collection|{$this->agents->id}|title" => 'Team']])->assertSessionHasErrors('locale');
    put('/admin/cms/translations', ['locale' => 'xx', 'texts' => []])->assertSessionHasErrors('locale');
    put('/admin/cms/translations', ['locale' => 'id', 'texts' => ["collection|{$this->agents->id}|title" => str_repeat('a', 3001)]])->assertSessionHasErrors("texts.collection|{$this->agents->id}|title");
    expect($this->agents->fresh()->translations)->toBeNull();

    // A site in one language has nothing to translate.
    config(['sunrice-cms.locales' => ['en']]);
    get('/admin/cms/translations')->assertNotFound();
});
