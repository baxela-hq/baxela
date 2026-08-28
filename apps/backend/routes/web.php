<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['status' => 'ok']));
