<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PortfolioEducation extends Model
{
    use HasFactory;

    protected $table = 'portfolio_educations';

    protected $fillable = ['user_id', 'school', 'degree', 'field_of_study', 'start_date', 'end_date', 'description', 'sort_order'];

    protected $hidden = [];
}
