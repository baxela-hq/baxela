<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\User\Account\MeController;
use Modules\Auth\Schemas\RouteSchema;

Route::middleware('auth:sanctum')->prefix('user')->name('user.')->group(function () {

    Route::prefix(RouteSchema::ACCOUNT_PREFIX)->name(RouteSchema::ACCOUNT_PREFIX.'.')->group(function () {
        Route::get(RouteSchema::ME, MeController::class)->name('me');
    });
});
