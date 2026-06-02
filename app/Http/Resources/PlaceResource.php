<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'community' => new CommunityResource($this->whenLoaded('community')),
        ];
    }
}
