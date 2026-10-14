<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Kode promo langganan (tingkat platform, bukan per toko). */
class PromoCode extends Model
{
    public const PERCENT = 'percent';
    public const AMOUNT = 'amount';

    protected $fillable = [
        'code', 'description', 'discount_type', 'discount_value', 'max_discount', 'plan_ids',
        'starts_at', 'ends_at', 'max_uses', 'per_store_limit', 'first_payment_only', 'is_active',
    ];

    protected $casts = [
        'discount_value' => 'integer',
        'max_discount' => 'integer',
        'plan_ids' => 'array',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'max_uses' => 'integer',
        'per_store_limit' => 'integer',
        'first_payment_only' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function setCodeAttribute($value): void
    {
        $this->attributes['code'] = strtoupper(trim((string) $value));
    }

    /** Pembayaran yang memakai kode ini (semua toko). Pending ikut dihitung sebagai "terpakai". */
    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class)->withoutGlobalScope('store');
    }

    public function usedCount(): int
    {
        return $this->payments()->whereIn('status', ['pending', 'confirmed'])->count();
    }

    /** Potongan (Rp) untuk harga tertentu. */
    public function discountFor(int $price): int
    {
        $discount = $this->discount_type === self::PERCENT
            ? (int) floor($price * min(100, $this->discount_value) / 100)
            : $this->discount_value;

        if ($this->discount_type === self::PERCENT && $this->max_discount) {
            $discount = min($discount, $this->max_discount);
        }

        return max(0, min($discount, $price));
    }

    public function appliesToPlan(int $planId): bool
    {
        return empty($this->plan_ids) || in_array($planId, array_map('intval', $this->plan_ids), true);
    }

    /** "Diskon 20% (maks Rp50.000)" / "Potongan Rp25.000" */
    public function label(): string
    {
        $rp = fn ($n) => 'Rp' . number_format((float) $n, 0, ',', '.');

        return $this->discount_type === self::PERCENT
            ? 'Diskon ' . $this->discount_value . '%' . ($this->max_discount ? ' (maks ' . $rp($this->max_discount) . ')' : '')
            : 'Potongan ' . $rp($this->discount_value);
    }

    /** aktif | terjadwal | berakhir | habis | nonaktif */
    public function status(): string
    {
        $today = now()->startOfDay();
        if (! $this->is_active) {
            return 'nonaktif';
        }
        if ($this->starts_at && $today->lt($this->starts_at)) {
            return 'terjadwal';
        }
        if ($this->ends_at && $today->gt($this->ends_at)) {
            return 'berakhir';
        }
        if ($this->max_uses !== null && $this->usedCount() >= $this->max_uses) {
            return 'habis';
        }

        return 'aktif';
    }
}
