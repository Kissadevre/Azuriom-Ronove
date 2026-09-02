<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/ronove/languages')->name('index');
Route::view('/languages', 'ronove::admin.placeholder', [
    'section' => 'languages',
])->name('languages.index')->middleware('can:ronove.settings');
Route::view('/translations', 'ronove::admin.placeholder', [
    'section' => 'translations',
])->name('translations.index')->middleware('can:ronove.translations');
