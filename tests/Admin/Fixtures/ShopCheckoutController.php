<?php

declare(strict_types=1);

namespace Sunrice\Tests\Admin\Fixtures;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Admin\Actions\Settings\SaveSettings;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\NumberInput;
use Sunrice\Admin\Resources\Forms\Section;
use Sunrice\Admin\Resources\Forms\Toggle;
use Sunrice\Admin\Settings\SettingsFields;

/** A plugin's own page among the settings sections (Panel::settingsPage()), with some of ShopSettings' fields. */
final class ShopCheckoutController
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Shop/Checkout', [
            'settingsNav' => $user instanceof Authorizable ? app(Panel::class)->settingsNavigation($user) : [],
            'checkout' => self::fields()->props(),
        ]);
    }

    public function update(Request $request, SaveSettings $save): RedirectResponse
    {
        $save->handle(app(ShopSettings::class), self::fields()->validate($request->all()));

        return back();
    }

    public static function fields(): SettingsFields
    {
        return SettingsFields::make(ShopSettings::class, (new Form)->schema([
            Section::make('Checkout')->schema([
                Toggle::make('guestCheckout')->label('Guest checkout'),
                NumberInput::make('freeShippingFrom')->integer()->min(0),
            ]),
        ]));
    }
}
