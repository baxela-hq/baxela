<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\PermissionMiddleware;
use Modules\Menu\Http\Controllers\Admin\Data\ExportMenuDataController;
use Modules\Menu\Http\Controllers\Admin\Data\ImportMenuDataController;
use Modules\Menu\Http\Controllers\Admin\Data\ListMenuDataImportsController;
use Modules\Menu\Http\Controllers\Admin\Data\PreviewMenuDataImportController;
use Modules\Menu\Http\Controllers\Admin\Menu\CreateMenuController;
use Modules\Menu\Http\Controllers\Admin\Menu\DeleteMenuController;
use Modules\Menu\Http\Controllers\Admin\Menu\ListMenuController;
use Modules\Menu\Http\Controllers\Admin\Menu\ShowMenuController;
use Modules\Menu\Http\Controllers\Admin\Menu\UpdateMenuController;
use Modules\Menu\Http\Controllers\Admin\MenuLink\CreateMenuLinkController;
use Modules\Menu\Http\Controllers\Admin\MenuLink\DeleteMenuLinkController;
use Modules\Menu\Http\Controllers\Admin\MenuLink\ListMenuLinkController;
use Modules\Menu\Http\Controllers\Admin\MenuLink\ShowMenuLinkController;
use Modules\Menu\Http\Controllers\Admin\MenuLink\UpdateMenuLinkController;

Route::middleware(['auth:sanctum', PermissionMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/menus', ListMenuController::class)->name('menus.list');
    Route::post('/menus', CreateMenuController::class)->name('menus.create');
    Route::get('/menus/{id}', ShowMenuController::class)->name('menus.show');
    Route::patch('/menus/{id}', UpdateMenuController::class)->name('menus.update');
    Route::delete('/menus/{id}', DeleteMenuController::class)->name('menus.delete');

    Route::get('/menus/{id}/links', ListMenuLinkController::class)->name('menu-links.list');
    Route::post('/menus/{id}/links', CreateMenuLinkController::class)->name('menu-links.create');
    Route::get('/menus/{id}/links/{linkId}', ShowMenuLinkController::class)->name('menu-links.show');
    Route::patch('/menus/{id}/links/{linkId}', UpdateMenuLinkController::class)->name('menu-links.update');
    Route::delete('/menus/{id}/links/{linkId}', DeleteMenuLinkController::class)->name('menu-links.delete');
    // The module's own data-transfer endpoints (per-module surface from
    // the transfer standard's phase plan; the central transfer endpoints
    // remain available).
    Route::get('/data/export', ExportMenuDataController::class)->name('data.export');
    Route::post('/data/import/preview', PreviewMenuDataImportController::class)->name('data.import.preview');
    Route::post('/data/import', ImportMenuDataController::class)->name('data.import');
    Route::get('/data/imports', ListMenuDataImportsController::class)->name('data.imports.list');
});
