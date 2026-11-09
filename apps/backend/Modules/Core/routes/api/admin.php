<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Admin\Country\ListCountryController;
use Modules\Core\Http\Controllers\Admin\Currency\CreateCurrencyController;
use Modules\Core\Http\Controllers\Admin\Currency\ListCurrencyController;
use Modules\Core\Http\Controllers\Admin\Currency\UpdateCurrencyController;
use Modules\Core\Http\Controllers\Admin\Data\ExportCoreDataController;
use Modules\Core\Http\Controllers\Admin\Data\ImportCoreDataController;
use Modules\Core\Http\Controllers\Admin\Data\ListCoreDataImportsController;
use Modules\Core\Http\Controllers\Admin\Data\PreviewCoreDataImportController;
use Modules\Core\Http\Controllers\Admin\Extension\ListExtensionController;
use Modules\Core\Http\Controllers\Admin\FailedJob\ForgetFailedJobController;
use Modules\Core\Http\Controllers\Admin\FailedJob\ListFailedJobsController;
use Modules\Core\Http\Controllers\Admin\FailedJob\RetryFailedJobController;
use Modules\Core\Http\Controllers\Admin\FailedJob\ShowFailedJobController;
use Modules\Core\Http\Controllers\Admin\Language\ListLanguageController;
use Modules\Core\Http\Controllers\Admin\Language\UpdateLanguageController;
use Modules\Core\Http\Controllers\Admin\Timezone\ListTimezoneController;
use Modules\Core\Http\Middleware\PermissionMiddleware;

Route::middleware(['auth:sanctum', PermissionMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/languages', ListLanguageController::class)->name('languages.list');
    Route::get('/timezones', ListTimezoneController::class)->name('timezones.list');
    Route::patch('/languages/{id}', UpdateLanguageController::class)->name('languages.update');
    Route::get('/currencies', ListCurrencyController::class)->name('currencies.list');
    Route::post('/currencies', CreateCurrencyController::class)->name('currencies.create');
    Route::patch('/currencies/{id}', UpdateCurrencyController::class)->name('currencies.update');
    Route::get('/countries', ListCountryController::class)->name('countries.list');

    // The module's own data-transfer endpoints (reference data export/import).
    Route::get('/data/export', ExportCoreDataController::class)->name('data.export');
    Route::post('/data/import/preview', PreviewCoreDataImportController::class)->name('data.import.preview');
    Route::post('/data/import', ImportCoreDataController::class)->name('data.import');
    Route::get('/data/imports', ListCoreDataImportsController::class)->name('data.imports.list');

    // Failed-jobs visibility surface (gap-B3): route names derive the
    // permissions core.admin.failed-jobs.{list,show,retry,delete} verbatim.
    Route::get('/failed-jobs', ListFailedJobsController::class)->name('failed-jobs.list');
    Route::get('/failed-jobs/{uuid}', ShowFailedJobController::class)->name('failed-jobs.show');
    Route::post('/failed-jobs/{uuid}/retry', RetryFailedJobController::class)->name('failed-jobs.retry');
    Route::delete('/failed-jobs/{uuid}', ForgetFailedJobController::class)->name('failed-jobs.delete');

    // Extension runtime surface (E.1): read-only — enabling/disabling
    // stays CLI-only until the roadmap Phase 5 admin page.
    Route::get('/extensions', ListExtensionController::class)->name('extensions.list');
});
