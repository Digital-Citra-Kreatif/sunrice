<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Sunrice\Admin\Actions\Permissions\SeedRoles;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Globals\SaveGlobals;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Content;
use Sunrice\Cms\Globals;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Form;
use Sunrice\Cms\Models\GlobalSet;
use Sunrice\Cms\Models\Menu;

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

    // Left out, the language is the current one (as Cms::global()), not always the main one.
    app()->setLocale('id');
    expect(app(Globals::class)->get('contact')['address'])->toBe('Jl. Utama 1')
        ->and(Content::global('contact')['address'])->toBe('Jl. Utama 1');
    app()->setLocale('fr');
    expect(app(Globals::class)->get('contact')['address'])->toBe('1 Main St');
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

    get('/admin/cms/globals')->assertInertia(fn (Assert $page) => $page->component('Resources/Index', shouldExist: false)
        ->where('rows.data.0.key', 'contact')->where('rows.data.0.url', url('/admin/cms/globals/contact/values')));
    get('/admin/cms/globals/contact/values?locale=id')->assertInertia(fn (Assert $page) => $page
        ->component('Cms/Globals/Edit', shouldExist: false)
        ->where('locale', 'id')->where('shared', ['phone', 'open_weekends'])->where('version', null)->where('can.update', false));
    put('/admin/cms/globals/contact/values', ['data' => ['address' => 'x']])->assertForbidden();

    actingAs(adminUser(Panel::ACCESS, 'cms.globals.view', 'cms.globals.update'));
    put('/admin/cms/globals/contact/values', ['data' => ['address' => ''], 'locale' => 'en'])->assertSessionHasErrors('data.address');
    put('/admin/cms/globals/contact/values', ['data' => ['address' => '1 Main St'], 'locale' => 'en'])->assertSessionHasNoErrors();
    expect($this->contact->values()->firstOrFail()->data['address'])->toBe('1 Main St');
    get('/admin/cms/globals/contact/values')->assertInertia(fn (Assert $page) => $page->where('version', 1));
    put('/admin/cms/globals/contact/values', ['data' => ['address' => '2 Main St'], 'locale' => 'en', 'version' => 1])->assertSessionHasNoErrors();
    put('/admin/cms/globals/contact/values', ['data' => ['address' => 'Stale'], 'locale' => 'en', 'version' => 1])->assertSessionHasErrors('version');

    actingAs(adminUser(Panel::ACCESS, 'cms.globals.view', 'cms.globals.create'));
    post('/admin/cms/globals', ['title' => 'Social', 'handle' => 'social'])->assertSessionHasNoErrors();
    expect(GlobalSet::query()->where('handle', 'social')->exists())->toBeTrue();
});

it('renames a global set or menu from its form, the handle made from the title when creating', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser(Panel::ACCESS, 'cms.globals.view', 'cms.globals.create', 'cms.globals.update', 'cms.globals.configure', 'cms.menus.view', 'cms.menus.create', 'cms.menus.update', 'cms.menus.configure'));
    $menu = Menu::query()->create(['handle' => 'mian', 'title' => 'Main']);

    get('/admin/cms/globals/create')->assertInertia(fn (Assert $page) => $page->where('sections.0.fields.1', fn ($field) => $field['name'] === 'handle' && $field['slugFrom'] === 'title' && $field['slugSeparator'] === '_'));
    put('/admin/cms/globals/contact', ['title' => 'Contact', 'handle' => 'contact-us'])->assertSessionHasErrors(['handle' => 'Use lowercase letters, digits and underscores, starting with a letter: try "contact_us".']);
    put('/admin/cms/globals/contact', ['title' => 'Contact', 'handle' => 'contact_us'])->assertSessionHasNoErrors();
    put('/admin/cms/menus/mian', ['title' => 'Main', 'handle' => 'main'])->assertSessionHasNoErrors();

    expect($this->contact->fresh()->handle)->toBe('contact_us')
        ->and($menu->fresh()->handle)->toBe('main')
        ->and(app(Globals::class)->get('contact_us'))->toHaveKey('address');
});

it('lets editors save a set\'s values but not change its handle or blueprint', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    app(SeedRoles::class)->handle();
    Blueprint::query()->create(['handle' => 'other', 'title' => 'Other', 'fields' => [['handle' => 'note', 'type' => 'text']]]);
    $editor = adminUser()->assignRole('editor');
    actingAs($editor);

    // No way to the set's Settings: not from its values, not from the list.
    get('/admin/cms/globals/contact/values')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('can.update', true)->where('settings', null));
    get('/admin/cms/globals')->assertInertia(fn (Assert $page) => $page->where('rows.data.0.can.update', false));
    get('/admin/cms/globals/contact/edit')->assertForbidden();

    // Refused on the server, whatever the request sends.
    put('/admin/cms/globals/contact', ['title' => 'Contact', 'handle' => 'footer'])->assertForbidden();
    put('/admin/cms/globals/contact', ['title' => 'Contact', 'blueprint' => 'other'])->assertForbidden();
    expect($this->contact->fresh())->handle->toBe('contact')->blueprint->toBe('contact');

    put('/admin/cms/globals/contact/values', ['data' => ['address' => '1 Main St'], 'locale' => 'en'])->assertSessionHasNoErrors();
    expect(app(Globals::class)->get('contact')['address'])->toBe('1 Main St');
});

