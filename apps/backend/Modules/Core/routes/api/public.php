<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Public\Country\ListCountryController;

Route::prefix('public')->name('public.')->group(function () {
    Route::get('/countries', ListCountryController::class)->name('countries.list');
});
