<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\User\Account\DestroyAllSessionsController;
use Modules\Auth\Http\Controllers\User\Account\DestroySessionController;
use Modules\Auth\Http\Controllers\User\Account\ListSessionsController;
use Modules\Auth\Http\Controllers\User\Account\MeController;
use Modules\Auth\Http\Controllers\User\Account\SignOutController;
use Modules\Auth\Schemas\RouteSchema;

Route::middleware('auth:sanctum')->prefix('user')->name('user.')->group(function () {

    Route::prefix(RouteSchema::ACCOUNT_PREFIX)->name(RouteSchema::ACCOUNT_PREFIX.'.')->group(function () {
        Route::get(RouteSchema::ME, MeController::class)->name('me');

        Route::post(RouteSchema::SIGN_OUT, SignOutController::class)->name('sign-out');

        Route::get(RouteSchema::SESSIONS, ListSessionsController::class)->name('sessions.list');
        Route::delete(RouteSchema::SESSIONS, DestroyAllSessionsController::class)->name('sessions.destroy-all');
        Route::delete(RouteSchema::SESSION, DestroySessionController::class)->name('sessions.destroy');
    });
});
