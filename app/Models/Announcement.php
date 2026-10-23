<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pengumuman push notification dari superadmin. */
class Announcement extends Model
{
    public const TARGET_ALL = 'all';
    public const TARGET_STORE = 'store';

    protected $fillable = ['title', 'body', 'target', 'store_id', 'sent_by', 'status', 'message_id', 'error'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
