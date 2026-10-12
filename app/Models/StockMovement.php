<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class StockMovement extends Model
{
    public const TYPES = [
        'receive'        => 'Receive',
        'sale'           => 'Sale',
        'return_restock' => 'Return (restock)',
        'damaged'        => 'Damaged',
        'adjustment'     => 'Adjustment',
        'assemble_in'    => 'Assemble (bundle in)',
        'assemble_out'   => 'Assemble (component out)',
        'transfer'       => 'Transfer',
    ];

    protected $fillable = [
        'sku_id',
        'batch_id',
        'location_id',
        'qty',
        'type',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Stock movements cannot be edited. Record an adjustment instead.');
        });

        static::deleting(function () {
            throw new LogicException('Stock movements cannot be deleted. Record an adjustment instead.');
        });
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}