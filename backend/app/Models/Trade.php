<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trade extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'symbol',
        'entry',
        'tp1',
        'tp2',
        'sl',
        'status',
        'triggered_at',
    ];
}
