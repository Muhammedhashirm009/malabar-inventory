<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    protected $table = 'inventory';

    protected $fillable = [
        'product_id',
        'quantity',
        'mrp',
        'getting_rate',
        'sale_rate',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'mrp' => 'decimal:2',
            'getting_rate' => 'decimal:2',
            'sale_rate' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
