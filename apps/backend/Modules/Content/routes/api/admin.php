<?php

use Illuminate\Support\Facades\Route;
use Modules\Content\Http\Controllers\Admin\Featured\ListFeaturedController;
use Modules\Content\Http\Controllers\Admin\Featured\UpdateFeaturedController;
use Modules\Content\Http\Controllers\Admin\Page\CreatePageController;
use Modules\Content\Http\Controllers\Admin\Page\DeletePageController;
use Modules\Content\Http\Controllers\Admin\Page\ListPageController;
use Modules\Content\Http\Controllers\Admin\Page\ShowPageController;
use Modules\Content\Http\Controllers\Admin\Page\UpdatePageController;
use Modules\Content\Http\Controllers\Admin\Post\CreatePostController;
use Modules\Content\Http\Controllers\Admin\Post\DeletePostController;
use Modules\Content\Http\Controllers\Admin\Post\ListPostController;
use Modules\Content\Http\Controllers\Admin\Post\ShowPostController;
use Modules\Content\Http\Controllers\Admin\Post\UpdatePostController;
use Modules\Content\Http\Controllers\Admin\PostCategory\CreatePostCategoryController;
use Modules\Content\Http\Controllers\Admin\PostCategory\DeletePostCategoryController;
use Modules\Content\Http\Controllers\Admin\PostCategory\ListPostCategoryController;
use Modules\Content\Http\Controllers\Admin\PostCategory\ShowPostCategoryController;
use Modules\Content\Http\Controllers\Admin\PostCategory\UpdatePostCategoryController;
use Modules\Content\Http\Controllers\Admin\PostComment\CreatePostCommentController;
use Modules\Content\Http\Controllers\Admin\PostComment\DeletePostCommentController;
use Modules\Content\Http\Controllers\Admin\PostComment\ListPostCommentController;
use Modules\Content\Http\Controllers\Admin\PostComment\ShowPostCommentController;
use Modules\Content\Http\Controllers\Admin\PostComment\UpdatePostCommentController;
use Modules\Core\Http\Middleware\PermissionMiddleware;

Route::middleware(['auth:sanctum', PermissionMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/pages', ListPageController::class)->name('pages.list');
    Route::post('/pages', CreatePageController::class)->name('pages.create');
    Route::get('/pages/{id}', ShowPageController::class)->name('pages.show');
    Route::patch('/pages/{id}', UpdatePageController::class)->name('pages.update');
    Route::delete('/pages/{id}', DeletePageController::class)->name('pages.delete');

    Route::get('/posts', ListPostController::class)->name('posts.list');
    Route::post('/posts', CreatePostController::class)->name('posts.create');
    Route::get('/posts/{id}', ShowPostController::class)->name('posts.show');
    Route::patch('/posts/{id}', UpdatePostController::class)->name('posts.update');
    Route::delete('/posts/{id}', DeletePostController::class)->name('posts.delete');

    Route::get('/featured', ListFeaturedController::class)->name('featured.list');
    Route::put('/featured', UpdateFeaturedController::class)->name('featured.update');

    Route::get('/post-categories', ListPostCategoryController::class)->name('post-categories.list');
    Route::post('/post-categories', CreatePostCategoryController::class)->name('post-categories.create');
    Route::get('/post-categories/{id}', ShowPostCategoryController::class)->name('post-categories.show');
    Route::patch('/post-categories/{id}', UpdatePostCategoryController::class)->name('post-categories.update');
    Route::delete('/post-categories/{id}', DeletePostCategoryController::class)->name('post-categories.delete');

    Route::get('/post-comments', ListPostCommentController::class)->name('post-comments.list');
    Route::post('/post-comments', CreatePostCommentController::class)->name('post-comments.create');
    Route::get('/post-comments/{id}', ShowPostCommentController::class)->name('post-comments.show');
    Route::patch('/post-comments/{id}', UpdatePostCommentController::class)->name('post-comments.update');
    Route::delete('/post-comments/{id}', DeletePostCommentController::class)->name('post-comments.delete');
});
