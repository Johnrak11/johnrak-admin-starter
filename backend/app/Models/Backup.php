<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'disk',
        'path',
        'size_bytes',
        'is_successful',
    ];

    protected $casts = [
        'is_successful' => 'boolean',
        'size_bytes' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
