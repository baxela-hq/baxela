<?php

use Illuminate\Support\Facades\Route;
use Modules\Content\Http\Controllers\User\PostComment\CreatePostCommentController;

Route::middleware('auth:sanctum')->prefix('user')->name('user.')->group(function () {
    Route::post('/posts/{id}/comments', CreatePostCommentController::class)->name('post-comments.create');
});
