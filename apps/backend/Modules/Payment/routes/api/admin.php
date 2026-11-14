<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\PermissionMiddleware;
use Modules\Payment\Http\Controllers\Admin\Data\ExportPaymentDataController;
use Modules\Payment\Http\Controllers\Admin\Data\ImportPaymentDataController;
use Modules\Payment\Http\Controllers\Admin\Data\ListPaymentDataImportsController;
use Modules\Payment\Http\Controllers\Admin\Data\PreviewPaymentDataImportController;
use Modules\Payment\Http\Controllers\Admin\DriverCredential\DeleteDriverCredentialController;
use Modules\Payment\Http\Controllers\Admin\DriverCredential\ListDriverCredentialsController;
use Modules\Payment\Http\Controllers\Admin\DriverCredential\UpdateDriverCredentialsController;
use Modules\Payment\Http\Controllers\Admin\Payment\ListPaymentController;
use Modules\Payment\Http\Controllers\Admin\Payment\ListPaymentMethodController;
use Modules\Payment\Http\Controllers\Admin\Payment\UpdatePaymentController;
use Modules\Payment\Http\Controllers\Admin\Payment\UpdatePaymentMethodController;
use Modules\Payment\Http\Controllers\Admin\PaymentRefund\CreatePaymentRefundController;
use Modules\Payment\Http\Controllers\Admin\PaymentRefund\ListPaymentRefundController;
use Modules\Payment\Http\Controllers\Admin\PaymentRefund\ResolvePaymentRefundController;

Route::middleware(['auth:sanctum', PermissionMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/payments', ListPaymentController::class)->name('payments.list');
    Route::patch('/payments/{id}', UpdatePaymentController::class)->name('payments.update');

    Route::get('/payments/{id}/refunds', ListPaymentRefundController::class)->name('payments.refunds.list');
    Route::post('/payments/{id}/refunds', CreatePaymentRefundController::class)->name('payments.refunds.create');
    Route::patch('/refunds/{id}/resolve', ResolvePaymentRefundController::class)->name('refunds.resolve');

    Route::get('/methods', ListPaymentMethodController::class)->name('methods.list');
    Route::patch('/methods/{id}', UpdatePaymentMethodController::class)->name('methods.update');
    // Driver credential admin surface (marketplace P5.2): states only —
    // values never leave the server. Route names derive the permissions
    // payment.admin.credentials.{list,update,delete} verbatim.
    Route::get('/credentials', ListDriverCredentialsController::class)->name('credentials.list');
    Route::patch('/credentials/{driver}', UpdateDriverCredentialsController::class)->name('credentials.update');
    Route::delete('/credentials/{driver}/{key}', DeleteDriverCredentialController::class)->name('credentials.delete');
    // The module's own data-transfer endpoints (export-only surface; the
    // central transfer endpoints remain available).
    Route::get('/data/export', ExportPaymentDataController::class)->name('data.export');
    Route::post('/data/import/preview', PreviewPaymentDataImportController::class)->name('data.import.preview');
    Route::post('/data/import', ImportPaymentDataController::class)->name('data.import');
    Route::get('/data/imports', ListPaymentDataImportsController::class)->name('data.imports.list');
});
