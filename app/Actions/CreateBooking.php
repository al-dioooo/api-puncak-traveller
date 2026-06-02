<?php

namespace App\Actions;

use App\Models\Booking;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateBooking
{
    /**
     * @param  array<int, array{ticket_type_id: int, quantity: int}>  $items
     */
    public function execute(User $user, Event $event, array $items, string $idempotencyKey): Booking
    {
        $existingBooking = Booking::query()
            ->whereBelongsTo($user)
            ->where('idempotency_key', $idempotencyKey)
            ->with(['event', 'items.ticketType'])
            ->first();

        if ($existingBooking !== null) {
            return $existingBooking;
        }

        return DB::transaction(function () use ($user, $event, $items, $idempotencyKey): Booking {
            $requestedItems = collect($items)
                ->mapWithKeys(fn (array $item): array => [(int) $item['ticket_type_id'] => (int) $item['quantity']])
                ->sortKeys();

            $ticketTypes = TicketType::query()
                ->whereBelongsTo($event)
                ->whereIn('id', $requestedItems->keys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($ticketTypes->count() !== $requestedItems->count()) {
                throw ValidationException::withMessages([
                    'items' => 'One or more ticket types are not available for this event.',
                ]);
            }

            $booking = Booking::query()->create([
                'user_id' => $user->id,
                'event_id' => $event->id,
                'reference' => $this->makeReference(),
                'status' => Booking::STATUS_RESERVED,
                'total' => 0,
                'idempotency_key' => $idempotencyKey,
            ]);

            $total = 0;

            foreach ($requestedItems as $ticketTypeId => $quantity) {
                /** @var TicketType $ticketType */
                $ticketType = $ticketTypes->get($ticketTypeId);

                if ($ticketType->remaining < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Only {$ticketType->remaining} {$ticketType->name} tickets remain.",
                    ]);
                }

                $updated = TicketType::query()
                    ->whereKey($ticketType->id)
                    ->whereRaw('sold + ? <= quantity', [$quantity])
                    ->increment('sold', $quantity);

                if ($updated === 0) {
                    throw ValidationException::withMessages([
                        'items' => "{$ticketType->name} is sold out.",
                    ]);
                }

                $booking->items()->create([
                    'ticket_type_id' => $ticketType->id,
                    'quantity' => $quantity,
                    'unit_price' => $ticketType->price,
                ]);

                $total += $quantity * $ticketType->price;
            }

            $booking->update(['total' => $total]);

            return $booking->load(['event', 'items.ticketType']);
        });
    }

    private function makeReference(): string
    {
        return 'PT-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
    }
}
