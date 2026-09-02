<?php

use Azuriom\Plugin\Ronove\Controllers\Admin\AuditController;
use Azuriom\Plugin\Ronove\Controllers\Admin\GlossaryController;
use Azuriom\Plugin\Ronove\Controllers\Admin\LanguageController;
use Azuriom\Plugin\Ronove\Controllers\Admin\ReviewController;
use Azuriom\Plugin\Ronove\Controllers\Admin\RevisionController;
use Azuriom\Plugin\Ronove\Controllers\Admin\SettingsController;
use Azuriom\Plugin\Ronove\Controllers\Admin\TranslationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/ronove/languages')->name('index');
Route::get('/settings', [SettingsController::class, 'index'])
    ->name('settings.index')->middleware('can:ronove.settings');
Route::post('/settings', [SettingsController::class, 'update'])
    ->name('settings.update')->middleware('can:ronove.settings');
Route::get('/languages', [LanguageController::class, 'index'])
    ->name('languages.index')->middleware('can:ronove.languages');
Route::post('/languages', [LanguageController::class, 'update'])
    ->name('languages.update')->middleware('can:ronove.languages');
Route::post('/languages/fallbacks', [LanguageController::class, 'updateFallbacks'])
    ->name('languages.fallbacks.update')->middleware('can:ronove.languages');
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
    Route::post('/resource/{type}/{key}/{locale}/review/approve', [ReviewController::class, 'approve'])
        ->name('reviews.approve')->middleware('can:ronove.review')->where('type', '[a-z0-9._-]+');
    Route::post('/resource/{type}/{key}/{locale}/review/changes', [ReviewController::class, 'requestChanges'])
        ->name('reviews.changes')->middleware('can:ronove.review')->where('type', '[a-z0-9._-]+');
    Route::post('/resource/{type}/{key}/{locale}/revisions/{revision}/restore', [RevisionController::class, 'restore'])
        ->name('revisions.restore')->where('type', '[a-z0-9._-]+');
    Route::put('/resource/{type}/{key}/{locale}/note', [TranslationController::class, 'updateNote'])
        ->name('notes.update')->where('type', '[a-z0-9._-]+');
    Route::delete('/resource/{type}/{key}/{locale}/note', [TranslationController::class, 'destroyNote'])
        ->name('notes.destroy')->where('type', '[a-z0-9._-]+');
    Route::delete('/resource/{type}/{key}/{locale}', [TranslationController::class, 'destroy'])->name('destroy')
        ->where('type', '[a-z0-9._-]+');
});
Route::prefix('/glossary')->name('glossary.')->middleware('can:ronove.glossary')->group(function () {
    Route::get('/', [GlossaryController::class, 'index'])->name('index');
    Route::get('/create', [GlossaryController::class, 'create'])->name('create');
    Route::post('/', [GlossaryController::class, 'store'])->name('store');
    Route::get('/{term}/edit', [GlossaryController::class, 'edit'])->name('edit');
    Route::put('/{term}', [GlossaryController::class, 'update'])->name('update');
    Route::delete('/{term}', [GlossaryController::class, 'destroy'])->name('destroy');
});
Route::prefix('/audit')->name('audit.')->middleware('can:ronove.audit')->group(function () {
    Route::get('/', [AuditController::class, 'index'])->name('index');
    Route::post('/cleanup/{category}', [AuditController::class, 'cleanup'])
        ->name('cleanup')->where('category', '[a-z_]+');
});
