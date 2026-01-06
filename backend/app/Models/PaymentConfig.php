<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'bakong_id',
        'merchant_name',
        'merchant_city',
        'merchant_phone',
        'merchant_email',
        'merchant_address',
        'provider_merchant_info',
        'webhook_secret',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'provider_merchant_info' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
