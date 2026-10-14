<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    use HasFactory, BelongsToStore;

    protected $fillable = [
        'name',
        'description',
        'type',
        'value',
        'status',
        'expired_date'
    ];
}
