<?php

use Illuminate\Support\Facades\Route;
use Modules\Content\Http\Controllers\Public\Page\ListPageController;
use Modules\Content\Http\Controllers\Public\Page\ShowPageController;

Route::prefix('public')->name('public.')->group(function () {
    Route::get('/pages', ListPageController::class)->name('pages.list');
    Route::get('/pages/{slug}', ShowPageController::class)->name('pages.show');
});
