<?php

use Illuminate\Support\Facades\Route;
use Modules\Webhooks\Http\Controllers\Machine\ListMachineOrdersController;
use Modules\Webhooks\Http\Controllers\Machine\ListMachineProductsController;
use Modules\Webhooks\Http\Controllers\Machine\ShowMachineOrderController;
use Modules\Webhooks\Http\Controllers\Machine\ShowMachineProductController;
use Modules\Webhooks\Http\Middleware\EnsureMachineToken;

/**
 * Read-only machine surface (gap-C2 D14): bearer-token-only (EnsureMachineToken),
 * per-token rate limit, and an explicit ability on every route.
 * Ownership is enforced in the controllers' queries (owning user's data
 * only) — machine tokens read the merchant's own store data.
 */
Route::middleware(['auth:sanctum', EnsureMachineToken::class, 'throttle:machine'])
    ->prefix('machine')->name('machine.')->group(function () {
        Route::get('/orders', ListMachineOrdersController::class)
            ->middleware('abilities:orders.read')->name('orders.list');
        Route::get('/orders/{id}', ShowMachineOrderController::class)
            ->middleware('abilities:orders.read')->name('orders.show');
        Route::get('/products', ListMachineProductsController::class)
            ->middleware('abilities:products.read')->name('products.list');
        Route::get('/products/{id}', ShowMachineProductController::class)
            ->middleware('abilities:products.read')->name('products.show');
    });
