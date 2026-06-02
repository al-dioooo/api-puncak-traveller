<?php

use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\CommunityController;
use App\Http\Controllers\Api\V1\ContactMessageController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\GalleryController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\LandingController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PlaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->as('api.v1.')
    ->group(function (): void {
        Route::get('health', HealthController::class)->name('health');
        Route::get('landing', LandingController::class)->name('landing');
        Route::apiResource('communities', CommunityController::class)->only(['index', 'show'])->scoped([
            'community' => 'slug',
        ]);
        Route::apiResource('events', EventController::class)->only(['index', 'show'])->scoped([
            'event' => 'slug',
        ]);
        Route::apiResource('places', PlaceController::class)->only(['index', 'show']);
        Route::apiResource('galleries', GalleryController::class)->only(['index', 'show']);
        Route::post('contact', ContactMessageController::class)->name('contact.store');

        Route::middleware('auth')->group(function (): void {
            Route::get('me', MeController::class)->name('me');
            Route::apiResource('bookings', BookingController::class)->only(['index', 'show']);
            Route::post('events/{event:slug}/bookings', [BookingController::class, 'store'])->name('events.bookings.store');
        });
    });
