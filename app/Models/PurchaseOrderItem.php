<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'sku_id',
        'qty_ordered',
        'qty_received',
        'unit_cost',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'qty_ordered' => 'integer',
            'qty_received' => 'integer',
            'unit_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PurchaseOrderItem $item) {
            $item->line_total = round((int) $item->qty_ordered * (float) $item->unit_cost, 2);
        });
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }
    public function qtyOutstanding(): int
    {
        return max(0, $this->qty_ordered - $this->qty_received);
    }
}