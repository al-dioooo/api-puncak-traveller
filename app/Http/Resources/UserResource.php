<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $bookings = $this->bookings()->with('event')->get();
        $completedBookings = $bookings->filter(fn ($booking): bool => $booking->event?->status === 'completed');

        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatarUrl' => $this->avatar,
            'location' => $this->location,
            'memberSince' => $this->created_at?->format('Y'),
            'crew' => $this->crew,
            'stats' => [
                'eventsBooked' => $bookings->count(),
                'completed' => $completedBookings->count(),
                'kilometersLogged' => $completedBookings->sum(fn ($booking): int => $this->kilometersFromLabel($booking->event?->distance_label)),
            ],
        ];
    }

    private function kilometersFromLabel(?string $label): int
    {
        if ($label === null) {
            return 0;
        }

        preg_match_all('/(\d+)\s*K/i', $label, $matches);

        return collect($matches[1] ?? [])->sum(fn (string $value): int => (int) $value);
    }
}
