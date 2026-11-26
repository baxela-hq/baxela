<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\PermissionMiddleware;
use Modules\Tenant\Http\Controllers\Admin\Tenant\ActivateTenantController;
use Modules\Tenant\Http\Controllers\Admin\Tenant\CreateTenantController;
use Modules\Tenant\Http\Controllers\Admin\Tenant\DeleteTenantController;
use Modules\Tenant\Http\Controllers\Admin\Tenant\ListTenantsController;
use Modules\Tenant\Http\Controllers\Admin\Tenant\ShowTenantController;
use Modules\Tenant\Http\Controllers\Admin\Tenant\SuspendTenantController;
use Modules\Tenant\Http\Controllers\Admin\Tenant\UpdateTenantController;
use Modules\Tenant\Http\Controllers\Admin\TenantDomain\AttachTenantDomainController;
use Modules\Tenant\Http\Controllers\Admin\TenantDomain\ListTenantDomainsController;
use Modules\Tenant\Http\Controllers\Admin\TenantDomain\ReleaseTenantDomainController;

/*
 * Platform administration (multi-tenancy §6.5, Category 1). These routes
 * carry the admin audience and the permission middleware like every admin
 * route, but no permission row is created for them
 * (core.tenancy.super_admin_only_routes): the check passes through the
 * super-admin bypass alone, so no store role can ever reach them.
 */
Route::middleware(['auth:sanctum', PermissionMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/tenants', ListTenantsController::class)->name('tenants.list');
    Route::post('/tenants', CreateTenantController::class)->name('tenants.create');
    Route::get('/tenants/{id}', ShowTenantController::class)->name('tenants.show');
    Route::patch('/tenants/{id}', UpdateTenantController::class)->name('tenants.update');
    Route::post('/tenants/{id}/suspend', SuspendTenantController::class)->name('tenants.suspend');
    Route::post('/tenants/{id}/activate', ActivateTenantController::class)->name('tenants.activate');
    Route::delete('/tenants/{id}', DeleteTenantController::class)->name('tenants.delete');

    Route::get('/tenants/{id}/domains', ListTenantDomainsController::class)->name('tenants.domains.list');
    Route::post('/tenants/{id}/domains', AttachTenantDomainController::class)->name('tenants.domains.attach');
    Route::delete('/tenants/{id}/domains/{domainId}', ReleaseTenantDomainController::class)->name('tenants.domains.release');
});
