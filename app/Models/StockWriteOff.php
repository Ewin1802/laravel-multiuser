<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Barang keluar bukan karena penjualan: kedaluwarsa, rusak, retur. */
class StockWriteOff extends Model
{
    use BelongsToStore;

    public const REASONS = [
        'expired' => 'Kedaluwarsa',
        'damaged' => 'Rusak',
        'returned' => 'Retur ke supplier',
    ];

    protected $fillable = [
        'client_uuid', 'product_id', 'stock_batch_id', 'quantity', 'cost_price',
        'reason', 'note', 'user_id', 'occurred_at',
    ];

    protected $casts = [
        'quantity' => 'float',
        'cost_price' => 'float',
        'occurred_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'stock_batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
