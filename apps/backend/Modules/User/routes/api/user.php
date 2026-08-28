<?php

use Illuminate\Support\Facades\Route;
use Modules\User\Http\Controllers\User\Address\CreateAddressController;
use Modules\User\Http\Controllers\User\Address\DeleteAddressController;
use Modules\User\Http\Controllers\User\Address\ListAddressController;
use Modules\User\Http\Controllers\User\Address\ShowAddressController;
use Modules\User\Http\Controllers\User\Address\UpdateAddressController;
use Modules\User\Http\Controllers\User\Profile\ShowProfileController;
use Modules\User\Http\Controllers\User\Profile\UpdateProfileController;

Route::middleware('auth:sanctum')->prefix('user')->name('user.')->group(function () {
    Route::get('/profile', ShowProfileController::class)->name('profile.show');
    Route::patch('/profile', UpdateProfileController::class)->name('profile.update');

    Route::get('/addresses', ListAddressController::class)->name('addresses.list');
    Route::post('/addresses', CreateAddressController::class)->name('addresses.create');
    Route::get('/addresses/{id}', ShowAddressController::class)->name('addresses.show');
    Route::patch('/addresses/{id}', UpdateAddressController::class)->name('addresses.update');
    Route::delete('/addresses/{id}', DeleteAddressController::class)->name('addresses.delete');
});
