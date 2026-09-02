<?php

use Azuriom\Plugin\Ronove\Controllers\LanguageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LanguageController::class, 'index'])->name('index');
Route::post('/locale', [LanguageController::class, 'update'])->name('locale.update');
