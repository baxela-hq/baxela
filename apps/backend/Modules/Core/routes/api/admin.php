<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Admin\Country\ListCountryController;
use Modules\Core\Http\Controllers\Admin\Currency\ListCurrencyController;
use Modules\Core\Http\Controllers\Admin\Language\ListLanguageController;
use Modules\Core\Http\Middleware\AdminMiddleware;

Route::middleware(['auth:sanctum', AdminMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/languages', ListLanguageController::class)->name('languages.list');
    Route::get('/currencies', ListCurrencyController::class)->name('currencies.list');
    Route::get('/countries', ListCountryController::class)->name('countries.list');
});
