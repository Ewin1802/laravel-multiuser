<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Token FCM satu HP. Tidak memakai filter toko (dipakai lintas toko oleh server). */
class PushToken extends Model
{
    public const APP_KASIR = 'kasir';
    public const APP_ADMIN = 'admin';

    protected $fillable = [
        'user_id', 'store_id', 'app', 'access_token_id', 'device_id', 'token_hash', 'token', 'platform', 'app_version', 'last_seen_at',
    ];

    protected $hidden = ['token'];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
