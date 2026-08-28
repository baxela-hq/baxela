<?php

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\Public\Category\ListCategoryController;
use Modules\Catalog\Http\Controllers\Public\Product\ListProductController;
use Modules\Catalog\Http\Controllers\Public\Product\ShowProductController;

Route::prefix('public')->name('public.')->group(function () {
    Route::get('/products', ListProductController::class)->name('products.list');
    Route::get('/products/{id}', ShowProductController::class)->name('products.show');

    Route::get('/categories', ListCategoryController::class)->name('categories.list');
});
