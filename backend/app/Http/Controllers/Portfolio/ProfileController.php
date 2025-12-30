<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\Controller;
use App\Models\PortfolioProfile;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $profile = PortfolioProfile::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['headline' => '', 'summary' => '']
        );

        return response()->json(['profile' => $profile]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'headline' => ['nullable', 'string', 'max:200'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:200'],
            'email_public' => ['nullable', 'email', 'max:255'],
            'phone_public' => ['nullable', 'string', 'max:50'],
            'website_url' => ['nullable', 'url', 'max:500'],
            'github_url' => ['nullable', 'url', 'max:500'],
            'linkedin_url' => ['nullable', 'url', 'max:500'],
            'avatar_url' => ['nullable', 'url', 'max:500'],
        ]);

        $profile = PortfolioProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $profile->fill($validated)->save();

        return response()->json(['profile' => $profile]);
    }
}
