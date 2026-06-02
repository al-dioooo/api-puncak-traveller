<?php

namespace Database\Factories;

use App\Models\TicketType;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketType>
 */
class TicketTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->randomElement(['General Admission', 'Community Pass', 'Camping Add-on']),
            'price' => fake()->numberBetween(0, 250000),
            'quantity' => fake()->numberBetween(10, 100),
            'sold' => 0,
        ];
    }
}
