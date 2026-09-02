<?php

use Azuriom\Plugin\Ronove\Controllers\Admin\LanguageController;
use Azuriom\Plugin\Ronove\Controllers\Admin\TranslationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/ronove/languages')->name('index');
Route::get('/languages', [LanguageController::class, 'index'])
    ->name('languages.index')->middleware('can:ronove.settings');
Route::post('/languages', [LanguageController::class, 'update'])
    ->name('languages.update')->middleware('can:ronove.settings');
Route::post('/languages/fallbacks', [LanguageController::class, 'updateFallbacks'])
    ->name('languages.fallbacks.update')->middleware('can:ronove.settings');
Route::prefix('/translations')->name('translations.')->middleware('can:ronove.translations')->group(function () {
    Route::get('/', [TranslationController::class, 'index'])->name('index');
    Route::get('/integration/{integration}', [TranslationController::class, 'integration'])
        ->name('integration')->where('integration', '[a-z0-9._-]+');
    Route::get('/resource/{type}/{key}', [TranslationController::class, 'edit'])->name('edit')
        ->where('type', '[a-z0-9._-]+');
    Route::put('/resource/{type}/{key}/preview', [TranslationController::class, 'preview'])->name('preview')
        ->where('type', '[a-z0-9._-]+');
    Route::put('/resource/{type}/{key}', [TranslationController::class, 'update'])->name('update')
        ->where('type', '[a-z0-9._-]+');
    Route::delete('/resource/{type}/{key}/{locale}', [TranslationController::class, 'destroy'])->name('destroy')
        ->where('type', '[a-z0-9._-]+');
});
