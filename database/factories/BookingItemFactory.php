<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingItem>
 */
class BookingItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'ticket_type_id' => TicketType::factory(),
            'quantity' => fake()->numberBetween(1, 3),
            'unit_price' => fake()->numberBetween(0, 250000),
        ];
    }
}
