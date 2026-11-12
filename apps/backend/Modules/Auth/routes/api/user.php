<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\User\Account\DestroyAllSessionsController;
use Modules\Auth\Http\Controllers\User\Account\DestroySessionController;
use Modules\Auth\Http\Controllers\User\Account\ListSessionsController;
use Modules\Auth\Http\Controllers\User\Account\MeController;
use Modules\Auth\Http\Controllers\User\Account\SignOutController;
use Modules\Auth\Http\Controllers\User\TwoFactor\TwoFactorConfirmController;
use Modules\Auth\Http\Controllers\User\TwoFactor\TwoFactorDisableController;
use Modules\Auth\Http\Controllers\User\TwoFactor\TwoFactorEnableController;
use Modules\Auth\Http\Controllers\User\TwoFactor\TwoFactorRecoveryCodesController;
use Modules\Auth\Http\Controllers\User\TwoFactor\TwoFactorStatusController;
use Modules\Auth\Schemas\RouteSchema;

Route::middleware('auth:sanctum')->prefix('user')->name('user.')->group(function () {

    Route::prefix(RouteSchema::ACCOUNT_PREFIX)->name(RouteSchema::ACCOUNT_PREFIX.'.')->group(function () {
        Route::get(RouteSchema::ME, MeController::class)->name('me');

        Route::post(RouteSchema::SIGN_OUT, SignOutController::class)->name('sign-out');

        Route::get(RouteSchema::SESSIONS, ListSessionsController::class)->name('sessions.list');
        Route::delete(RouteSchema::SESSIONS, DestroyAllSessionsController::class)->name('sessions.destroy-all');
        Route::delete(RouteSchema::SESSION, DestroySessionController::class)->name('sessions.destroy');
    });

    // Self-service 2FA management (S.1 D7): no PermissionMiddleware —
    // managing your own second factor is not a business permission.
    Route::prefix('2fa')->name('two-factor.')->group(function () {
        Route::get('/status', TwoFactorStatusController::class)->name('status');
        Route::post('/enable', TwoFactorEnableController::class)->name('enable');
        Route::post('/confirm', TwoFactorConfirmController::class)->name('confirm');
        Route::post('/disable', TwoFactorDisableController::class)->name('disable');
        Route::post('/recovery-codes', TwoFactorRecoveryCodesController::class)->name('recovery-codes');
    });
});
