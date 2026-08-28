<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\AdminMiddleware;
use Modules\Setting\Http\Controllers\Admin\Setting\ListSettingController;
use Modules\Setting\Http\Controllers\Admin\Setting\UpdateSettingController;

Route::middleware(['auth:sanctum', AdminMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/settings', ListSettingController::class)->name('settings.list');
    Route::patch('/settings', UpdateSettingController::class)->name('settings.update');
});
