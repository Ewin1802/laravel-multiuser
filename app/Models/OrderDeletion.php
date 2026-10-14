<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;

/**
 * Log pencatat order yang sudah dihapus permanen dari tabel `orders`.
 * Dipakai oleh endpoint sync (Api\OrderSyncController) supaya
 * aplikasi kasir tahu order mana yang perlu dibuang dari SQLite
 * lokalnya. Lihat komentar lengkap di migration-nya.
 */
class OrderDeletion extends Model
{
    use BelongsToStore;

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'client_order_id',
        'deleted_at',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];
}
