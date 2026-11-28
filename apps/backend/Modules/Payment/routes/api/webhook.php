<?php

use Illuminate\Support\Facades\Route;
use Modules\Payment\Http\Controllers\Webhook\Payment\HandleWebhookController;

// The {tenantSlug} route parameter is the AUTHORITATIVE Stage-2
// resolution source (multi-tenancy §3.1 B2): ResolveTenant reads it,
// rejects suspended stores, and sets the context before any credential
// application — all tenant headers are ignored on this audience. The
// signature verification inside the driver then uses the resolved
// tenant's secret (§6.3), and every lookup/write lands in that store
// (row 17).
Route::middleware('throttle:webhook')->prefix('webhook')->name('webhook.')->group(function () {
    Route::post('/{tenantSlug}/{driver}', HandleWebhookController::class)->name('handle');
});
