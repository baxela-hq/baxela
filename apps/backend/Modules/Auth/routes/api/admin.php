<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\Admin\Account\ShowAccountController;
use Modules\Auth\Http\Controllers\Admin\Permission\ListPermissionsController;
use Modules\Auth\Http\Controllers\Admin\Role\CreateRoleController;
use Modules\Auth\Http\Controllers\Admin\Role\DeleteRoleController;
use Modules\Auth\Http\Controllers\Admin\Role\ListRolesController;
use Modules\Auth\Http\Controllers\Admin\Role\UpdateRoleController;
use Modules\Auth\Http\Controllers\Admin\Stats\ShowStatsController;
use Modules\Auth\Http\Controllers\Admin\Token\CreateMachineTokenController;
use Modules\Auth\Http\Controllers\Admin\Token\ListMachineTokensController;
use Modules\Auth\Http\Controllers\Admin\Token\RevokeMachineTokenController;
use Modules\Auth\Http\Controllers\Admin\User\CreateUserController;
use Modules\Auth\Http\Controllers\Admin\User\DeleteUserController;
use Modules\Auth\Http\Controllers\Admin\User\ListUserController;
use Modules\Auth\Http\Controllers\Admin\User\ListUserSessionsController;
use Modules\Auth\Http\Controllers\Admin\User\RevokeAllUserSessionsController;
use Modules\Auth\Http\Controllers\Admin\User\RevokeUserSessionController;
use Modules\Auth\Http\Controllers\Admin\User\ShowUserController;
use Modules\Auth\Http\Controllers\Admin\User\UpdateUserController;
use Modules\Core\Http\Middleware\PermissionMiddleware;

Route::middleware(['auth:sanctum', PermissionMiddleware::class])->prefix('admin')->name('admin.')->group(function () {

    Route::get('/stats', ShowStatsController::class)->name('stats.show');

    Route::get('/account', ShowAccountController::class)->name('account.show');

    Route::get('/permissions', ListPermissionsController::class)->name('permissions.list');

    Route::get('/roles', ListRolesController::class)->name('roles.list');
    Route::post('/roles', CreateRoleController::class)->name('roles.create');
    Route::patch('/roles/{id}', UpdateRoleController::class)->name('roles.update');
    Route::delete('/roles/{id}', DeleteRoleController::class)->name('roles.delete');

    Route::get('/users', ListUserController::class)->name('users.list');
    Route::post('/users', CreateUserController::class)->name('users.create');
    Route::delete('/users/{id}', DeleteUserController::class)->name('users.delete');
    Route::get('/users/{id}', ShowUserController::class)->name('users.show');
    Route::patch('/users/{id}', UpdateUserController::class)->name('users.update');

    // Forced session revocation (S.2): super-admins list/revoke any
    // user's sessions. Permissions derive from the route names and are
    // synced by SyncPermissionsAction.
    Route::get('/users/{id}/sessions', ListUserSessionsController::class)->name('users.sessions.list');
    Route::delete('/users/{id}/sessions', RevokeAllUserSessionsController::class)->name('users.sessions.destroy-all');
    Route::delete('/users/{id}/sessions/{tokenId}', RevokeUserSessionController::class)->name('users.sessions.destroy');

    // Machine tokens (gap-C2 D14): mint/list/revoke for S2S integrations.
    Route::get('/machine-tokens', ListMachineTokensController::class)->name('machine-tokens.list');
    Route::post('/machine-tokens', CreateMachineTokenController::class)->name('machine-tokens.create');
    Route::delete('/machine-tokens/{id}', RevokeMachineTokenController::class)->name('machine-tokens.revoke');

});
