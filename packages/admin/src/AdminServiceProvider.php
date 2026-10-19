<?php

declare(strict_types=1);

namespace Sunrice\Admin;

use Illuminate\Support\ServiceProvider;

/**
 * Boots the admin panel. Panels and their plugins are configured in the
 * app's own panel provider (see docs/architecture.md).
 *
 * @api
 */
final class AdminServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void {}
}
