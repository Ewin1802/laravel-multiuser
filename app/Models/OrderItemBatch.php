<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pemakaian batch oleh satu baris order (FEFO). Ikut toko lewat order_items. */
class OrderItemBatch extends Model
{
    protected $fillable = ['order_item_id', 'stock_batch_id', 'quantity', 'cost_price'];

    protected $casts = [
        'quantity' => 'float',
        'cost_price' => 'float',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'stock_batch_id');
    }
}
