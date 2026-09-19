<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\UserController;
use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', HealthController::class);

    Route::post('auth/login', LoginController::class)->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/user', UserController::class);
        Route::post('auth/logout', LogoutController::class);
    });
});
