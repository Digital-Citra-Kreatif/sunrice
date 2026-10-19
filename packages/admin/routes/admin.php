<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Sunrice\Admin\Http\Controllers\DashboardController;
use Sunrice\Admin\Http\Controllers\SettingsController;

/*
 * The panel's own routes for signed-in users with Panel::ACCESS. Loaded
 * by PanelRoutes under the panel's path, with names "sunrice.admin.*".
 */

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
Route::get('settings/{section}', [SettingsController::class, 'edit'])->name('settings.edit');
Route::put('settings/{section}', [SettingsController::class, 'update'])->name('settings.update');
