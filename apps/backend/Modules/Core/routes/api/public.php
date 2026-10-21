<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Public\Country\ListCountryController;
use Modules\Core\Http\Controllers\Public\Currency\ListCurrencyController;
use Modules\Core\Http\Controllers\Public\Currency\ShowDefaultCurrencyController;

Route::prefix('public')->name('public.')->group(function () {
    Route::get('/countries', ListCountryController::class)->name('countries.list');
    Route::get('/currency', ShowDefaultCurrencyController::class)->name('currencies.default');
    Route::get('/currencies', ListCurrencyController::class)->name('currencies.list');
});
