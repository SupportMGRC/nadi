<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_ORDERED = 'ordered';
    public const STATUS_PARTIAL = 'partially_received';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_SUBMITTED => 'Submitted',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_ORDERED => 'Ordered',
        self::STATUS_PARTIAL => 'Partially received',
        self::STATUS_RECEIVED => 'Received',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'currency' => 'MYR',
    ];

    protected $fillable = [
        'po_no',
        'supplier_id',
        'status',
        'order_date',
        'expected_date',
        'currency',
        'subtotal',
        'shipping_fee',
        'tax',
        'total',
        'notes',
        'terms',
        'created_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'ordered_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_date' => 'date',
            'subtotal' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'ordered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Auto PO number after insert, e.g. PO-000001
        static::created(function (PurchaseOrder $po) {
            if (blank($po->po_no)) {
                $po->po_no = 'PO-' . str_pad((string) $po->id, 6, '0', STR_PAD_LEFT);
                $po->saveQuietly();
            }
        });

        // Only drafts can be deleted — anything further has a paper trail
        static::deleting(function (PurchaseOrder $po) {
            if ($po->status !== self::STATUS_DRAFT) {
                throw new \RuntimeException('Only draft purchase orders can be deleted. Cancel it instead.');
            }
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function recalculateTotal(): void
    {
        $this->subtotal = (float) $this->items()->sum('line_total');
        $this->total = max(0, (float) $this->subtotal + (float) $this->shipping_fee + (float) $this->tax);
        $this->saveQuietly();
    }
}