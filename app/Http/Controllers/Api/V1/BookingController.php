<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateBooking;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($request->string('status')->toString() === 'saved') {
            $savedEvents = $request->user()
                ->savedEvents()
                ->with(['event.ticketTypes'])
                ->latest()
                ->get();

            return response()->json([
                'data' => $savedEvents->map(fn ($savedEvent): array => $this->savedEventCard($savedEvent->event))->values(),
            ]);
        }

        $bookings = Booking::query()
            ->whereBelongsTo($request->user())
            ->with(['event', 'items.ticketType'])
            ->latest()
            ->get()
            ->filter(fn (Booking $booking): bool => $this->matchesStatusFilter($booking, $request->string('status')->toString()))
            ->map(fn (Booking $booking): array => $this->bookingCard($booking))
            ->values();

        return response()->json(['data' => $bookings]);
    }

    public function store(CreateBookingRequest $request, CreateBooking $createBooking): JsonResponse
    {
        $event = $request->event();

        abort_if($event === null, 404, 'Event not found.');

        $booking = $createBooking->execute(
            $request->user(),
            $event,
            $request->bookingItems(),
            $request->validated('attendees', []),
            $request->idempotencyKey() ?: null
        );

        return (new BookingResource($booking))->response()->setStatusCode(201);
    }

    public function show(Request $request, Booking $booking): BookingResource
    {
        abort_unless($booking->user()->is($request->user()), 403);

        return new BookingResource($booking->load(['event', 'items.ticketType']));
    }

    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        abort_unless($booking->user()->is($request->user()), 403);

        $booking->load(['event', 'items.ticketType']);

        if ($booking->event->starts_at->copy()->subDay()->lte(now())) {
            throw new ConflictHttpException('Bookings can only be cancelled at least 24 hours before the event starts.');
        }

        if ($booking->status === Booking::STATUS_CONFIRMED) {
            foreach ($booking->items as $item) {
                $item->ticketType()->decrement('sold', $item->quantity);
            }
        }

        $booking->update([
            'status' => Booking::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => $request->string('reason')->toString(),
        ]);

        return response()->json([
            'data' => [
                'reference' => $booking->reference,
                'status' => Booking::STATUS_CANCELLED,
            ],
        ]);
    }

    private function matchesStatusFilter(Booking $booking, string $status): bool
    {
        return match ($status) {
            'upcoming' => $booking->event?->status === 'upcoming' && $booking->status !== Booking::STATUS_CANCELLED,
            'past' => in_array($booking->event?->status, ['completed'], true) || $booking->status === Booking::STATUS_COMPLETED,
            'all', '' => true,
            default => true,
        };
    }

    private function bookingCard(Booking $booking): array
    {
        $event = $booking->event;
        $ticketCount = $booking->items->sum('quantity');
        $ticketNames = $booking->items
            ->map(fn ($item): string => $item->ticketType?->name ?? 'Ticket')
            ->map(fn (string $name): string => str($name)->before(' ')->toString())
            ->unique()
            ->join(' + ');
        $status = $event?->status === 'completed' ? 'past' : 'upcoming';

        return [
            'id' => $booking->reference,
            'status' => $status,
            'badge' => $status === 'upcoming' ? 'Upcoming in '.max(0, (int) now()->diffInDays($event?->starts_at, false)).' days' : 'Completed',
            'title' => $event?->title ?? 'Puncak Travellers event',
            'date' => $event?->full_date_label ?? $event?->starts_at?->timezone('Asia/Jakarta')->format('D, j M Y - H:i'),
            'location' => $event?->location ?? $event?->place?->name ?? 'Puncak region',
            'reference' => $booking->reference,
            'ticketLabel' => 'Tickets '.$ticketCount.($ticketNames ? ' - '.$ticketNames : ''),
            'primaryAction' => $status === 'upcoming' ? 'View ticket' : 'Certificate',
            'primaryHref' => '/account',
            'secondaryAction' => $status === 'upcoming' ? 'Manage booking' : 'View recap',
            'secondaryHref' => $status === 'upcoming' ? '/account' : ($event?->recap_href ?? '/account'),
        ];
    }

    private function savedEventCard(Event $event): array
    {
        return [
            'id' => 'SAVED-'.str($event->slug)->upper()->replace('-', '_')->toString(),
            'status' => 'saved',
            'badge' => 'Saved',
            'title' => $event->title,
            'date' => $event->date_label ?? $event->starts_at?->timezone('Asia/Jakarta')->format('D, j M Y - H:i'),
            'location' => $event->location ?? $event->place?->name ?? 'Puncak region',
            'reference' => 'Saved event',
            'ticketLabel' => $event->spots_label ?? 'Book when ready',
            'primaryAction' => 'Book ticket',
            'primaryHref' => $event->booking_href ?? "/events/{$event->slug}/booking",
            'secondaryAction' => 'View details',
            'secondaryHref' => $event->detail_href ?? "/events/{$event->slug}",
        ];
    }
}
