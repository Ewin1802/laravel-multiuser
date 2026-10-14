<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockInItem extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'stock_in_id', 'product_id', 'product_unit_id',
        'unit_name', 'unit_conversion', 'quantity', 'base_quantity',
        'cost_price', 'cost_per_base', 'expiry_date', 'batch_code',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_conversion' => 'float',
        'base_quantity' => 'float',
        'cost_price' => 'float',
        'cost_per_base' => 'float',
        'expiry_date' => 'date',
    ];

    public function stockIn(): BelongsTo
    {
        return $this->belongsTo(StockIn::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }

    public function batch(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(StockBatch::class);
    }
}
