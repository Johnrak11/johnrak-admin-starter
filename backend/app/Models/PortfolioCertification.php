<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PortfolioCertification extends Model
{
    use HasFactory;

    protected $table = 'portfolio_certifications';

    protected $fillable = ['user_id', 'name', 'issuer', 'issue_date', 'expire_date', 'credential_id', 'credential_url', 'sort_order'];

    protected $hidden = [];
}
