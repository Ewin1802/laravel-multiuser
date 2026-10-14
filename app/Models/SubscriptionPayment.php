<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pembayaran langganan via transfer manual, dikonfirmasi superadmin. */
class SubscriptionPayment extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'plan_id', 'amount', 'bank_name', 'sender_name', 'transferred_at', 'proof_image', 'note',
        'promo_code_id', 'promo_code', 'original_amount', 'discount_amount',
    ];

    protected $casts = [
        'amount' => 'integer',
        'original_amount' => 'integer',
        'discount_amount' => 'integer',
        'transferred_at' => 'date',
        'reviewed_at' => 'datetime',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function promo(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class, 'promo_code_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getProofUrlAttribute(): ?string
    {
        return $this->proof_image ? asset('storage/' . $this->proof_image) : null;
    }
}