it('lets administrators change a set\'s handle and blueprint', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    app(SeedRoles::class)->handle();
    Blueprint::query()->create(['handle' => 'other', 'title' => 'Other', 'fields' => [['handle' => 'note', 'type' => 'text']]]);
    actingAs(adminUser()->assignRole('administrator'));

    get('/admin/cms/globals/contact/values')->assertInertia(fn (Assert $page) => $page->where('settings', url('/admin/cms/globals/contact/edit')));
    get('/admin/cms/globals/contact/edit')->assertOk();
    put('/admin/cms/globals/contact', ['title' => 'Contact', 'handle' => 'contact_us', 'blueprint' => 'other'])->assertSessionHasNoErrors();

    expect($this->contact->fresh())->handle->toBe('contact_us')->blueprint->toBe('other')
        ->and(Role::findByName('editor')->hasPermissionTo('cms.globals.configure'))->toBeFalse();
});

it('gives cms.globals.configure on upgrade to those who looked after the sets, not to editors', function () {
    $permission = fn (string ...$names) => array_map(fn (string $name) => Permission::findOrCreate($name, 'web'), $names);
    $permission('cms.globals.view', 'cms.globals.update', 'cms.globals.create', 'cms.globals.delete');
    $editor = Role::findOrCreate('editor', 'web')->givePermissionTo('cms.globals.view', 'cms.globals.update');
    $admin = Role::findOrCreate('administrator', 'web')->givePermissionTo('cms.globals.view', 'cms.globals.create', 'cms.globals.update', 'cms.globals.delete');
    $developer = Role::findOrCreate('developer', 'web')->givePermissionTo('cms.globals.update', 'cms.globals.delete');
    $creator = Role::findOrCreate('creator', 'web')->givePermissionTo('cms.globals.create');
    $person = adminUser('cms.globals.update', 'cms.globals.create');
    $other = adminUser('cms.globals.update');
    Permission::query()->where('name', 'cms.globals.configure')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $migration = require dirname(__DIR__, 2).'/packages/cms/database/migrations/2026_10_25_000001_give_globals_configure_permission.php';
    $migration->up();
    $migration->up(); // again: nothing changes

    $has = fn ($model) => $model->fresh()->hasPermissionTo('cms.globals.configure');
    expect($has($admin))->toBeTrue()
        ->and($has($developer))->toBeTrue()
        ->and($has($editor))->toBeFalse()
        ->and($has($creator))->toBeFalse()
        ->and($person->fresh()->getDirectPermissions()->pluck('name')->all())->toContain('cms.globals.configure')
        ->and($other->fresh()->getDirectPermissions()->pluck('name')->all())->not->toContain('cms.globals.configure')
        ->and(DB::table('role_has_permissions')->where('role_id', $admin->id)->count())->toBe(5);
});

it('offers each blueprint picker the blueprints for its purpose', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser(Panel::ACCESS, 'cms.globals.view', 'cms.globals.create', 'cms.globals.update', 'cms.globals.configure', 'cms.forms.view', 'cms.forms.create', 'cms.collections.view', 'cms.collections.create'));
    Blueprint::query()->create(['handle' => 'contact_form', 'title' => 'Contact form']);
    Blueprint::query()->create(['handle' => 'agent', 'title' => 'Agent']);
    Blueprint::query()->create(['handle' => 'unused', 'title' => 'Unused']);
    Form::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'contact_form']);
    Collection::query()->create(['handle' => 'agents', 'title' => 'Agents', 'blueprint' => 'agent']);
    $choices = fn (string $url, string $name = 'blueprint') => array_column(collect(get($url)->assertOk()->viewData('page')['props']['sections'])
        ->flatMap(fn ($section) => $section['fields'])->firstWhere('name', $name)['options'], 'value');

    expect($choices('/admin/cms/globals/create'))->toBe(['contact', 'unused'])
        ->and($choices('/admin/cms/globals/contact/edit'))->toBe(['contact', 'unused'])
        ->and($choices('/admin/cms/forms/create'))->toBe(['contact_form', 'unused'])
        ->and($choices('/admin/cms/collections/create'))->toBe(['agent', 'unused']);
    post('/admin/cms/globals', ['title' => 'Agent info', 'handle' => 'agent_info', 'blueprint' => 'agent'])->assertSessionHasErrors('blueprint');
});

it('shows the main language\'s values where another language has none', function () {
    Blueprint::query()->where('handle', 'contact')->firstOrFail()->update(['fields' => [
        ['handle' => 'address', 'type' => 'textarea'],
        ['handle' => 'hours', 'type' => 'text'],
        ['handle' => 'phone', 'type' => 'text', 'translatable' => false],
    ]]);
    app(SaveGlobals::class)->handle($this->contact->fresh() ?? $this->contact, ['address' => '1 Main St', 'hours' => '9 to 5', 'phone' => '+62 21 555']);

    // Never saved in Indonesian: the site and the editor start from the English values.
    expect(app(Globals::class)->get('contact', 'id'))->toBe(['address' => '1 Main St', 'hours' => '9 to 5', 'phone' => '+62 21 555']);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser(Panel::ACCESS, 'cms.globals.view'));
    get('/admin/cms/globals/contact/values?locale=id')->assertInertia(fn (Assert $page) => $page->where('values.address', '1 Main St')->where('version', null));

    // Saved with a field left empty: that field still shows the English one.
    app(SaveGlobals::class)->handle($this->contact->fresh() ?? $this->contact, ['address' => 'Jl. Utama 1', 'hours' => ''], 'id');
    app(Globals::class)->flush();
    expect(app(Globals::class)->get('contact', 'id'))->toBe(['address' => 'Jl. Utama 1', 'hours' => '9 to 5', 'phone' => '+62 21 555']);
});
