<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Sunrice\Admin\Help\HelpPages;
use Sunrice\Admin\Http\Controllers\AccountController;
use Sunrice\Admin\Http\Controllers\ActivityController;
use Sunrice\Admin\Http\Controllers\BrandingPreviewController;
use Sunrice\Admin\Http\Controllers\EditLocksController;
use Sunrice\Admin\Http\Controllers\HelpController;
use Sunrice\Admin\Http\Controllers\LanguageController;
use Sunrice\Admin\Http\Controllers\PreferencesController;
use Sunrice\Admin\Http\Controllers\SettingsController;
use Sunrice\Admin\Http\Controllers\StrongerPasswordController;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Preferences\Preferences;

/*
 * The panel's own routes for signed-in users with Panel::ACCESS. Loaded
 * by PanelRoutes under the panel's path, with names "sunrice.admin.*".
 */

// The widgets page, or the app's own (Panel::dashboard()).
Route::get('/', app(Panel::class)->getDashboard())->name('dashboard');

Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
// Settings → Branding's preview of the sidebar with the form's unsaved values.
Route::get('branding/preview', BrandingPreviewController::class)->middleware('can:sunrice.settings.branding')->name('branding.preview');
Route::post('settings/mail/test', [SettingsController::class, 'testMail'])->middleware('can:sunrice.settings.mail')->name('settings.mail.test');
// Plugins' own pages among the sections (Panel::settingsPage()), before settings/{section} catches their address.
foreach (app(Panel::class)->getSettingsPages() as $key => $page) {
    Route::controller($page['controller'])->middleware('can:'.$page['permission'])->name("settings.{$key}.")->group(function () use ($key): void {
        Route::get("settings/{$key}", 'edit')->name('edit');
        Route::put("settings/{$key}", 'update')->name('update');
    });
}
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

// The signed-in user's own account: name, email and password; nobody else's.
Route::get('account', [AccountController::class, 'edit'])->name('account.edit');
Route::put('account', [AccountController::class, 'update'])->middleware('throttle:10,1')->name('account.update'); // limits guesses at the current password

// After signing in with a weak password, every page leads here until a stronger one is chosen (RequireStrongPassword).
Route::get('stronger-password', [StrongerPasswordController::class, 'edit'])->name('password.stronger');
Route::put('stronger-password', [StrongerPasswordController::class, 'update'])->middleware('throttle:10,1')->name('password.stronger.update');

// The signed-in user's own preferences (useTablePreferences); nobody else's.
// The panel's language for the signed-in user (PanelLocale).
Route::put('language', [LanguageController::class, 'update'])->name('language.update');
Route::get('preferences/{key}', [PreferencesController::class, 'show'])->name('preferences.show')->where('key', Preferences::KEY_PATTERN);
Route::put('preferences/{key}', [PreferencesController::class, 'update'])->name('preferences.update')->where('key', Preferences::KEY_PATTERN);

// Help: the developer docs and the user guide (Panel::docs(), Panel::guide()); each checks its permission.
Route::get('docs/{page?}', [HelpController::class, 'docs'])->middleware('can:sunrice.docs.view')->name('docs.show')->where('page', HelpPages::PAGE_PATTERN);
Route::get('guide/images/{section}/{file}', [HelpController::class, 'image'])->middleware('can:sunrice.guide.view')->name('guide.image')
    ->where(['section' => '[a-z0-9-]+', 'file' => HelpController::IMAGE_PATTERN]);
Route::get('guide/{page?}', [HelpController::class, 'guide'])->middleware('can:sunrice.guide.view')->name('guide.show')->where('page', HelpPages::PAGE_PATTERN);
