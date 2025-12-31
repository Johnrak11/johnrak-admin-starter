<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PortfolioProfile extends Model
{
    use HasFactory;

    protected $table = 'portfolio_profiles';

    protected $fillable = ['user_id', 'headline', 'summary', 'about_me', 'location', 'email_public', 'phone_public', 'website_url', 'github_url', 'linkedin_url', 'avatar_url'];

    protected $hidden = [];
}
