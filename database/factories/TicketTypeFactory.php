<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
        $name = fake()->randomElement(['General Admission', 'Community Pass', 'Camping Add-on']);

        return [
            'event_id' => Event::factory(),
            'public_id' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => $name,
            'description' => fake()->sentence(),
            'price' => fake()->numberBetween(0, 250000),
            'currency' => 'IDR',
            'quantity' => fake()->numberBetween(10, 100),
            'sold' => 0,
        ];
    }
}
