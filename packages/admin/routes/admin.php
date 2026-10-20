<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Sunrice\Admin\Http\Controllers\ActivityController;
use Sunrice\Admin\Http\Controllers\DashboardController;
use Sunrice\Admin\Http\Controllers\EditLocksController;
use Sunrice\Admin\Http\Controllers\PreferencesController;
use Sunrice\Admin\Http\Controllers\SettingsController;
use Sunrice\Admin\Preferences\Preferences;

/*
 * The panel's own routes for signed-in users with Panel::ACCESS. Loaded
 * by PanelRoutes under the panel's path, with names "sunrice.admin.*".
 */

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
Route::post('settings/mail/test', [SettingsController::class, 'testMail'])->middleware('can:sunrice.settings.mail')->name('settings.mail.test');
Route::get('settings/{section}', [SettingsController::class, 'edit'])->name('settings.edit');
Route::put('settings/{section}', [SettingsController::class, 'update'])->name('settings.update');

Route::get('activity', ActivityController::class)->name('activity.index');
Route::post('activity/prune', [ActivityController::class, 'prune'])->middleware('can:sunrice.activity.prune')->name('activity.prune');

// Edit locks of the types plugins register with $panel->lockable(); each item is checked there.
Route::prefix('locks/{type}/{id}')->name('locks.')->controller(EditLocksController::class)->group(function (): void {
    Route::post('/', 'renew')->name('renew');
    Route::post('take', 'take')->name('take');
    Route::post('release', 'release')->name('release');
    Route::post('keep', 'keep')->name('keep');
});
Route::get('kept-edits/{kept}', [EditLocksController::class, 'show'])->name('kept-edits.show');
Route::delete('kept-edits/{kept}', [EditLocksController::class, 'destroy'])->name('kept-edits.destroy');

// The signed-in user's own preferences (useTablePreferences); nobody else's.
Route::get('preferences/{key}', [PreferencesController::class, 'show'])->name('preferences.show')->where('key', Preferences::KEY_PATTERN);
Route::put('preferences/{key}', [PreferencesController::class, 'update'])->name('preferences.update')->where('key', Preferences::KEY_PATTERN);
