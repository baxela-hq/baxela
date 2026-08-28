<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\Public\Auth\RequestAccountActivationAuthController;
use Modules\Auth\Http\Controllers\Public\Auth\RequestPasswordResetAuthController;
use Modules\Auth\Http\Controllers\Public\Auth\SignInAuthController;
use Modules\Auth\Http\Controllers\Public\Auth\SignUpAuthController;
use Modules\Auth\Http\Controllers\Public\Auth\VerifyAccountActivationAuthController;
use Modules\Auth\Http\Controllers\Public\Auth\VerifyPasswordResetAuthController;
use Modules\Auth\Schemas\RouteSchema;

Route::prefix('public')->name('public.')->group(function () {

    Route::prefix(RouteSchema::PREFIX)->name(RouteSchema::PREFIX.'.')->group(function () {
        Route::post(RouteSchema::SIGN_UP, SignUpAuthController::class)->name('signup');
        Route::post(RouteSchema::SIGN_IN, SignInAuthController::class)->name('signin');
        Route::post(RouteSchema::VERIFY_ACCOUNT_ACTIVATION, VerifyAccountActivationAuthController::class)->name('account-activation.verify');
        Route::post(RouteSchema::REQUEST_ACCOUNT_ACTIVATION, RequestAccountActivationAuthController::class)->name('account-activation.request');
        Route::post(RouteSchema::VERIFY_PASSWORD_RESET, VerifyPasswordResetAuthController::class)->name('reset-password.verify');
        Route::post(RouteSchema::REQUEST_PASSWORD_RESET, RequestPasswordResetAuthController::class)->name('reset-password.request');
    });
});
