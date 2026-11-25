<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris = satu kunjungan halaman depan.
 */
class PageVisit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ip', 'path', 'referer', 'user_agent', 'is_bot', 'visited_at',
    ];

    protected $casts = [
        'is_bot' => 'boolean',
        'visited_at' => 'datetime',
    ];
}
