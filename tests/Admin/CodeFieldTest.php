<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\Code;
use Sunrice\Tests\Admin\Fixtures\EmbedSettings;
use Sunrice\Tests\Admin\Fixtures\ShopSettings;
use Sunrice\Tests\Admin\Fixtures\TestPlugin;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(new TestPlugin('shop', register: fn (Panel $p) => $p->settings(ShopSettings::class, EmbedSettings::class))));
});

it('describes a code field: its language, lines and limit', function () {
    expect(Code::make('snippet')->toArray())->toMatchArray(['type' => 'code', 'language' => 'plain', 'minLines' => 4, 'maxLines' => 20, 'maxLength' => 65535])
        ->and(Code::make('snippet')->language('html')->lines(6, 3)->maxLength(500)->toArray())->toMatchArray(['language' => 'html', 'minLines' => 6, 'maxLines' => 6, 'maxLength' => 500])
        ->and(Code::make('snippet')->maxLength(500)->validationRules(null))->toBe(['nullable', 'string', 'max:500']);

    expect(fn () => Code::make('snippet')->language('php'))->toThrow(InvalidArgumentException::class, 'html, css, javascript, json, plain');
});

it('edits code on a settings page, under the section\'s warning', function () {
    actingAs(adminUser(Panel::ACCESS, 'shop.settings.embed'));

    get('/admin/settings/embed')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('section.warning', 'Scripts here run for every customer.')
        ->where('form.0.fields.0', fn ($field) => $field['type'] === 'code' && $field['language'] === 'javascript' && $field['minLines'] === 3));

    $code = "window.chat = {\n    id: 'x',\n};";
    put('/admin/settings/embed', ['widget' => $code])->assertSessionHasNoErrors();
    expect(app(EmbedSettings::class)->widget)->toBe($code);

    put('/admin/settings/embed', ['widget' => str_repeat('x', 101)])->assertSessionHasErrors('widget');
    expect(app(EmbedSettings::class)->widget)->toBe($code);
});

it('has no warning unless the section gives one', function () {
    actingAs(adminUser(Panel::ACCESS, 'shop.settings.shop'));

    get('/admin/settings/shop')->assertInertia(fn (Assert $page) => $page->where('section.warning', null));
});

it('hides a section that is switched off, keeping its values and permission', function () {
    app(EmbedSettings::class)->fill(['widget' => 'kept()'])->save();
    actingAs($user = adminUser(Panel::ACCESS, 'shop.settings.embed', 'shop.settings.shop'));
    config(['shop.embed' => false]);

    get('/admin/settings/embed')->assertNotFound();
    put('/admin/settings/embed', ['widget' => 'other()'])->assertNotFound();
    get('/admin/settings/shop')->assertInertia(fn (Assert $page) => $page->where('sections', [['key' => 'shop', 'label' => 'Shop', 'url' => 'settings/shop']]));
    expect(app(Panel::class)->getSettings()['embed']->allows($user))->toBeFalse()
        ->and(app(Panel::class)->getPermissions())->toHaveKey('shop.settings.embed')
        ->and(app(EmbedSettings::class)->widget)->toBe('kept()');

    // Its only section gone: so is the Settings item.
    actingAs($only = adminUser(Panel::ACCESS, 'shop.settings.embed'));
    $items = collect(app(Panel::class)->navigationFor($only))->flatMap(fn (array $group) => $group['items']);
    expect($items->pluck('key')->all())->not->toContain('settings');

    config(['shop.embed' => true]);
    get('/admin/settings/embed')->assertOk();
});
