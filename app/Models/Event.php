<?php

namespace App\Models;

use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'community_id',
    'place_id',
    'public_id',
    'title',
    'slug',
    'description',
    'category',
    'activity',
    'activity_type',
    'distance_label',
    'elevation_label',
    'difficulty',
    'venue_name',
    'venue_description',
    'summary',
    'includes',
    'schedule',
    'starts_at',
    'ends_at',
    'status_label',
    'date_label',
    'full_date_label',
    'time_label',
    'location',
    'region',
    'price_label',
    'spots_label',
    'cover_image',
    'image_alt',
    'detail_href',
    'booking_href',
    'recap_href',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    public const ACTIVITY_TRAIL_RUN = 'trail-run';

    public const ACTIVITY_WALK = 'walk';

    public const ACTIVITY_CAMPING = 'camping';

    public const ACTIVITY_HIKE = 'hike';

    public const ACTIVITY_WELLNESS = 'wellness';

    public const ACTIVITY_FUN_RUN = 'fun-run';

    /**
     * @var array<string, string>
     */
    public const ACTIVITY_LABELS = [
        self::ACTIVITY_TRAIL_RUN => 'Trail Run',
        self::ACTIVITY_WALK => 'Walk',
        self::ACTIVITY_CAMPING => 'Camping',
        self::ACTIVITY_HIKE => 'Hike',
        self::ACTIVITY_WELLNESS => 'Wellness',
        self::ACTIVITY_FUN_RUN => 'Fun Run',
    ];

    protected $attributes = [
        'activity_type' => self::ACTIVITY_TRAIL_RUN,
    ];

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

    public function savedEvents(): HasMany
    {
        return $this->hasMany(SavedEvent::class);
    }

    public function scopeForStatus(Builder $query, string $status): Builder
    {
        $now = now();

        return match ($status) {
            'past', 'completed' => $query->where('ends_at', '<', $now),
            'ongoing' => $query->where('starts_at', '<=', $now)->where('ends_at', '>=', $now),
            'upcoming' => $query->where('starts_at', '>', $now),
            default => $query,
        };
    }

    public function getActivityLabelAttribute(): string
    {
        $activity = $this->activity ?? $this->activity_type;

        return self::ACTIVITY_LABELS[$activity] ?? str($activity)->replace(['_', '-'], ' ')->title()->toString();
    }

    public function getStatusAttribute(): string
    {
        $now = now();

        if ($this->ends_at->lt($now)) {
            return 'completed';
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
            'summary' => 'array',
            'includes' => 'array',
            'schedule' => 'array',
        ];
    }
}
