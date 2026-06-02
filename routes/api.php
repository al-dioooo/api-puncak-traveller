<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\CommunityController;
use App\Http\Controllers\Api\V1\ContactMessageController;
use App\Http\Controllers\Api\V1\ContactMethodController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\GalleryController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\LandingController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PlaceController;
use App\Http\Controllers\Api\V1\SavedEventController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->as('api.v1.')
    ->group(function (): void {
        Route::get('health', HealthController::class)->name('health');
        Route::get('landing', LandingController::class)->name('landing');
        Route::middleware('web')->prefix('auth')->as('auth.')->group(function (): void {
            Route::get('google/redirect', [AuthController::class, 'redirectToGoogle'])->name('google.redirect');
            Route::get('google/callback', [AuthController::class, 'handleGoogleCallback'])->name('google.callback');
        });
        Route::prefix('auth')->as('auth.')->group(function (): void {
            Route::post('login', [AuthController::class, 'login'])->name('login');
            Route::middleware('auth:sanctum')->group(function (): void {
                Route::get('user', [AuthController::class, 'user'])->name('user');
                Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            });
        });
        Route::apiResource('communities', CommunityController::class)->only(['index', 'show'])->scoped([
            'community' => 'slug',
        ]);
        Route::apiResource('events', EventController::class)->only(['index', 'show'])->scoped([
            'event' => 'slug',
        ]);
        Route::apiResource('places', PlaceController::class)->only(['index', 'show']);
        Route::apiResource('galleries', GalleryController::class)->only(['index', 'show']);
        Route::get('contact-methods', [ContactMethodController::class, 'index'])->name('contact-methods.index');
        Route::post('contact', ContactMessageController::class)->name('contact.store');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('me', [MeController::class, 'show'])->name('me');
            Route::patch('me', [MeController::class, 'update'])->name('me.update');
            Route::get('me/saved-events', [SavedEventController::class, 'index'])->name('me.saved-events.index');
            Route::post('me/saved-events', [SavedEventController::class, 'store'])->name('me.saved-events.store');
            Route::delete('me/saved-events/{eventSlug}', [SavedEventController::class, 'destroy'])->name('me.saved-events.destroy');
            Route::apiResource('bookings', BookingController::class)->only(['index', 'show']);
            Route::post('bookings', [BookingController::class, 'store'])->name('bookings.store');
            Route::post('bookings/{booking:reference}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
        });
    });
