<?php

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\Public\Brand\ListBrandController;
use Modules\Catalog\Http\Controllers\Public\Brand\ShowBrandController;
use Modules\Catalog\Http\Controllers\Public\Category\ListCategoryController;
use Modules\Catalog\Http\Controllers\Public\Option\ListOptionController;
use Modules\Catalog\Http\Controllers\Public\Product\ListProductController;
use Modules\Catalog\Http\Controllers\Public\Product\ShowProductController;
use Modules\Catalog\Http\Controllers\Public\ProductComment\ListProductCommentsController;
use Modules\Catalog\Http\Controllers\Public\Sitemap\ListSitemapController;

Route::prefix('public')->name('public.')->group(function () {
    Route::get('/products', ListProductController::class)->name('products.list');
    Route::get('/products/{id}', ShowProductController::class)->name('products.show');
    Route::get('/products/{id}/comments', ListProductCommentsController::class)->name('product-comments.list');

    Route::get('/categories', ListCategoryController::class)->name('categories.list');
    Route::get('/brands', ListBrandController::class)->name('brands.list');
    Route::get('/brands/{id}', ShowBrandController::class)->name('brands.show');
    Route::get('/options', ListOptionController::class)->name('options.list');

    Route::get('/sitemap', ListSitemapController::class)->name('sitemap.show');
});
