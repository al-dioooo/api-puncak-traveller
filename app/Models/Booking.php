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
    'payment_status',
    'payment_provider',
    'midtrans_order_id',
    'snap_token',
    'snap_redirect_url',
    'midtrans_transaction_id',
    'midtrans_payment_type',
    'midtrans_status',
    'midtrans_fraud_status',
    'midtrans_payload',
    'attendee_name',
    'attendee_email',
    'subtotal',
    'booking_fee',
    'total',
    'currency',
    'idempotency_key',
    'cancelled_at',
    'paid_at',
    'payment_failed_at',
    'stock_released_at',
    'cancellation_reason',
])]
class Booking extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REFUNDED = 'refunded';

    public const PAYMENT_PENDING = 'pending';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_FAILED = 'failed';

    public const PAYMENT_REFUNDED = 'refunded';

    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => self::STATUS_CONFIRMED,
        'payment_status' => self::PAYMENT_PENDING,
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
            'midtrans_payload' => 'array',
            'cancelled_at' => 'datetime',
            'paid_at' => 'datetime',
            'payment_failed_at' => 'datetime',
            'stock_released_at' => 'datetime',
        ];
    }
}
