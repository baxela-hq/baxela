<?php

use Illuminate\Support\Facades\Route;
use Modules\Content\Http\Controllers\Public\Page\ListPageController;
use Modules\Content\Http\Controllers\Public\Page\ShowPageController;
use Modules\Content\Http\Controllers\Public\Post\ListPostController;
use Modules\Content\Http\Controllers\Public\Post\ShowPostController;
use Modules\Content\Http\Controllers\Public\PostCategory\ListPostCategoryController;
use Modules\Content\Http\Controllers\Public\PostCategory\ShowPostCategoryController;
use Modules\Content\Http\Controllers\Public\PostComment\ListPostCommentsController;

Route::prefix('public')->name('public.')->group(function () {
    Route::get('/pages', ListPageController::class)->name('pages.list');
    Route::get('/pages/{slug}', ShowPageController::class)->name('pages.show');

    Route::get('/posts', ListPostController::class)->name('posts.list');
    Route::get('/posts/{idOrSlug}', ShowPostController::class)->name('posts.show');
    Route::get('/posts/{id}/comments', ListPostCommentsController::class)->name('post-comments.list');

    Route::get('/post-categories', ListPostCategoryController::class)->name('post-categories.list');
    Route::get('/post-categories/{idOrSlug}', ShowPostCategoryController::class)->name('post-categories.show');
});
