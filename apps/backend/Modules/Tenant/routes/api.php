<?php

use Illuminate\Support\Facades\Route;
use Modules\Tenant\Schemas\Module;

Route::prefix('v1/'.Module::ROUTE_PREFIX)->name(Module::ROUTE_PREFIX.'.')->group(function () {
    // Phase 1 ships the module's runtime (context, gateway, seeders) only.
    // Admin CRUD, member management, and the onboarding endpoint land in
    // Phases 3 and 7 (multi-tenancy §8).
});
