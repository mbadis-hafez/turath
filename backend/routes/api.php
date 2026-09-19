<?php

use App\Http\Controllers\Api\V1\ActivityFeedController;
use App\Http\Controllers\Api\V1\ArtistDestroyController;
use App\Http\Controllers\Api\V1\ArtistIndexController;
use App\Http\Controllers\Api\V1\ArtistRestoreController;
use App\Http\Controllers\Api\V1\ArtistShowController;
use App\Http\Controllers\Api\V1\ArtistStoreController;
use App\Http\Controllers\Api\V1\ArtistUnverifyController;
use App\Http\Controllers\Api\V1\ArtistUpdateController;
use App\Http\Controllers\Api\V1\ArtistVariantDestroyController;
use App\Http\Controllers\Api\V1\ArtistVariantStoreController;
use App\Http\Controllers\Api\V1\ArtistVariantUpdateController;
use App\Http\Controllers\Api\V1\ArtistVerifyController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\UserController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\SubjectActivityController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', HealthController::class);

    Route::post('auth/login', LoginController::class)->middleware('throttle:login');

    Route::middleware('throttle:api')->group(function () {
        Route::get('artists', ArtistIndexController::class);
        Route::get('artists/{slug}', ArtistShowController::class)->where('slug', '[a-z0-9-]+');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/user', UserController::class);
        Route::post('auth/logout', LogoutController::class);

        Route::post('artists', ArtistStoreController::class)->middleware('can:create,App\Models\Artist');
        Route::patch('artists/{artist}', ArtistUpdateController::class)->whereNumber('artist')->middleware('can:update,artist');
        Route::delete('artists/{artist}', ArtistDestroyController::class)->whereNumber('artist')->middleware('can:delete,artist');
        Route::post('artists/{id}/restore', ArtistRestoreController::class)->whereNumber('id')->middleware('can:restore,App\Models\Artist');

        Route::post('artists/{artist}/variants', ArtistVariantStoreController::class)->whereNumber('artist')->middleware('can:update,artist');
        Route::patch('artists/{artist}/variants/{variant}', ArtistVariantUpdateController::class)
            ->whereNumber('artist')->whereNumber('variant')->middleware('can:update,artist');
        Route::delete('artists/{artist}/variants/{variant}', ArtistVariantDestroyController::class)
            ->whereNumber('artist')->whereNumber('variant')->middleware('can:update,artist');

        Route::post('artists/{artist}/verify', ArtistVerifyController::class)->whereNumber('artist')->middleware('can:verify,artist');
        Route::delete('artists/{artist}/verify', ArtistUnverifyController::class)->whereNumber('artist')->middleware('can:verify,artist');

        Route::get('activity', ActivityFeedController::class)->middleware('can:activity.view');
        Route::get('{resource}/{id}/activity', SubjectActivityController::class)
            ->whereNumber('id')
            ->middleware('can:activity.view');
    });
});
