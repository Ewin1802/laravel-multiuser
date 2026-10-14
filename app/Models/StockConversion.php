<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockConversion extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'source_product_id',
        'target_product_id',
        'source_qty',
        'ratio',
        'target_qty',
        'unit_cost',
        'note',
        'user_id',
        'reverted_at',
    ];

    protected $casts = [
        'source_qty' => 'float',
        'ratio' => 'float',
        'target_qty' => 'float',
        'unit_cost' => 'float',
        'reverted_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'source_product_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'target_product_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isReverted(): bool
    {
        return $this->reverted_at !== null;
    }
}
