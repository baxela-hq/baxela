<?php

use Illuminate\Support\Facades\Route;
use Modules\Menu\Http\Controllers\Public\Menu\ListMenuController;
use Modules\Menu\Http\Controllers\Public\Menu\ShowMenuController;

Route::prefix('public')->name('public.')->group(function () {
    Route::get('/menus', ListMenuController::class)->name('menus.list');
    Route::get('/menus/{location}', ShowMenuController::class)->name('menus.show');
});
