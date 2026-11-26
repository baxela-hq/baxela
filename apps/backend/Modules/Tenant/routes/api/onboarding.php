<?php

use Illuminate\Support\Facades\Route;
use Modules\Tenant\Http\Controllers\User\Onboarding\ProvisionTenantController;

/*
 * Self-service onboarding (multi-tenancy §6.5 Category 3, §3.1): the
 * tenant-neutral `onboarding` audience. Tenant resolution is skipped —
 * there is no store yet — and the context stays INACTIVE; the route needs
 * only an authenticated global user, never an existing tenant.
 *
 * Mounted by routes/api.php under its own prefix (`v1/onboarding`), not
 * the module's: its canonical URL is POST /api/v1/onboarding/tenants.
 */
Route::middleware(['auth:sanctum', 'throttle:tenant-onboarding'])->group(function () {
    Route::post('/tenants', ProvisionTenantController::class)->name('tenants.store');
});
