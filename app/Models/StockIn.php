<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockIn extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'supplier_id', 'user_id', 'invoice_number', 'received_at', 'notes', 'total',
        'payment_status', 'due_date', 'paid_at',
    ];

    protected $casts = [
        'received_at' => 'date',
        'due_date' => 'date',
        'paid_at' => 'date',
        'total' => 'float',
    ];

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockInItem::class);
    }
}
