<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'category',
        'type',
        'is_sellable',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_sellable' => 'boolean',
        ];
    }
}
