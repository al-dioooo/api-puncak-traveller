<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GalleryResource extends JsonResource
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
            'event_id' => $this->event_id,
            'image_path' => $this->image_path,
            'caption' => $this->caption,
            'community' => new CommunityResource($this->whenLoaded('community')),
            'event' => new EventResource($this->whenLoaded('event')),
        ];
    }
}
