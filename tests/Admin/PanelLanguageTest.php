<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Permissions\Permission;
use Sunrice\Admin\Preferences\Preferences;
use Sunrice\Admin\Resources\Forms\Select;
use Sunrice\Admin\Support\PanelLocale;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

beforeEach(function () {
    usePanel();
    // A language of the test's own (the packages' lang/id.json may say otherwise).
    $dir = sys_get_temp_dir().'/sunrice-lang-'.uniqid();
    mkdir($dir);
    file_put_contents("{$dir}/id.json", json_encode(['Dashboard' => 'Dasbor', 'Saved.' => 'Tersimpan.', 'Name' => 'Nama']));
    app('translator')->getLoader()->addJsonPath($dir);
});

it('speaks each user\'s panel language, apart from the site\'s, with the words in the page', function () {
    $user = adminUser(Panel::ACCESS, 'sunrice.users.view', 'sunrice.users.create');
    actingAs($user);

    get('/admin')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('sunrice.locale', 'en')->where('sunrice.locales', ['en' => 'English', 'id' => 'Bahasa Indonesia']));

    put('/admin/language', ['locale' => 'xx'])->assertSessionHasErrors('locale');
    put('/admin/language', ['locale' => 'id'])->assertRedirect()->assertCookie(PanelLocale::COOKIE, 'id');
    expect(app(Preferences::class)->get($user, 'locale'))->toBe('id');

    $response = get('/admin')->assertInertia(fn (Assert $page) => $page->where('sunrice.locale', 'id'));
    // Definitions written in English are said in it (Say): a field's label.
    get('/admin/users/create')->assertInertia(fn (Assert $page) => $page->where('sections.0.fields.0.label', 'Nama'));
    expect($response->getContent())->toContain('window.sunriceTranslations = {')->toContain('"Dashboard":"Dasbor"');
    // Only the panel: the app's own locale is untouched outside it.
    expect(app()->getLocale())->toBe('id');
});

it('says flash messages and definitions in the panel\'s language, and uses the default for guests', function () {
    config(['sunrice-admin.locale' => 'id']);

    get('/admin/login')->assertInertia(fn (Assert $page) => $page->where('sunrice.locale', 'id'));
    expect(app()->getLocale())->toBe('id');

    actingAs(adminUser(Panel::ACCESS));
    session()->flash('success', 'Saved.');
    get('/admin')->assertInertia(fn (Assert $page) => $page->where('flash.success', 'Tersimpan.'));

    config(['sunrice-admin.locales' => ['en' => 'English'], 'sunrice-admin.locale' => 'id']);
    expect(PanelLocale::default())->toBe('en')->and(PanelLocale::valid('id'))->toBeFalse();
});

it('says a permission\'s label with its placeholders in the panel\'s language, and keeps the English label', function () {
    app()->setLocale('id');
    $permission = Permission::make('shop.orders.view', 'View :label', 'Shop', ['label' => 'Name']);

    expect($permission->label)->toBe('View Name')
        ->and($permission->saidLabel())->toBe(__('View :label', ['label' => 'Nama']));
});

it('keeps option labels that are data as written', function () {
    app()->setLocale('id');
    $said = Select::make('x')->options(['a' => 'Name'])->toArray();
    $kept = Select::make('x')->verbatim()->options(['a' => 'Name'])->toArray();

    expect($said['options'][0]['label'])->toBe(__('Name'))->and($kept['options'][0]['label'])->toBe('Name');
});
