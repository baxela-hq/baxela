<?php

use Illuminate\Support\Facades\Route;
use Modules\Tenant\Schemas\Module;

Route::prefix('v1/'.Module::ROUTE_PREFIX)->name(Module::ROUTE_PREFIX.'.')->group(function () {
    require __DIR__.'/api/admin.php';

    // Member management (multi-tenancy §6.5 Category 2) lands with Phase 3,
    // when memberships gate access.
});

// Self-service onboarding lives at /api/v1/onboarding/tenants — outside
// the module prefix — and is named `api.tenant.onboarding.tenants.store`:
// the third name segment is the tenant-neutral `onboarding` audience.
Route::prefix('v1/onboarding')->name(Module::ROUTE_PREFIX.'.onboarding.')->group(function () {
    require __DIR__.'/api/onboarding.php';
});
