<?php

namespace Database\Factories;

use App\Models\Gallery;
use App\Models\Community;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gallery>
 */
class GalleryFactory extends Factory
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
            'event_id' => fake()->boolean(70) ? Event::factory() : null,
            'image_path' => 'galleries/'.fake()->uuid().'.jpg',
            'caption' => fake()->sentence(),
        ];
    }
}
