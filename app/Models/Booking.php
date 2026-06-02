<?php

namespace App\Models;

use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'event_id',
    'reference',
    'status',
    'attendee_name',
    'attendee_email',
    'subtotal',
    'booking_fee',
    'total',
    'currency',
    'idempotency_key',
    'cancelled_at',
    'cancellation_reason',
])]
class Booking extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => self::STATUS_CONFIRMED,
        'subtotal' => 0,
        'booking_fee' => 0,
        'total' => 0,
        'currency' => 'IDR',
    ];

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'booking_fee' => 'integer',
            'total' => 'integer',
            'cancelled_at' => 'datetime',
        ];
    }
}
