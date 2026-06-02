<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\Event;
use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);
        $startsAt = fake()->dateTimeBetween('+1 week', '+3 months');

        return [
            'community_id' => Community::factory(),
            'place_id' => Place::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => fake()->paragraphs(2, true),
            'activity_type' => fake()->randomElement(array_keys(Event::ACTIVITY_LABELS)),
            'distance_label' => fake()->randomElement(['5K', '10K', '15K', 'Weekend Camp', 'Half Day']),
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+3 hours'),
            'cover_image' => 'events/'.fake()->uuid().'.jpg',
        ];
    }

    public function past(): static
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDays(3)->addHours(3),
        ]);
    }

    public function ongoing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(2),
        ]);
    }

    public function upcoming(): static
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(3),
        ]);
    }
}
