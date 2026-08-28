<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Schemas\Module;

Route::prefix('v1/'.Module::ROUTE_PREFIX)->name(Module::ROUTE_PREFIX.'.')->group(function () {
    require __DIR__.'/api/admin.php';
});
