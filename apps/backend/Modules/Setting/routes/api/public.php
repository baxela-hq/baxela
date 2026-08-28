<?php

use Illuminate\Support\Facades\Route;
use Modules\Setting\Http\Controllers\Public\Setting\ListSettingController;

Route::prefix('public')->name('public.')->group(function () {
    Route::get('/settings', ListSettingController::class)->name('settings.list');
});
