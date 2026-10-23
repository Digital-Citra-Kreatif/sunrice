<?php

declare(strict_types=1);

namespace Sunrice\Admin\Settings;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Str;
use Sunrice\Admin\Events\SettingsSaved;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Support\Card;

/**
 * A section of settings: typed public properties with defaults, stored
 * in the database and edited on the panel's Settings page.
 *
 *     final class ShopSettings extends Settings
 *     {
 *         public string $currency = 'IDR';
 *         public bool $guestCheckout = true;
 *         public ?int $freeShippingFrom = null;
 *
 *         #[Encrypted]
 *         public string $paymentKey = '';
 *
 *         public static function key(): string { return 'shop'; }
 *
 *         public static function form(Form $form): Form
 *         {
 *             return $form->schema([
 *                 Select::make('currency')->options(['IDR' => 'Rupiah', 'USD' => 'US dollar'])->required(),
 *                 Toggle::make('guestCheckout'),
 *                 NumberInput::make('freeShippingFrom')->integer()->min(0),
 *                 Secret::make('paymentKey'),
 *             ]);
 *         }
 *     }
 *
 *     app(ShopSettings::class)->currency;
 *
 * Properties may be string, int, float, bool, array or a backed enum,
 * nullable or not, and need a default: it applies until a value is saved,
 * and when a stored value no longer fits the type. Register the section
 * with `$panel->settings(ShopSettings::class)` to edit it in the panel.
 *
 * @api
 */
abstract class Settings
{
    /** Loaded with the stored values (one cached query per request for all sections). */
    final public function __construct()
    {
        app(SettingsStore::class)->load($this);
    }

    /** Where it's stored and its URL: "shop" → /admin/settings/shop. */
    abstract public static function key(): string;

    abstract public static function form(Form $form): Form;

    public static function label(): string
    {
        return Str::headline(static::key());
    }

    public static function description(): ?string
    {
        return null;
    }

    /**
     * Whether the section is in use (a config switch can turn a feature
     * off): when false it's left out of the Settings menu, its page is not
     * found and agents' tools don't see it. Its permission stays, and so
     * do its stored values, ready for when it's back on.
     */
    public static function available(): bool
    {
        return true;
    }

    /**
     * A warning shown at the top of the section's page, above its form:
     * what can go wrong with these settings (code run on every page, a key
     * that charges money). Translated with __(). None by default.
     */
    public static function warning(): ?string
    {
        return null;
    }

    /**
     * Read-only cards under the section's form, for `$user` (who may edit
     * the section): how something is set up outside the panel, with
     * buttons to try it. Nothing by default. A section without fields
     * shows only its cards.
     *
     * @return list<Card>
     */
    public static function cards(Authorizable $user): array
    {
        return [];
    }

    /**
     * Set properties from input, converted to their types; unknown keys
     * are ignored.
     *
     * @param  array<string, mixed>  $values
     */
    public function fill(array $values): static
    {
        app(SettingsStore::class)->fill($this, $values);

        return $this;
    }

    /**
     * Store the values and, when some changed, fire SettingsSaved with
     * their names (as the Settings page does).
     *
     * @return list<string> the settings that changed
     */
    public function save(): array
    {
        $before = (new static)->toArray();
        $after = $this->toArray();
        $changed = array_values(array_filter(array_keys($after), fn (string $name) => $after[$name] !== $before[$name]));
        app(SettingsStore::class)->save($this);
        if ($changed !== []) {
            SettingsSaved::dispatch(static::key(), $changed);
        }

        return $changed;
    }

    /** @return array<string, mixed> property => value */
    public function toArray(): array
    {
        return app(SettingsStore::class)->values($this);
    }
}
