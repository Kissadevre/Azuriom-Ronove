<?php

use Azuriom\Plugin\Ronove\Controllers\Admin\LanguageController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/ronove/languages')->name('index');
Route::get('/languages', [LanguageController::class, 'index'])
    ->name('languages.index')->middleware('can:ronove.settings');
Route::post('/languages', [LanguageController::class, 'update'])
    ->name('languages.update')->middleware('can:ronove.settings');
Route::view('/translations', 'ronove::admin.placeholder', [
    'section' => 'translations',
])->name('translations.index')->middleware('can:ronove.translations');
