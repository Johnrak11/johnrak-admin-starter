<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PortfolioExperience extends Model
{
    use HasFactory;

    protected $table = 'portfolio_experiences';

    protected $fillable = ['user_id', 'company', 'title', 'location', 'employment_type', 'start_date', 'end_date', 'is_current', 'description', 'sort_order'];

    protected $hidden = [];
}
