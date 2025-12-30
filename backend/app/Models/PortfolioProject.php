<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PortfolioProject extends Model
{
    use HasFactory;

    protected $table = 'portfolio_projects';

    protected $fillable = ['user_id', 'name', 'slug', 'description', 'tech_stack', 'repo_url', 'live_url', 'start_date', 'end_date', 'is_featured', 'sort_order'];

    protected $hidden = [];
}
