<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;

/** HP yang pernah login ke sebuah toko. */
class StoreDevice extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'device_id', 'device_name', 'platform', 'app_version',
        'first_seen_at', 'last_seen_at', 'last_sync_at', 'activated_at', 'deactivated_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
    ];

    public function isActiveFor(Store $store): bool
    {
        return (int) $store->active_device_id === (int) $this->id;
    }
}
