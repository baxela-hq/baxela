<?php

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\User\ProductComment\CreateProductCommentController;

Route::middleware('auth:sanctum')->prefix('user')->name('user.')->group(function () {
    Route::post('/products/{id}/comments', CreateProductCommentController::class)->name('product-comments.create');
});
