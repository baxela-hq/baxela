<?php

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Schemas\Module;

// use Modules\Catalog\Http\Controllers\CatalogController;
//
// Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
//    Route::apiResource('catalogs', CatalogController::class)->names('catalog');
// });

Route::prefix('v1/'.Module::ROUTE_PREFIX)->name(Module::ROUTE_PREFIX.'.')->group(function () {
    require __DIR__.'/api/public.php';
    require __DIR__.'/api/user.php';
    require __DIR__.'/api/admin.php';
});
