<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Modules\Notification\Schemas\Module;

Route::prefix('v1/'.Module::ROUTE_PREFIX)->name(Module::ROUTE_PREFIX.'.')->group(function () {
    // Echo channel authorization. Token-based (both SPAs call it with a
    // Bearer header), so it rides the API group instead of the web
    // middleware the default Broadcast::routes() would apply.
    Broadcast::routes(['middleware' => ['auth:sanctum']]);

    require __DIR__.'/api/admin.php';
    require __DIR__.'/api/user.php';
});
