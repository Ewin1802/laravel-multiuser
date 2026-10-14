<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory, BelongsToStore;

    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'product_unit_id',
        'product_name',
        'unit_name',
        'unit_conversion',
        'quantity',
        'base_quantity',
        'price',
        'cost_price',
        'cost_total',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'product_id' => 'integer',
        'quantity' => 'float',
        'price' => 'integer',
        'cost_price' => 'float',
        'unit_conversion' => 'float',
        'base_quantity' => 'float',
        'cost_total' => 'float',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(
            Order::class,
            'order_id'
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class,
            'product_id'
        );
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }

    public function batches(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderItemBatch::class);
    }
}
