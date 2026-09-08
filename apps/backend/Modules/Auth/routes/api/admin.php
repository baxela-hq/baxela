<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\Admin\Account\ShowAccountController;
use Modules\Auth\Http\Controllers\Admin\Role\ListRolesController;
use Modules\Auth\Http\Controllers\Admin\User\CreateUserController;
use Modules\Auth\Http\Controllers\Admin\User\DeleteUserController;
use Modules\Auth\Http\Controllers\Admin\User\ListUserController;
use Modules\Auth\Http\Controllers\Admin\User\ShowUserController;
use Modules\Auth\Http\Controllers\Admin\User\UpdateUserController;
use Modules\Core\Http\Middleware\PermissionMiddleware;

Route::middleware(['auth:sanctum', PermissionMiddleware::class])->prefix('admin')->name('admin.')->group(function () {

    Route::get('/account', ShowAccountController::class)->name('account.show');
    Route::get('/roles', ListRolesController::class)->name('roles.list');

    Route::get('/users', ListUserController::class)->name('users.list');
    Route::post('/users', CreateUserController::class)->name('users.create');
    Route::delete('/users/{id}', DeleteUserController::class)->name('users.delete');
    Route::get('/users/{id}', ShowUserController::class)->name('users.show');
    Route::patch('/users/{id}', UpdateUserController::class)->name('users.update');

});
