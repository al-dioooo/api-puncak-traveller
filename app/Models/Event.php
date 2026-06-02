<?php

namespace App\Models;

use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['community_id', 'place_id', 'title', 'slug', 'description', 'starts_at', 'ends_at', 'cover_image'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function galleries(): HasMany
    {
        return $this->hasMany(Gallery::class);
    }

    public function scopeForStatus(Builder $query, string $status): Builder
    {
        $now = now();

        return match ($status) {
            'past' => $query->where('ends_at', '<', $now),
            'ongoing' => $query->where('starts_at', '<=', $now)->where('ends_at', '>=', $now),
            'upcoming' => $query->where('starts_at', '>', $now),
            default => $query,
        };
    }

    public function getStatusAttribute(): string
    {
        $now = now();

        if ($this->ends_at->lt($now)) {
            return 'past';
        }

        if ($this->starts_at->gt($now)) {
            return 'upcoming';
        }

        return 'ongoing';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
