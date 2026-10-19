<?php

declare(strict_types=1);

namespace Workbench\App\Admin;

use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\NumberInput;
use Sunrice\Admin\Resources\Forms\Secret;
use Sunrice\Admin\Resources\Forms\Section;
use Sunrice\Admin\Resources\Forms\Select;
use Sunrice\Admin\Resources\Forms\TextInput;
use Sunrice\Admin\Resources\Forms\Toggle;
use Sunrice\Admin\Settings\Encrypted;
use Sunrice\Admin\Settings\Settings;

/** The workbench's settings section, set up the way an app would. */
final class StoreSettings extends Settings
{
    public string $name = 'Sunrice Store';

    public string $currency = 'IDR';

    public bool $guestCheckout = true;

    public ?int $freeShippingFrom = null;

    #[Encrypted]
    public string $paymentKey = '';

    public static function key(): string
    {
        return 'store';
    }

    public static function description(): ?string
    {
        return 'How the store sells.';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required(),
            Section::make('Checkout')->columns(2)->schema([
                Select::make('currency')->options(['IDR' => 'Rupiah', 'USD' => 'US dollar'])->required(),
                NumberInput::make('freeShippingFrom')->label('Free shipping from')->integer()->min(0),
                Toggle::make('guestCheckout')->label('Guest checkout'),
            ]),
            Section::make('Payments')->schema([
                Secret::make('paymentKey')->label('Payment API key')->help('Stored encrypted and never shown again.'),
            ]),
        ]);
    }
}
