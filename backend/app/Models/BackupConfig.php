<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupConfig extends Model
{
    protected $fillable = [
        'user_id', 'enabled', 'provider',
        's3_access_key', 's3_secret', 's3_region', 's3_bucket', 's3_endpoint', 's3_path_prefix'
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}

