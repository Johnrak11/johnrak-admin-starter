<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PortfolioAttachment extends Model
{
    use HasFactory;

    protected $table = 'portfolio_attachments';

    protected $fillable = ['user_id', 'category', 'title', 'original_name', 'mime_type', 'size_bytes', 'storage_path', 'sha256'];

    protected $hidden = ['storage_path', 'sha256'];
}
