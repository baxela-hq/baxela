<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\Public\Auth\RequestAccountActivationAuthController;
use Modules\Auth\Http\Controllers\Public\Auth\RequestGoogleNonceAuthController;
use Modules\Auth\Http\Controllers\Public\Auth\RequestPasswordResetAuthController;
use Modules\Auth\Http\Controllers\Public\Auth\GoogleSignInAuthController;
use Modules\Auth\Http\Controllers\Public\Auth\SignInAuthController;
use Modules\Auth\Http\Controllers\Public\Auth\SignUpAuthController;
use Modules\Auth\Http\Controllers\Public\Auth\VerifyAccountActivationAuthController;
use Modules\Auth\Http\Controllers\Public\Auth\VerifyPasswordResetAuthController;
use Modules\Auth\Http\Controllers\Public\Auth\TwoFactorEnrollmentConfirmController;
use Modules\Auth\Http\Controllers\Public\Auth\TwoFactorEnrollmentSecretController;
use Modules\Auth\Http\Controllers\Public\Auth\VerifyTwoFactorController;
use Modules\Auth\Schemas\RouteSchema;

Route::prefix('public')->name('public.')->group(function () {

    Route::prefix(RouteSchema::PREFIX)->name(RouteSchema::PREFIX.'.')->group(function () {
        Route::post(RouteSchema::SIGN_UP, SignUpAuthController::class)->name('signup')->middleware('throttle:auth-sign-up');
        Route::post(RouteSchema::SIGN_IN, SignInAuthController::class)->name('signin')->middleware('throttle:auth-sign-in');
        Route::post(RouteSchema::SIGN_IN_GOOGLE, GoogleSignInAuthController::class)->name('google.signin')->middleware('throttle:auth-sign-in');
        Route::post(RouteSchema::GOOGLE_NONCE, RequestGoogleNonceAuthController::class)->name('google.nonce')->middleware('throttle:auth-sign-in');
        Route::post('2fa/verify', VerifyTwoFactorController::class)->name('2fa.verify')->middleware('throttle:auth-sign-in');
        Route::post('2fa/enrollment/secret', TwoFactorEnrollmentSecretController::class)->name('2fa.enrollment.secret')->middleware('throttle:auth-sign-in');
        Route::post('2fa/enrollment/confirm', TwoFactorEnrollmentConfirmController::class)->name('2fa.enrollment.confirm')->middleware('throttle:auth-sign-in');
        Route::post(RouteSchema::VERIFY_ACCOUNT_ACTIVATION, VerifyAccountActivationAuthController::class)->name('account-activation.verify')->middleware('throttle:auth-otp-verify');
        Route::post(RouteSchema::REQUEST_ACCOUNT_ACTIVATION, RequestAccountActivationAuthController::class)->name('account-activation.request')->middleware('throttle:auth-otp-request');
        Route::post(RouteSchema::VERIFY_PASSWORD_RESET, VerifyPasswordResetAuthController::class)->name('reset-password.verify')->middleware('throttle:auth-otp-verify');
        Route::post(RouteSchema::REQUEST_PASSWORD_RESET, RequestPasswordResetAuthController::class)->name('reset-password.request')->middleware('throttle:auth-otp-request');
    });
});
