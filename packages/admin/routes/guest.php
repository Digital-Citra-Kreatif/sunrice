<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Sunrice\Admin\Http\Controllers\Auth\LoginController;
use Sunrice\Admin\Http\Controllers\Auth\PasswordResetController;
use Sunrice\Admin\Http\Controllers\Auth\TwoFactorController;

/*
 * The panel's routes for guests: login (with its emailed code) and password reset. Loaded by
 * PanelRoutes under the panel's path, with names "sunrice.admin.*".
 */

Route::get('login', [LoginController::class, 'create'])->name('login');
Route::post('login', [LoginController::class, 'store'])->name('login.store');
Route::get('login/code', [TwoFactorController::class, 'create'])->name('login.code');
Route::post('login/code', [TwoFactorController::class, 'store'])->middleware('throttle:sunrice-login-code')->name('login.code.verify');
Route::post('login/code/resend', [TwoFactorController::class, 'resend'])->middleware('throttle:sunrice-login-code')->name('login.code.resend');
Route::get('forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
Route::post('forgot-password', [PasswordResetController::class, 'store'])->middleware('throttle:sunrice-password')->name('password.email');
Route::get('reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
Route::post('reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:sunrice-password')->name('password.update');
