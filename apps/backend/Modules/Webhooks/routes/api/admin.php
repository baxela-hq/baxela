<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\PermissionMiddleware;
use Modules\Webhooks\Http\Controllers\Admin\WebhookDelivery\ListWebhookDeliveryController;
use Modules\Webhooks\Http\Controllers\Admin\WebhookDelivery\RetryWebhookDeliveryController;
use Modules\Webhooks\Http\Controllers\Admin\WebhookDelivery\ShowWebhookDeliveryController;
use Modules\Webhooks\Http\Controllers\Admin\WebhookEndpoint\CreateWebhookEndpointController;
use Modules\Webhooks\Http\Controllers\Admin\WebhookEndpoint\DeleteWebhookEndpointController;
use Modules\Webhooks\Http\Controllers\Admin\WebhookEndpoint\ListWebhookEndpointController;
use Modules\Webhooks\Http\Controllers\Admin\WebhookEndpoint\RegenerateWebhookEndpointSecretController;
use Modules\Webhooks\Http\Controllers\Admin\WebhookEndpoint\ShowWebhookEndpointController;
use Modules\Webhooks\Http\Controllers\Admin\WebhookEndpoint\TestWebhookEndpointController;
use Modules\Webhooks\Http\Controllers\Admin\WebhookEndpoint\UpdateWebhookEndpointController;

Route::middleware(['auth:sanctum', PermissionMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/endpoints', ListWebhookEndpointController::class)->name('endpoints.list');
    Route::post('/endpoints', CreateWebhookEndpointController::class)->name('endpoints.create');
    Route::get('/endpoints/{id}', ShowWebhookEndpointController::class)->name('endpoints.show');
    Route::patch('/endpoints/{id}', UpdateWebhookEndpointController::class)->name('endpoints.update');
    Route::delete('/endpoints/{id}', DeleteWebhookEndpointController::class)->name('endpoints.delete');
    Route::post('/endpoints/{id}/regenerate-secret', RegenerateWebhookEndpointSecretController::class)->name('endpoints.regenerate-secret');
    Route::post('/endpoints/{id}/test', TestWebhookEndpointController::class)->name('endpoints.test');

    Route::get('/deliveries', ListWebhookDeliveryController::class)->name('deliveries.list');
    Route::get('/deliveries/{id}', ShowWebhookDeliveryController::class)->name('deliveries.show');
    Route::post('/deliveries/{id}/retry', RetryWebhookDeliveryController::class)->name('deliveries.retry');
});
