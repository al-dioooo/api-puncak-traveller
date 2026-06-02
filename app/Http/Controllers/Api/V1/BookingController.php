<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateBooking;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return BookingResource::collection(
            Booking::query()
                ->whereBelongsTo($request->user())
                ->with(['event', 'items.ticketType'])
                ->latest()
                ->paginate(15)
        );
    }

    public function store(StoreBookingRequest $request, Event $event, CreateBooking $createBooking): BookingResource
    {
        $booking = $createBooking->execute(
            $request->user(),
            $event,
            $request->bookingItems(),
            $request->idempotencyKey()
        );

        return new BookingResource($booking);
    }

    public function show(Request $request, Booking $booking): BookingResource
    {
        abort_unless($booking->user()->is($request->user()), 404);

        return new BookingResource($booking->load(['event', 'items.ticketType']));
    }
}
