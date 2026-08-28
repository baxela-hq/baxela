<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Schemas\Module;

Route::prefix('v1/'.Module::ROUTE_PREFIX)->name(Module::ROUTE_PREFIX.'.')->group(function () {
    require __DIR__.'/api/public.php';
    require __DIR__.'/api/user.php';
    require __DIR__.'/api/admin.php';
    //    require __DIR__.'/api/vendor.php';
});
