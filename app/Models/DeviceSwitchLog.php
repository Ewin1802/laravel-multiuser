<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceSwitchLog extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'from_store_device_id', 'to_store_device_id', 'user_id', 'source', 'ip_address',
    ];

    public function fromDevice(): BelongsTo
    {
        return $this->belongsTo(StoreDevice::class, 'from_store_device_id');
    }

    public function toDevice(): BelongsTo
    {
        return $this->belongsTo(StoreDevice::class, 'to_store_device_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
