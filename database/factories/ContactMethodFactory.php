<?php

namespace Database\Factories;

use App\Models\ContactMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMethod>
 */
class ContactMethodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->randomElement(['Email us', 'WhatsApp', 'Find us']),
            'value' => fake()->email(),
            'description' => fake()->sentence(),
            'icon' => fake()->randomElement(['mail', 'phone', 'map']),
            'sort_order' => fake()->numberBetween(1, 5),
        ];
    }
}
