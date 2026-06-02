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
        $priceFrom = $this->priceFrom();
        $spotsRemaining = $this->spotsRemaining();

        return [
            'id' => $this->public_id ?? (string) $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'category' => $this->category ?? $this->activity_label,
            'activity' => $this->activity ?? $this->activity_type,
            'status' => $this->status,
            'statusLabel' => $this->status_label ?? str($this->status)->replace('-', ' ')->title()->toString(),
            'startsAt' => $this->starts_at?->toIso8601String(),
            'endsAt' => $this->ends_at?->toIso8601String(),
            'dateLabel' => $this->date_label ?? $this->starts_at?->timezone('Asia/Jakarta')->format('D, j M - H:i'),
            'fullDateLabel' => $this->full_date_label ?? $this->starts_at?->timezone('Asia/Jakarta')->format('l, j F Y'),
            'timeLabel' => $this->time_label ?? 'Start '.$this->starts_at?->timezone('Asia/Jakarta')->format('H:i').' WIB',
            'location' => $this->location ?? $this->place?->name ?? 'Puncak region',
            'region' => $this->region ?? 'West Java',
            'priceFrom' => $priceFrom,
            'priceLabel' => $this->price_label ?? $this->formatPriceLabel($priceFrom),
            'spotsRemaining' => $spotsRemaining,
            'spotsLabel' => $this->spots_label ?? ($spotsRemaining > 0 ? "{$spotsRemaining} spots left" : 'Sold out'),
            'imageUrl' => $this->publicImageUrl($this->cover_image),
            'imageAlt' => $this->image_alt ?? $this->title,
            'detailHref' => $this->detail_href ?? "/events/{$this->slug}",
            'bookingHref' => $this->booking_href ?? "/events/{$this->slug}/booking",
            'recapHref' => $this->when($this->recap_href !== null, $this->recap_href),
            'organiser' => $this->when($this->relationLoaded('community'), fn (): array => [
                'id' => $this->community?->slug ?? (string) $this->community_id,
                'name' => $this->community?->name ?? 'Puncak Travellers',
                'description' => 'Organiser - '.($this->community?->member_count ?? 0).' members',
                'eventsHosted' => $this->community?->events()->count() ?? 0,
                'href' => $this->community?->slug ? "/communities/{$this->community->slug}" : '/about',
            ]),
            'distanceLabel' => $this->distance_label ?? $this->category ?? $this->activity_label,
            'elevationLabel' => $this->elevation_label ?? 'Community-supported route',
            'difficulty' => $this->difficulty ?? 'Friendly pace',
            'venueName' => $this->venue_name ?? $this->location ?? $this->place?->name ?? 'Puncak region',
            'venueDescription' => $this->venue_description ?? $this->description ?? 'Full route notes will be shared with registered participants.',
            'summary' => $this->summary ?? array_filter([$this->description]),
            'includes' => $this->includes ?? [
                'Community host and route briefing',
                'Trail support and safety coordination',
                'Shared event photos',
            ],
            'schedule' => $this->schedule ?? [
                ['time' => $this->starts_at?->timezone('Asia/Jakarta')->format('H:i') ?? '06:00', 'title' => 'Route starts'],
            ],
            'tickets' => TicketTypeResource::collection($this->whenLoaded('ticketTypes')),
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

    private function priceFrom(): int
    {
        if ($this->relationLoaded('ticketTypes')) {
            return (int) ($this->ticketTypes->min('price') ?? 0);
        }

        if (array_key_exists('starting_price', $this->getAttributes())) {
            return (int) ($this->starting_price ?? 0);
        }

        return 0;
    }

    private function spotsRemaining(): int
    {
        if (! $this->relationLoaded('ticketTypes')) {
            return 0;
        }

        return (int) $this->ticketTypes->sum(fn ($ticketType): int => $ticketType->remaining);
    }

    private function formatPriceLabel(int $price): string
    {
        if ($price <= 0) {
            return 'From Free';
        }

        if ($price % 1000 === 0) {
            return 'From Rp '.number_format($price / 1000).'K';
        }

        return 'From Rp '.number_format($price, 0, ',', '.');
    }
}
