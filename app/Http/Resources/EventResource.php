<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'place_id' => $this->place_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'activity_type' => $this->activity_type,
            'activity_label' => $this->activity_label,
            'distance_label' => $this->distance_label,
            'status' => $this->status,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'starting_price' => $this->when(array_key_exists('starting_price', $this->getAttributes()), fn (): ?int => $this->starting_price === null ? null : (int) $this->starting_price),
            'cover_image' => $this->cover_image,
            'cover_image_url' => $this->publicImageUrl($this->cover_image),
            'participant_count' => $this->when(array_key_exists('participant_count', $this->getAttributes()), fn (): int => (int) $this->participant_count),
            'community' => new CommunityResource($this->whenLoaded('community')),
            'place' => new PlaceResource($this->whenLoaded('place')),
            'ticket_types' => TicketTypeResource::collection($this->whenLoaded('ticketTypes')),
        ];
    }

    private function publicImageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
