<?php

use Illuminate\Support\Facades\Route;
use Modules\Content\Http\Controllers\Admin\Page\CreatePageController;
use Modules\Content\Http\Controllers\Admin\Page\DeletePageController;
use Modules\Content\Http\Controllers\Admin\Page\ListPageController;
use Modules\Content\Http\Controllers\Admin\Page\ShowPageController;
use Modules\Content\Http\Controllers\Admin\Page\UpdatePageController;
use Modules\Core\Http\Middleware\AdminMiddleware;

Route::middleware(['auth:sanctum', AdminMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/pages', ListPageController::class)->name('pages.list');
    Route::post('/pages', CreatePageController::class)->name('pages.create');
    Route::get('/pages/{id}', ShowPageController::class)->name('pages.show');
    Route::patch('/pages/{id}', UpdatePageController::class)->name('pages.update');
    Route::delete('/pages/{id}', DeletePageController::class)->name('pages.delete');
});
