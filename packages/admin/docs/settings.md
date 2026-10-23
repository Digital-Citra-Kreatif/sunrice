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

Register it in a plugin's `register()` or in your panel provider (see [Customising the panel in your app](panel.md#customising-the-panel-in-your-app) for what else an app can add):

```php
$panel->settings(ShopSettings::class);
```

That adds the section to `/admin/settings/shop` and a permission to edit it: `shop.settings.shop` when the plugin `shop` registers it, `settings.shop` when your app does. The Settings sidebar item shows for users who may edit at least one section (or open a page put among them with `settingsPage()` below or `NavigationItem::inSettings()`, see [navigation](panel.md#navigation)); the Settings menu beside the form lists the sections in the order registered, with those pages after the section each names. The keys `branding` ([the panel's look and sign-in page](branding.md), `sunrice.settings.branding`), `security` (two-factor login, `sunrice.settings.security`) and `mail` (how mail is sent, with a test email: `sunrice.settings.mail`, see [Mail](mail.md)) belong to the panel's own sections.

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

Settings forms use the [resource form fields](resources.md#forms), with field names matching the properties. A setting that is empty shows its field's `->default()` when it has one (CMS Languages shows the config's languages that way, until saved). Only the fields on the form are written. `Secret` is for API keys and the like: its value is never sent to the browser, and leaving it empty keeps the saved one. `Password` (which hashes) and fields with `saveUsing()` can't be settings.

A section that a config switch can turn off says so with `available()`: while it returns false the section is left out of the Settings menu (and the Settings item when it was the only one), its page is not found (404) and agents' `get_settings` and `update_settings` don't see it. Its permission and stored values are kept, so switching it back on brings everything back (the CMS's Site code is off with `SUNRICE_SITE_CODE=false`):

```php
public static function available(): bool
{
    return (bool) config('shop.payments');
}
```

A section whose settings can do harm says so with `warning()`: the text shows in an amber callout at the top of its page, above the form (Settings → Site code in the CMS warns that its code runs in every visitor's browser):

```php
public static function warning(): ?string
{
    return 'Changing the payment key takes the shop offline until the new key is checked.';
}
```

A section may also show read-only cards under its form with `cards(Authorizable $user)`, built with `Sunrice\Admin\Support\Card` (see [Cards beside the form](resources.md#cards-beside-the-form)): how something is set up outside the panel, with a button that tries it. A section with no fields shows only its cards and no Save button (Settings → Mail is one):

```php
public static function cards(Authorizable $user): array
{
    return [Card::make('Payment provider')->rows(['Mode' => config('shop.live') ? 'Live' : 'Test'])
        ->button('Check the connection', route('shop.settings.check', [], false))];
}
```

Saving a section's media pickers records the files they hold as used by it in the media library ("Settings: Shop"), on every save; a section with none any more (its pickers moved to a page of their own) uses none.

Saving a section fires `Sunrice\Admin\Events\SettingsSaved` with the section's key and the names of the settings that changed (no values: they may be secrets), whether the Settings page saved it or your code called `save()` (a seeder, a command): `save()` returns those names, and fires nothing when none changed. The `Sunrice\Admin\Actions\Settings\SaveSettings` action also records the change in the activity log; use it for your own screens.

## A settings page of your own

When a form of fields isn't enough (a table of choices per record, a preview), a plugin or your app adds a page of its own among the sections with `settingsPage()`. It lives at `settings/{key}` like a section, is listed in the Settings menu after the section `$after` (else at the end) for users with `$permission`, and the sidebar's Settings item is current on it. The CMS's Settings → SEO is one:

```php
$panel->settingsPage('seo', 'SEO', SeoController::class, 'cms.settings.site', after: 'site');
```

The controller answers `GET /admin/settings/seo` with `edit()` and `PUT` with `update()` (routes `sunrice.admin.settings.seo.edit` and `.update`), both behind the permission: guard anything else it does too. A key that is a section's (the panel's own `branding`, `security`, `mail` included) or another page's is a `PanelMisconfigured` error at boot; keys are lowercase letters, numbers and dashes. The page shows the Settings menu with `SettingsLayout` (`items` from `$panel->settingsNavigation($user)`, `current` the page's key; see [frontend](frontend.md#ui-components)).

Such a page can still show settings fields, drawn and checked exactly as the Settings page does, with `Sunrice\Admin\Settings\SettingsFields`: some of a section's properties on a form of their own (usually not on the section's form, so one page owns each setting).

```php
use Sunrice\Admin\Settings\SettingsFields;

$fields = SettingsFields::make(ShopSettings::class, (new Form)->schema([
    Section::make('Checkout')->schema([Toggle::make('guestCheckout'), NumberInput::make('freeShippingFrom')->integer()->min(0)]),
]));

// edit(): what FormSections from @imajiner/admin-sdk/ui takes
Inertia::render('Shop/Checkout', ['settingsNav' => $panel->settingsNavigation($user), 'checkout' => $fields->props()]);

// update(): checked as the Settings page checks them (ValidationException), then saved
app(SaveSettings::class)->handle(app(ShopSettings::class), $fields->validate($request->all()));
```

`props()` is `['sections' => …, 'values' => …, 'secrets' => …]`: draw it with `<FormSections sections={checkout.sections} data={…} errors={…} onChange={…} />`, starting the data from `values` (a saved `Secret` is sent empty; `secrets` says which have a value, and leaving one empty keeps it). `validate($input)` returns only these fields' values, ready for `SaveSettings`. Each field must be one of the class's properties (`PanelMisconfigured` otherwise).

Give the page's settings fields to AI agents with `fields:`, a closure returning its `SettingsFields`: `get_panel_info` lists the page as a settings section of its key and label, and `get_settings` / `update_settings` read and change those fields, checked as the page checks them, behind the page's permission, logged like any settings change ([AI access](ai.md)). The CMS gives Settings → SEO's structured data this way:

```php
$panel->settingsPage('seo', 'SEO', SeoController::class, 'cms.settings.site', after: 'site', fields: SaveSeoSettings::structuredData(...));
```

Only settings fields: what a page saves elsewhere (the CMS's SEO fields per collection) isn't offered.

