<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Batch extends Model
{
    protected $fillable = [
        'sku_id',
        'batch_no',
        'received_date',
        'expiry_date',
        'supplier',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'received_date' => 'date',
            'expiry_date'   => 'date',
        ];
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }
}