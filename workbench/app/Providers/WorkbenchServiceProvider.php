<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;

/** Wires the workbench app's panel (added with the Panel API). */
class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void {}
}
