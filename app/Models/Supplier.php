<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use BelongsToStore;

    protected $fillable = ['name', 'phone', 'address', 'notes', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function stockIns(): HasMany
    {
        return $this->hasMany(StockIn::class);
    }
}
