<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Sunrice\Admin\Http\Controllers\DashboardController;

/*
 * The panel's own routes for signed-in users with Panel::ACCESS. Loaded
 * by PanelRoutes under the panel's path, with names "sunrice.admin.*".
 */

Route::get('/', DashboardController::class)->name('dashboard');
