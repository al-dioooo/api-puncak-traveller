<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'event_id' => Event::factory(),
            'reference' => 'PT-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
            'status' => Booking::STATUS_CONFIRMED,
            'payment_status' => Booking::PAYMENT_PAID,
            'subtotal' => 0,
            'booking_fee' => 0,
            'total' => 0,
            'currency' => 'IDR',
            'idempotency_key' => (string) Str::uuid(),
        ];
    }
}
