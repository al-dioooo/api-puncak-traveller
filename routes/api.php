<?php

use App\Http\Controllers\Api\V1\AdminDashboardController;
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
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\MidtransNotificationController;
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
            Route::post('register', [AuthController::class, 'register'])->name('register');
            Route::post('login', [AuthController::class, 'login'])->name('login');
            Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
            Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
            Route::post('google/exchange', [AuthController::class, 'exchangeGoogleCode'])->name('google.exchange');
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
        Route::post('payments/midtrans/notification', MidtransNotificationController::class)->name('payments.midtrans.notification');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('me', [MeController::class, 'show'])->name('me');
            Route::patch('me', [MeController::class, 'update'])->name('me.update');
            Route::get('me/saved-events', [SavedEventController::class, 'index'])->name('me.saved-events.index');
            Route::post('me/saved-events', [SavedEventController::class, 'store'])->name('me.saved-events.store');
            Route::delete('me/saved-events/{eventSlug}', [SavedEventController::class, 'destroy'])->name('me.saved-events.destroy');
            Route::apiResource('bookings', BookingController::class)->only(['index', 'show']);
            Route::post('bookings', [BookingController::class, 'store'])->name('bookings.store');
            Route::post('bookings/{booking:reference}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
            Route::post('bookings/{booking:reference}/payment-status/sync', [BookingController::class, 'syncPaymentStatus'])->name('bookings.payment-status.sync');
            Route::post('bookings/{booking:reference}/refund', [BookingController::class, 'refund'])
                ->middleware('admin')
                ->name('bookings.refund');
        });

        Route::middleware(['auth:sanctum', 'admin'])->group(function (): void {
            Route::get('admin/dashboard', AdminDashboardController::class)->name('admin.dashboard');

            Route::apiResource('members', MemberController::class);

            Route::post('communities', [CommunityController::class, 'store'])->name('communities.store');
            Route::match(['put', 'patch'], 'communities/{community:slug}', [CommunityController::class, 'update'])->name('communities.update');
            Route::delete('communities/{community:slug}', [CommunityController::class, 'destroy'])->name('communities.destroy');

            Route::post('places', [PlaceController::class, 'store'])->name('places.store');
            Route::match(['put', 'patch'], 'places/{place}', [PlaceController::class, 'update'])->name('places.update');
            Route::delete('places/{place}', [PlaceController::class, 'destroy'])->name('places.destroy');

            Route::post('events', [EventController::class, 'store'])->name('events.store');
            Route::match(['put', 'patch'], 'events/{event:slug}', [EventController::class, 'update'])->name('events.update');
            Route::delete('events/{event:slug}', [EventController::class, 'destroy'])->name('events.destroy');

            Route::post('galleries', [GalleryController::class, 'store'])->name('galleries.store');
            Route::match(['put', 'patch'], 'galleries/{gallery}', [GalleryController::class, 'update'])->name('galleries.update');
            Route::get('galleries/{gallery}/download', [GalleryController::class, 'download'])->name('galleries.download');
            Route::delete('galleries/{gallery}', [GalleryController::class, 'destroy'])->name('galleries.destroy');
            Route::post('galleries/bulk-delete', [GalleryController::class, 'bulkDestroy'])->name('galleries.bulk-destroy');

            Route::post('bookings/{booking:reference}/resend-receipt', [BookingController::class, 'resendReceipt'])->name('bookings.resend-receipt');
            Route::get('bookings/{booking:reference}/ticket', [BookingController::class, 'ticket'])->name('bookings.ticket');
            Route::patch('bookings/{booking:reference}/payment-status', [BookingController::class, 'updatePaymentStatus'])->name('bookings.payment-status.update');
        });
    });
