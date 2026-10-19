<?php

declare(strict_types=1);

namespace Sunrice\Admin;

use Sunrice\Admin\Contracts\Plugin;

/**
 * A convenient base for plugins: no dependencies and nothing to boot
 * unless overridden.
 *
 *     final class ShopPlugin extends BasePlugin
 *     {
 *         public function id(): string { return 'shop'; }
 *
 *         public function register(Panel $panel): void
 *         {
 *             $panel->permissions(Permission::make('shop.orders.view', 'View orders', 'Shop'));
 *         }
 *     }
 *
 * @api
 */
abstract class BasePlugin implements Plugin
{
    /** Plugins take their options through fluent methods, not the constructor. */
    final public function __construct() {}

    public static function make(): static
    {
        return new static;
    }

    public function requires(): array
    {
        return [];
    }

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void {}
}
