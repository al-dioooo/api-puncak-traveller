<?php

namespace App\Actions;

use App\Models\Booking;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CreateBooking
{
    public const BOOKING_FEE = 5000;

    /**
     * @param  array<int, array{ticket_tier_id: string, quantity: int}>  $items
     * @param  array<int, array{name?: string, email?: string, ticketTierId?: string}>  $attendees
     */
    public function execute(User $user, Event $event, array $items, array $attendees = [], ?string $idempotencyKey = null): Booking
    {
        if ($idempotencyKey) {
            $existingBooking = Booking::query()
                ->whereBelongsTo($user)
                ->where('idempotency_key', $idempotencyKey)
                ->with(['event', 'items.ticketType'])
                ->first();

            if ($existingBooking !== null) {
                return $existingBooking;
            }
        }

        return DB::transaction(function () use ($user, $event, $items, $attendees, $idempotencyKey): Booking {
            $requestedItems = collect($items)
                ->mapWithKeys(fn (array $item): array => [(string) $item['ticket_tier_id'] => (int) $item['quantity']])
                ->sortKeys();

            $ticketTypes = TicketType::query()
                ->whereBelongsTo($event)
                ->whereIn('public_id', $requestedItems->keys())
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('public_id');

            if ($ticketTypes->count() !== $requestedItems->count()) {
                throw new NotFoundHttpException('One or more ticket tiers are not available for this event.');
            }

            $firstAttendee = $attendees[0] ?? [];
            $booking = Booking::query()->create([
                'user_id' => $user->id,
                'event_id' => $event->id,
                'reference' => $this->makeReference(),
                'status' => Booking::STATUS_PENDING,
                'payment_status' => Booking::PAYMENT_PENDING,
                'attendee_name' => $firstAttendee['name'] ?? $user->name,
                'attendee_email' => $firstAttendee['email'] ?? $user->email,
                'subtotal' => 0,
                'booking_fee' => self::BOOKING_FEE,
                'total' => 0,
                'currency' => 'IDR',
                'idempotency_key' => $idempotencyKey ?? (string) Str::uuid(),
            ]);

            $subtotal = 0;

            foreach ($requestedItems as $ticketTierId => $quantity) {
                /** @var TicketType $ticketType */
                $ticketType = $ticketTypes->get($ticketTierId);

                if ($ticketType->remaining < $quantity) {
                    throw new ConflictHttpException("Only {$ticketType->remaining} {$ticketType->name} tickets remain.");
                }

                $updated = TicketType::query()
                    ->whereKey($ticketType->id)
                    ->whereRaw('sold + ? <= quantity', [$quantity])
                    ->increment('sold', $quantity);

                if ($updated === 0) {
                    throw new ConflictHttpException("{$ticketType->name} is sold out.");
                }

                $booking->items()->create([
                    'ticket_type_id' => $ticketType->id,
                    'quantity' => $quantity,
                    'unit_price' => $ticketType->price,
                ]);

                $subtotal += $quantity * $ticketType->price;
            }

            $booking->update([
                'subtotal' => $subtotal,
                'booking_fee' => self::BOOKING_FEE,
                'total' => $subtotal + self::BOOKING_FEE,
            ]);

            return $booking->load(['event', 'items.ticketType']);
        });
    }

    private function makeReference(): string
    {
        return 'PTR-'.now()->format('y').'-'.Str::upper(Str::random(6));
    }
}
