<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'id' => $this->public_id ?? (string) $this->id,
            'title' => $this->title ?? $this->caption ?? 'Puncak Travellers moment',
            'event' => $this->event_label ?? $this->event?->title ?? 'Puncak Travellers',
            'category' => $this->category ?? $this->event?->activity ?? 'trail-run',
            'year' => $this->year ?? $this->created_at?->format('Y') ?? '2026',
            'imageUrl' => $this->publicImageUrl($this->image_path),
            'imageAlt' => $this->image_alt ?? $this->caption ?? 'Puncak Travellers gallery moment',
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
