<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BundleComponent extends Model
{
    protected $fillable = [
        'bundle_sku_id',
        'component_sku_id',
        'qty',
    ];

    public function bundleSku(): BelongsTo
    {
        return $this->belongsTo(Sku::class, 'bundle_sku_id');
    }

    public function componentSku(): BelongsTo
    {
        return $this->belongsTo(Sku::class, 'component_sku_id');
    }
}