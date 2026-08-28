<?php

use Illuminate\Support\Facades\Route;
use Modules\Notification\Http\Controllers\NotificationController;

Route::get('test', [NotificationController::class, 'index']);
