<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClockTamperLog extends Model
{
    use BelongsToStore;

    public const KINDS = [
        'clock_rollback' => 'Jam HP dimundurkan',
        'clock_jump' => 'Jam HP melompat tidak wajar',
        'offline_limit' => 'Melewati batas offline',
        'license_expired' => 'Transaksi saat masa aktif habis',
    ];

    protected $fillable = [
        'store_device_id', 'device_id', 'client_uuid', 'kind',
        'device_time', 'trusted_time', 'drift_seconds', 'details', 'reported_at',
    ];

    protected $casts = [
        'details' => 'array',
        'reported_at' => 'datetime',
        'drift_seconds' => 'integer',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(StoreDevice::class, 'store_device_id');
    }
}
