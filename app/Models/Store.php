<?php

namespace App\Models;

use App\Support\Subscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * TOKO (tenant). Masa aktif dihitung oleh App\Support\Subscription.
 */
class Store extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'name', 'phone', 'address', 'logo', 'receipt_footer', 'feature_draft_order',
        'bank_name', 'bank_account', 'bank_holder',
    ];

    protected $casts = [
        'feature_draft_order' => 'boolean',
        'approved_at' => 'datetime',
        'trial_started_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'paid_until' => 'datetime',
        'grace_days' => 'integer',
    ];

    public function owner(): HasOne
    {
        return $this->hasOne(User::class)->oldestOfMany();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(StoreDevice::class);
    }

    public function activeDevice(): BelongsTo
    {
        return $this->belongsTo(StoreDevice::class, 'active_device_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class)->latest();
    }

    public function tamperLogs(): HasMany
    {
        return $this->hasMany(ClockTamperLog::class)->latest();
    }

    public function switchLogs(): HasMany
    {
        return $this->hasMany(DeviceSwitchLog::class)->latest();
    }

    /** Ringkasan status langganan menurut jam server. */
    public function subscription(): Subscription
    {
        return Subscription::for($this);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? asset('storage/' . $this->logo) : null;
    }
}
