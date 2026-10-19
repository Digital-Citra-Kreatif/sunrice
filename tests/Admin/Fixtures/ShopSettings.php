<?php

declare(strict_types=1);

namespace Sunrice\Tests\Admin\Fixtures;

use Sunrice\Admin\Resources\Forms\CheckboxList;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\NumberInput;
use Sunrice\Admin\Resources\Forms\Secret;
use Sunrice\Admin\Resources\Forms\Section;
use Sunrice\Admin\Resources\Forms\Select;
use Sunrice\Admin\Resources\Forms\TextInput;
use Sunrice\Admin\Resources\Forms\Toggle;
use Sunrice\Admin\Settings\Encrypted;
use Sunrice\Admin\Settings\Settings;

final class ShopSettings extends Settings
{
    public string $name = 'Shop';

    public Currency $currency = Currency::Rupiah;

    public bool $guestCheckout = true;

    public ?int $freeShippingFrom = null;

    public float $taxRate = 0.11;

    /** @var list<string> */
    public array $countries = ['ID'];

    #[Encrypted]
    public string $paymentKey = '';

    /** Not on the form: only code sets it. */
    public int $orders = 0;

    public static function key(): string
    {
        return 'shop';
    }

    public static function description(): ?string
    {
        return 'How the shop sells.';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required(),
            Section::make('Checkout')->columns(2)->schema([
                Select::make('currency')->options(['IDR' => 'Rupiah', 'USD' => 'US dollar'])->required(),
                Toggle::make('guestCheckout'),
                NumberInput::make('freeShippingFrom')->integer()->min(0),
                NumberInput::make('taxRate')->min(0)->max(1)->required(),
                CheckboxList::make('countries')->options(['ID' => 'Indonesia', 'SG' => 'Singapore', 'MY' => 'Malaysia']),
                Secret::make('paymentKey')->required(),
            ]),
        ]);
    }
}
