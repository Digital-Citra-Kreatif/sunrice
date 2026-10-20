<?php

declare(strict_types=1);

namespace Sunrice\Admin\Settings;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Str;
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

    public function save(): void
    {
        app(SettingsStore::class)->save($this);
    }

    /** @return array<string, mixed> property => value */
    public function toArray(): array
    {
        return app(SettingsStore::class)->values($this);
    }
}
