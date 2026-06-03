<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateBooking;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

        $bookingsQuery = Booking::query()
            ->with(['user', 'event', 'items.ticketType'])
            ->latest();

        if ($request->user()->role !== User::ROLE_ADMIN) {
            $bookingsQuery->whereBelongsTo($request->user());
        }

        $this->applyStatusFilter($bookingsQuery, $request->string('status')->toString());

        $perPage = max(1, min($request->integer('per_page', 15), 50));
        $paginator = $bookingsQuery->paginate($perPage);
        $rows = $paginator->getCollection()
            ->map(fn (Booking $booking): array => $request->user()->role === User::ROLE_ADMIN
                ? $this->adminBookingRow($booking)
                : $this->bookingCard($booking))
            ->values();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
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

    public function show(Request $request, Booking $booking): BookingResource|JsonResponse
    {
        abort_unless($booking->user()->is($request->user()) || $request->user()->role === User::ROLE_ADMIN, 403);

        $booking->load(['user', 'event', 'items.ticketType']);

        if ($request->user()->role === User::ROLE_ADMIN) {
            return response()->json(['data' => $this->adminBookingDetail($booking)]);
        }

        return new BookingResource($booking);
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

    public function refund(Booking $booking): JsonResponse
    {
        $booking = DB::transaction(function () use ($booking): Booking {
            $booking->load(['items.ticketType']);

            if ($booking->status === Booking::STATUS_REFUNDED) {
                return $booking;
            }

            if (! in_array($booking->status, [Booking::STATUS_CONFIRMED, Booking::STATUS_COMPLETED], true)) {
                throw new ConflictHttpException('Only confirmed bookings can be refunded.');
            }

            foreach ($booking->items as $item) {
                $item->ticketType()->decrement('sold', $item->quantity);
            }

            $booking->update([
                'status' => Booking::STATUS_REFUNDED,
                'payment_status' => Booking::PAYMENT_REFUNDED,
                'cancelled_at' => now(),
                'cancellation_reason' => 'Refunded by administrator.',
            ]);

            return $booking;
        });

        return response()->json([
            'message' => 'Booking refunded successfully.',
            'data' => [
                'reference' => $booking->reference,
                'status' => Booking::STATUS_REFUNDED,
            ],
        ]);
    }

    public function updatePaymentStatus(Request $request, Booking $booking): JsonResponse
    {
        $validated = $request->validate([
            'payment_status' => ['required', Rule::in([
                Booking::PAYMENT_PENDING,
                Booking::PAYMENT_PAID,
                Booking::PAYMENT_FAILED,
                Booking::PAYMENT_REFUNDED,
            ])],
        ]);

        $booking->update([
            'payment_status' => $validated['payment_status'],
            'status' => match ($validated['payment_status']) {
                Booking::PAYMENT_PAID => Booking::STATUS_CONFIRMED,
                Booking::PAYMENT_REFUNDED => Booking::STATUS_REFUNDED,
                Booking::PAYMENT_FAILED => Booking::STATUS_CANCELLED,
                default => Booking::STATUS_PENDING,
            },
        ]);

        return response()->json([
            'message' => 'Payment status updated successfully.',
            'data' => [
                'reference' => $booking->reference,
                'status' => $booking->status,
                'paymentStatus' => $booking->payment_status,
            ],
        ]);
    }

    private function applyStatusFilter(Builder $query, string $status): void
    {
        match ($status) {
            'upcoming' => $query
                ->where('status', '!=', Booking::STATUS_CANCELLED)
                ->whereHas('event', fn (Builder $event) => $event->where('starts_at', '>', now())),
            'past' => $query->where(function ($query): void {
                $query
                    ->where('status', Booking::STATUS_COMPLETED)
                    ->orWhereHas('event', fn (Builder $event) => $event->where('ends_at', '<', now()));
            }),
            'paid' => $query->where('payment_status', Booking::PAYMENT_PAID),
            'pending' => $query->where('payment_status', Booking::PAYMENT_PENDING),
            'cancelled' => $query->where('status', Booking::STATUS_CANCELLED),
            'refunded' => $query->where(function ($query): void {
                $query
                    ->where('status', Booking::STATUS_REFUNDED)
                    ->orWhere('payment_status', Booking::PAYMENT_REFUNDED);
            }),
            default => null,
        };
    }

    private function adminBookingRow(Booking $booking): array
    {
        $tickets = $booking->items->map(fn ($item): array => [
            'name' => $item->ticketType?->name ?? 'Ticket',
            'quantity' => (int) $item->quantity,
            'price' => (int) $item->unit_price,
        ])->values();

        return [
            'id' => (string) $booking->id,
            'reference' => $booking->reference,
            'user' => [
                'id' => (string) $booking->user?->id,
                'name' => $booking->user?->name ?? $booking->attendee_name ?? 'Guest',
                'email' => $booking->user?->email ?? $booking->attendee_email,
            ],
            'event' => [
                'title' => $booking->event?->title ?? 'Puncak Travellers event',
                'date' => $booking->event?->full_date_label ?? $booking->event?->starts_at?->timezone('Asia/Jakarta')->format('j M Y'),
                'location' => $booking->event?->location ?? 'Puncak region',
                'imageUrl' => $this->publicImageUrl($booking->event?->cover_image),
                'imageAlt' => $booking->event?->image_alt ?? $booking->event?->title ?? 'Puncak Travellers event',
            ],
            'tickets' => $tickets,
            'qty' => (int) $tickets->sum('quantity'),
            'total' => (int) $booking->total,
            'status' => $booking->status,
            'paymentStatus' => $booking->payment_status,
            'date' => $booking->created_at?->toIso8601String(),
        ];
    }

    private function adminBookingDetail(Booking $booking): array
    {
        return [
            ...$this->adminBookingRow($booking),
            'member' => [
                'name' => $booking->user?->name ?? $booking->attendee_name ?? 'Guest',
                'email' => $booking->user?->email ?? $booking->attendee_email,
                'phone' => '',
                'community' => $booking->user?->crew ?? 'Puncak Travellers',
            ],
            'subtotal' => (int) $booking->subtotal,
            'bookingFee' => (int) $booking->booking_fee,
            'currency' => $booking->currency ?? 'IDR',
            'timeline' => [
                [
                    'title' => 'Booking confirmed',
                    'time' => $booking->created_at?->timezone('Asia/Jakarta')->format('j M Y - H:i'),
                    'type' => 'confirmed',
                ],
            ],
        ];
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

        $isCancelled = $booking->status === Booking::STATUS_CANCELLED;
        $isRefunded = $booking->status === Booking::STATUS_REFUNDED;

        $status = match (true) {
            $isCancelled || $isRefunded => 'past',
            $event?->status === 'completed' => 'past',
            default => 'upcoming',
        };

        $badge = match (true) {
            $isCancelled => 'Cancelled',
            $isRefunded => 'Refunded',
            $status === 'upcoming' => 'Upcoming in '.max(0, (int) now()->diffInDays($event?->starts_at, false)).' days',
            default => 'Completed',
        };

        $primaryAction = match (true) {
            $isCancelled || $isRefunded => 'View details',
            $status === 'upcoming' => 'View ticket',
            default => 'Certificate',
        };

        return [
            'id' => $booking->reference,
            'status' => $status,
            'badge' => $badge,
            'title' => $event?->title ?? 'Puncak Travellers event',
            'date' => $event?->full_date_label ?? $event?->starts_at?->timezone('Asia/Jakarta')->format('D, j M Y - H:i'),
            'location' => $event?->location ?? $event?->place?->name ?? 'Puncak region',
            'reference' => $booking->reference,
            'ticketLabel' => 'Tickets '.$ticketCount.($ticketNames ? ' - '.$ticketNames : ''),
            'primaryAction' => $primaryAction,
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

    private function publicImageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
