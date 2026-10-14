<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satuan jual sebuah produk. conversion = isi dalam satuan terkecil.
 */
class ProductUnit extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'product_id', 'name', 'conversion', 'price', 'barcode', 'is_base', 'sort', 'is_active',
    ];

    protected $casts = [
        'conversion' => 'float',
        'price' => 'float',
        'is_base' => 'boolean',
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
