<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Order extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_CONFIRMED => 'Confirmed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const CHANNELS = [
        'manual' => 'Manual',
        'shopify' => 'Shopify',
        'tiktok' => 'TikTok Shop',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'channel' => 'manual',
    ];

    protected $fillable = [
        'order_no',
        'channel',
        'external_ref',
        'order_date',
        'customer_name',
        'customer_phone',
        'customer_email',
        'shipping_address',
        'status',
        'payment_status',
        'fulfillment_status',
        'shipping_fee',
        'discount',
        'total',
        'notes',
        'confirmed_at',
        'cancelled_at',
        'imported_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'imported_at' => 'datetime',
            'shipping_fee' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Auto order number after insert, e.g. ORD-000001
        static::created(function (Order $order) {
            if (blank($order->order_no)) {
                $order->order_no = 'ORD-' . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
                $order->saveQuietly();
            }
        });

        // Confirmed/cancelled orders have stock history — never delete them
        static::deleting(function (Order $order) {
            if ($order->status !== self::STATUS_DRAFT) {
                throw new \RuntimeException('Only draft orders can be deleted. Cancel the order instead.');
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function picks(): HasManyThrough
    {
        return $this->hasManyThrough(OrderItemBatch::class, OrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function recalculateTotal(): void
    {
        $subtotal = (float) $this->items()->sum('line_total');
        $this->total = max(0, $subtotal + (float) $this->shipping_fee - (float) $this->discount);
        $this->saveQuietly();
    }
}