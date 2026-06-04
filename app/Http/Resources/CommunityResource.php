<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CommunityResource extends JsonResource
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
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image_path' => $this->image_path,
            'image_url' => $this->publicImageUrl($this->image_path),
            'member_count' => $this->member_count,
            'places_count' => $this->places_count ?? $this->places()->count(),
            'events_count' => $this->events_count ?? $this->events()->count(),
            'placesCount' => $this->places_count ?? $this->places()->count(),
            'eventsCount' => $this->events_count ?? $this->events()->count(),
            'children' => CommunityResource::collection($this->whenLoaded('children')),
        ];
    }

    private function publicImageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
