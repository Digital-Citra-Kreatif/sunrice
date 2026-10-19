# Settings

A settings section is a PHP class with typed properties. The panel stores the values, gives each section a form on the **Settings** page (under Administration) and a permission to edit it.

```php
use Sunrice\Admin\Resources\Forms\{Form, NumberInput, Secret, Select, Toggle};
use Sunrice\Admin\Settings\{Encrypted, Settings};

final class ShopSettings extends Settings
{
    public Currency $currency = Currency::Rupiah;   // a backed enum
    public bool $guestCheckout = true;
    public ?int $freeShippingFrom = null;

    #[Encrypted]
    public string $paymentKey = '';

    public static function key(): string { return 'shop'; }

    public static function description(): ?string { return 'How the shop sells.'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('currency')->options(['IDR' => 'Rupiah', 'USD' => 'US dollar'])->required(),
            Toggle::make('guestCheckout'),
            NumberInput::make('freeShippingFrom')->integer()->min(0),
            Secret::make('paymentKey'),
        ]);
    }
}
```

Register it in a plugin's `register()` or in your panel provider:

```php
$panel->settings(ShopSettings::class);
```

That adds the section to `/admin/settings/shop` and a permission to edit it: `shop.settings.shop` when the plugin `shop` registers it, `settings.shop` when your app does. The Settings sidebar item shows for users who may edit at least one section. The keys `security` and `branding` belong to the panel's own sections:

- **Security** (`sunrice.settings.security`): two-factor login.
- **Branding** (`sunrice.settings.branding`, `Branding\BrandingSettings`): the panel's name, logo and colour, and the sign-in page's headline and text. Empty values keep `$panel->brand()` and the default look; the colour (a hex colour) goes on buttons, highlights and focus rings, with dark or light text on it, whichever reads better. Pages get them as `sunrice.brand`, `sunrice.logo`, `sunrice.color`, `sunrice.colorText` and `sunrice.login`.

## Reading and writing

```php
$shop = app(ShopSettings::class);
$shop->currency;            // Currency::Rupiah until someone saves another

$shop->freeShippingFrom = 250_000;
$shop->save();

$shop->fill(['guestCheckout' => '0'])->save();   // converted to the property's type
```

- Properties are `string`, `int`, `float`, `bool`, `array` or a backed enum, nullable or not, and need a default. Anything else is refused with an error naming the property.
- The default applies until a value is saved, and whenever a stored value no longer fits the type (after you change a property's type, say), so reading settings never fails.
- All sections are read with one query, cached until a section is saved. Before the panel's migrations have run, the defaults apply.
- `#[Encrypted]` stores the value encrypted with the app key. If the key changes, the default applies.
- Settings that aren't on the form (counters, state your code keeps) are stored like the others and never changed by the form.

## The form

Settings forms use the [resource form fields](resources.md#forms), with field names matching the properties. Only the fields on the form are written. `Secret` is for API keys and the like: its value is never sent to the browser, and leaving it empty keeps the saved one. `Password` (which hashes) and fields with `saveUsing()` can't be settings.

Saving a section fires `Sunrice\Admin\Events\SettingsSaved` with the section's key and the names of the settings that changed (no values: they may be secrets). The `Sunrice\Admin\Actions\Settings\SaveSettings` action does the same for your own screens.
