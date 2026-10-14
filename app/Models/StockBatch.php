<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Batch stok produk ber-expire. Jumlah dalam satuan terkecil.
 */
class StockBatch extends Model
{
    use BelongsToStore;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_DEPLETED = 'depleted';
    public const STATUS_WRITTEN_OFF = 'written_off';

    protected $fillable = [
        'product_id', 'stock_in_item_id', 'expiry_date', 'batch_code', 'received_at',
        'initial_quantity', 'remaining_quantity', 'cost_price', 'status',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'received_at' => 'date',
        'initial_quantity' => 'float',
        'remaining_quantity' => 'float',
        'cost_price' => 'float',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockInItem(): BelongsTo
    {
        return $this->belongsTo(StockInItem::class);
    }

    /** Batch yang masih bisa dijual pada tanggal $date (belum kedaluwarsa). */
    public function scopeSellable(Builder $q, $date = null): Builder
    {
        $date = ($date ?? now())->toDateString();

        return $q->where('status', self::STATUS_ACTIVE)
            ->where('remaining_quantity', '>', 0)
            ->where(fn ($w) => $w->whereNull('expiry_date')->orWhere('expiry_date', '>=', $date));
    }

    /** Urutan FEFO: expire terdekat dulu, lalu yang masuk lebih dulu. */
    public function scopeFefo(Builder $q): Builder
    {
        return $q->orderByRaw('expiry_date IS NULL')   // batch tanpa tanggal paling akhir
            ->orderBy('expiry_date')
            ->orderBy('received_at')
            ->orderBy('id');
    }

    public function isExpired($date = null): bool
    {
        return $this->expiry_date !== null
            && $this->expiry_date->lt(($date ?? now())->copy()->startOfDay());
    }
}
