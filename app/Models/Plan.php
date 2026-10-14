<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = ['code', 'name', 'duration_months', 'price', 'is_active'];

    protected $casts = [
        'duration_months' => 'integer',
        'price' => 'integer',
        'is_active' => 'boolean',
    ];
}
