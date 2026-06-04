<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PlaceResource extends JsonResource
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
            'name' => $this->name,
            'lat' => (float) $this->lat,
            'lng' => (float) $this->lng,
            'description' => $this->description,
            'image_path' => $this->image_path,
            'imageUrl' => $this->publicImageUrl($this->image_path),
            'image_url' => $this->publicImageUrl($this->image_path),
            'imageAlt' => $this->image_alt ?? $this->name,
            'community' => new CommunityResource($this->whenLoaded('community')),
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
