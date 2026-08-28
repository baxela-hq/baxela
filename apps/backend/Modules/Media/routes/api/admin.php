<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\AdminMiddleware;
use Modules\Media\Http\Controllers\Admin\Folder\CreateFolderController;
use Modules\Media\Http\Controllers\Admin\Folder\DeleteFolderController;
use Modules\Media\Http\Controllers\Admin\Folder\ListFolderController;
use Modules\Media\Http\Controllers\Admin\Folder\UpdateFolderController;
use Modules\Media\Http\Controllers\Admin\Media\CreateMediaController;
use Modules\Media\Http\Controllers\Admin\Media\DeleteMediaController;
use Modules\Media\Http\Controllers\Admin\Media\ListMediaController;
use Modules\Media\Http\Controllers\Admin\Media\UpdateMediaController;

Route::middleware(['auth:sanctum', AdminMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/media', ListMediaController::class)->name('media.list');
    Route::post('/media', CreateMediaController::class)->name('media.create');
    Route::patch('/media/{id}', UpdateMediaController::class)->name('media.update');
    Route::delete('/media/{id}', DeleteMediaController::class)->name('media.delete');

    Route::post('/folders', CreateFolderController::class)->name('folders.create');
    Route::get('/folders', ListFolderController::class)->name('folders.list');
    Route::patch('/folders/{id}', UpdateFolderController::class)->name('folders.update');
    Route::delete('/folders/{id}', DeleteFolderController::class)->name('folders.delete');
});
