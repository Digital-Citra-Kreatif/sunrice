<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Sunrice\Admin\Http\Controllers\AiAccessController;

/*
 * The AI access page, when the panel has it (Panel::aiAccess()). Needs
 * "sunrice.ai.connect": each user manages their own tokens.
 */

Route::get('ai', [AiAccessController::class, 'index'])->name('ai.index');
Route::post('ai/tokens', [AiAccessController::class, 'store'])->name('ai.tokens.store');
Route::delete('ai/tokens/{token}', [AiAccessController::class, 'destroy'])->name('ai.tokens.destroy')->whereNumber('token');
