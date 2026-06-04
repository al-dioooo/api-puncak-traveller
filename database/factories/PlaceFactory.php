<?php

namespace Database\Factories;

use App\Models\Place;
use App\Models\Community;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Place>
 */
class PlaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'name' => fake()->city().' Basecamp',
            'lat' => fake()->latitude(-6.75, -6.60),
            'lng' => fake()->longitude(106.90, 107.05),
            'description' => fake()->paragraph(),
            'image_path' => 'places/'.fake()->uuid().'.jpg',
            'image_alt' => fake()->sentence(),
        ];
    }
}
