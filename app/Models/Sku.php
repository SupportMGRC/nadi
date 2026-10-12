<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sku extends Model
{
    protected $fillable = [
        'product_id',
        'sku_code',
        'barcode',
        'cost_price',
        'selling_price',
        'reorder_level',
        'safety_buffer',
        'packing_mode',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'cost_price'    => 'decimal:2',
            'selling_price' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
    public function components(): HasMany
{
    return $this->hasMany(BundleComponent::class, 'bundle_sku_id');
}
}