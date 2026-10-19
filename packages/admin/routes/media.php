<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Sunrice\Admin\Http\Controllers\MediaController;
use Sunrice\Admin\Http\Controllers\MediaFolderController;

/*
 * The Media page, when the panel has it (Panel::mediaLibrary()). Each
 * action checks its "sunrice.media.*" permission.
 */

Route::prefix('media')->name('media.')->group(function (): void {
    Route::get('/', [MediaController::class, 'index'])->name('index');
    Route::post('/', [MediaController::class, 'store'])->name('store');
    Route::post('bulk', [MediaController::class, 'bulk'])->name('bulk');
    Route::get('picker', [MediaController::class, 'picker'])->name('picker');

    Route::post('folders', [MediaFolderController::class, 'store'])->name('folders.store');
    Route::put('folders/{folder}', [MediaFolderController::class, 'update'])->name('folders.update')->whereNumber('folder');
    Route::delete('folders/{folder}', [MediaFolderController::class, 'destroy'])->name('folders.destroy')->whereNumber('folder');

    Route::put('{media}', [MediaController::class, 'update'])->name('update')->whereNumber('media');
    Route::post('{media}/replace', [MediaController::class, 'replace'])->name('replace')->whereNumber('media');
    Route::delete('{media}', [MediaController::class, 'destroy'])->name('destroy')->whereNumber('media');
    Route::post('{media}/restore', [MediaController::class, 'restore'])->name('restore')->whereNumber('media');
    Route::delete('{media}/force', [MediaController::class, 'forceDelete'])->name('force-delete')->whereNumber('media');
});
