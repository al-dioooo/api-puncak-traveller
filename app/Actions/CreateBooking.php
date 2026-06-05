<?php

namespace App\Actions;

use App\Models\Booking;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

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

        $requestedItems = collect($items)
            ->mapWithKeys(fn (array $item): array => [(string) $item['ticket_tier_id'] => (int) $item['quantity']])
            ->sortKeys();
        $requestedTicketTierIds = $requestedItems->keys();

        $ticketTypes = TicketType::query()
            ->whereBelongsTo($event)
            ->orderBy('id', 'asc')
            ->get()
            ->reduce(function ($lookup, TicketType $ticketType) use ($requestedTicketTierIds) {
                $publicId = $ticketType->public_id !== null ? (string) $ticketType->public_id : null;
                $modelId = (string) $ticketType->getKey();

                if ($publicId !== null && $requestedTicketTierIds->containsStrict($publicId)) {
                    $lookup->put($publicId, $ticketType);
                }

                if ($requestedTicketTierIds->containsStrict($modelId) && ! $lookup->has($modelId)) {
                    $lookup->put($modelId, $ticketType);
                }

                return $lookup;
            }, collect());

        if ($ticketTypes->count() !== $requestedItems->count()) {
            throw new NotFoundHttpException('One or more ticket tiers are not available for this event.');
        }

        foreach ($requestedItems as $ticketTierId => $quantity) {
            /** @var TicketType $ticketType */
            $ticketType = $ticketTypes->get($ticketTierId);

            if ($ticketType->remaining < $quantity) {
                throw new ConflictHttpException("Only {$ticketType->remaining} {$ticketType->name} tickets remain.");
            }
        }

        $reservedItems = [];
        $bookingItems = [];
        $subtotal = 0;
        $booking = null;

        try {
            foreach ($requestedItems as $ticketTierId => $quantity) {
                /** @var TicketType $ticketType */
                $ticketType = $ticketTypes->get($ticketTierId);

                $updated = TicketType::query()
                    ->whereKey($ticketType->id)
                    ->where('sold', '<=', $ticketType->quantity - $quantity)
                    ->increment('sold', $quantity);

                if ($updated === 0) {
                    throw new ConflictHttpException("{$ticketType->name} is sold out.");
                }

                $reservedItems[] = ['ticket_type_id' => $ticketType->id, 'quantity' => $quantity];
                $bookingItems[] = [
                    'ticket_type_id' => $ticketType->id,
                    'quantity' => $quantity,
                    'unit_price' => $ticketType->price,
                ];
                $subtotal += $quantity * $ticketType->price;
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
                'subtotal' => $subtotal,
                'booking_fee' => self::BOOKING_FEE,
                'total' => $subtotal + self::BOOKING_FEE,
                'currency' => 'IDR',
                'idempotency_key' => $idempotencyKey ?? (string) Str::uuid(),
            ]);

            foreach ($bookingItems as $bookingItem) {
                $booking->items()->create($bookingItem);
            }

            return $booking->load(['event', 'items.ticketType']);
        } catch (Throwable $throwable) {
            foreach ($reservedItems as $reservedItem) {
                TicketType::query()
                    ->whereKey($reservedItem['ticket_type_id'])
                    ->where('sold', '>=', $reservedItem['quantity'])
                    ->decrement('sold', $reservedItem['quantity']);
            }

            if ($booking !== null) {
                $booking->items()->delete();
                $booking->delete();
            }

            throw $throwable;
        }
    }

    private function makeReference(): string
    {
        return 'PTR-'.now()->format('y').'-'.Str::upper(Str::random(6));
    }
}
