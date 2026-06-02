<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\Event;
use App\Models\Gallery;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
        $title = fake()->sentence(3);

        return [
            'community_id' => Community::factory(),
            'event_id' => fake()->boolean(70) ? Event::factory() : null,
            'public_id' => Str::slug($title).'-'.fake()->unique()->numberBetween(1000, 9999),
            'title' => $title,
            'event_label' => fake()->words(2, true),
            'category' => fake()->randomElement(['trail-run', 'walk', 'camping', 'hike', 'wellness']),
            'year' => fake()->randomElement(['2026', '2025']),
            'image_path' => 'galleries/'.fake()->uuid().'.jpg',
            'image_alt' => fake()->sentence(),
            'caption' => fake()->sentence(),
        ];
    }
}
