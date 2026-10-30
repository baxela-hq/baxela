<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Admin\Country\ListCountryController;
use Modules\Core\Http\Controllers\Admin\Currency\CreateCurrencyController;
use Modules\Core\Http\Controllers\Admin\Currency\ListCurrencyController;
use Modules\Core\Http\Controllers\Admin\Currency\UpdateCurrencyController;
use Modules\Core\Http\Controllers\Admin\Language\ListLanguageController;
use Modules\Core\Http\Controllers\Admin\Language\UpdateLanguageController;
use Modules\Core\Http\Controllers\Admin\Timezone\ListTimezoneController;
use Modules\Core\Http\Middleware\PermissionMiddleware;

Route::middleware(['auth:sanctum', PermissionMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/languages', ListLanguageController::class)->name('languages.list');
    Route::get('/timezones', ListTimezoneController::class)->name('timezones.list');
    Route::patch('/languages/{id}', UpdateLanguageController::class)->name('languages.update');
    Route::get('/currencies', ListCurrencyController::class)->name('currencies.list');
    Route::post('/currencies', CreateCurrencyController::class)->name('currencies.create');
    Route::patch('/currencies/{id}', UpdateCurrencyController::class)->name('currencies.update');
    Route::get('/countries', ListCountryController::class)->name('countries.list');
});
