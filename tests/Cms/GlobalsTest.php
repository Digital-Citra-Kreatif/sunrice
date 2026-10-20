<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Globals\SaveGlobals;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Globals;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\GlobalSet;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    actingAs(adminUser());
    Blueprint::query()->create(['handle' => 'contact', 'title' => 'Contact', 'fields' => [
        ['handle' => 'address', 'type' => 'textarea', 'label' => 'Address', 'required' => true],
        ['handle' => 'phone', 'type' => 'text', 'translatable' => false],
        ['handle' => 'open_weekends', 'type' => 'toggle'],
    ]]);
    $this->contact = GlobalSet::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'contact']);
});

it('saves values that are live at once, with defaults until then', function () {
    expect(app(Globals::class)->get('contact'))->toBe(['address' => null, 'phone' => null, 'open_weekends' => false]);

    app(SaveGlobals::class)->handle($this->contact, ['address' => '1 Main St', 'phone' => '+62 21 555']);

    expect(app(Globals::class)->get('contact'))->toMatchArray(['address' => '1 Main St', 'phone' => '+62 21 555'])
        ->and(app(Globals::class)->get('nope'))->toBe([])
        ->and(Activity::query()->latest('id')->first()?->properties)->toMatchArray(['changed' => ['address', 'phone']]);
});

it('validates against the blueprint', function () {
    app(SaveGlobals::class)->handle($this->contact, ['address' => '']);
})->throws(ValidationException::class, 'The Address field is required.');

it('shares untranslated fields from the main language', function () {
    app(SaveGlobals::class)->handle($this->contact, ['address' => '1 Main St', 'phone' => '+62 21 555', 'open_weekends' => true]);
    app(SaveGlobals::class)->handle($this->contact, ['address' => 'Jl. Utama 1', 'phone' => 'ignored'], 'id');

    expect(app(Globals::class)->get('contact', 'id'))->toBe(['address' => 'Jl. Utama 1', 'phone' => '+62 21 555', 'open_weekends' => true])
        ->and(app(Globals::class)->get('contact', 'en')['address'])->toBe('1 Main St');
});

it('refuses a save over a newer one, unless no version is given', function () {
    app(SaveGlobals::class)->handle($this->contact, ['address' => '1 Main St']);
    app(SaveGlobals::class)->handle($this->contact, ['address' => '2 Main St'], 'en', 1);

    expect($this->contact->values()->firstOrFail()->version)->toBe(2)
        ->and(fn () => app(SaveGlobals::class)->handle($this->contact, ['address' => 'Stale'], 'en', 1))->toThrow(ValidationException::class, 'Someone else saved')
        ->and(fn () => app(SaveGlobals::class)->handle($this->contact, ['address' => 'Stale'], 'en', 'x'))->toThrow(ValidationException::class, 'must be a number')
        ->and(app(Globals::class)->get('contact')['address'])->toBe('2 Main St');

    app(SaveGlobals::class)->handle($this->contact, ['address' => '3 Main St']);
    expect(app(Globals::class)->get('contact')['address'])->toBe('3 Main St');
});

it('loads all global sets once per request', function () {
    GlobalSet::query()->create(['handle' => 'social', 'title' => 'Social']);
    app(Globals::class)->flush();
    DB::enableQueryLog();
    app(Globals::class)->get('contact');
    app(Globals::class)->get('social');
    app(Globals::class)->get('contact', 'id');

    // The sets with their values, then the contact blueprint once.
    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(4);
});

it('edits globals in the admin', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser(Panel::ACCESS, 'cms.globals.view'));

    get('/admin/cms/globals')->assertInertia(fn (Assert $page) => $page->component('Cms/Globals/Index', shouldExist: false)->where('sets.0.handle', 'contact'));
    get('/admin/cms/globals/contact?locale=id')->assertInertia(fn (Assert $page) => $page
        ->component('Cms/Globals/Edit', shouldExist: false)
        ->where('locale', 'id')->where('shared', ['phone', 'open_weekends'])->where('version', null)->where('can.update', false));
    put('/admin/cms/globals/contact', ['data' => ['address' => 'x']])->assertForbidden();

    actingAs(adminUser(Panel::ACCESS, 'cms.globals.view', 'cms.globals.update'));
    put('/admin/cms/globals/contact', ['data' => ['address' => ''], 'locale' => 'en'])->assertSessionHasErrors('data.address');
    put('/admin/cms/globals/contact', ['data' => ['address' => '1 Main St'], 'locale' => 'en'])->assertSessionHasNoErrors();
    expect($this->contact->values()->firstOrFail()->data['address'])->toBe('1 Main St');
    get('/admin/cms/globals/contact')->assertInertia(fn (Assert $page) => $page->where('version', 1));
    put('/admin/cms/globals/contact', ['data' => ['address' => '2 Main St'], 'locale' => 'en', 'version' => 1])->assertSessionHasNoErrors();
    put('/admin/cms/globals/contact', ['data' => ['address' => 'Stale'], 'locale' => 'en', 'version' => 1])->assertSessionHasErrors('version');

    actingAs(adminUser(Panel::ACCESS, 'cms.global-sets.view', 'cms.global-sets.create'));
    post('/admin/global-sets', ['title' => 'Social', 'handle' => 'social'])->assertSessionHasNoErrors();
    expect(GlobalSet::query()->where('handle', 'social')->exists())->toBeTrue();
});
