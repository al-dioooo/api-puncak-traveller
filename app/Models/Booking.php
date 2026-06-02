<?php

namespace App\Models;

use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'event_id', 'reference', 'status', 'total', 'idempotency_key'])]
class Booking extends Model
{
    public const STATUS_RESERVED = 'reserved';

    public const STATUS_CANCELLED = 'cancelled';

    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => self::STATUS_RESERVED,
        'total' => 0,
    ];

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
            'total' => 'integer',
        ];
    }
}
