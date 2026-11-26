<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// The framework's default "who am I" route. Named so tenant
// identification can classify it: it is a platform-level route (no store),
// declared in core.tenancy.platform_routes.
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum')->name('api.platform.me');
