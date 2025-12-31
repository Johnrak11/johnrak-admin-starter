<?php

namespace Database\Seeders;

use App\Models\PortfolioCertification;
use App\Models\PortfolioEducation;
use App\Models\PortfolioExperience;
use App\Models\PortfolioProfile;
use App\Models\PortfolioProject;
use App\Models\PortfolioSkill;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PortfolioFromJsonSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('JOHNRAK_ADMIN_EMAIL', 'admin@johnrak.online');
        $user = User::where('email', $email)->firstOrFail();

        $path = base_path('database/data/profile.json');
        $raw = file_get_contents($path);
        $data = json_decode($raw, true) ?: [];

        // profile
        $profile = PortfolioProfile::firstOrCreate(['user_id' => $user->id]);
        $links = collect($data['basics']['links'] ?? []);
        $getLink = fn($label) => optional($links->firstWhere('label', $label))['url'] ?? null;
        $profile->fill([
            'headline' => $data['basics']['headline'] ?? null,
            'summary' => $data['basics']['summary'] ?? null,
            'about_me' => $data['basics']['aboutMe'] ?? null,
            'location' => $data['basics']['location'] ?? null,
            'email_public' => $data['basics']['email'] ?? null,
            'website_url' => $getLink('Site') ?? null,
            'github_url' => $getLink('GitHub') ?? null,
            'linkedin_url' => $getLink('LinkedIn') ?? null,
            'avatar_url' => $data['basics']['avatarUrl'] ?? null,
        ])->save();

        // experiences
        PortfolioExperience::where('user_id', $user->id)->delete();
        $i = 0;
        foreach ($data['experience'] ?? [] as $row) {
            $start = $this->toDate($row['start'] ?? null);
            $end = $this->toDate($row['end'] ?? null);
            PortfolioExperience::create([
                'user_id' => $user->id,
                'company' => $row['company'] ?? '',
                'title' => $row['title'] ?? '',
                'location' => $row['location'] ?? null,
                'employment_type' => null,
                'start_date' => $start,
                'end_date' => $end,
                'is_current' => $end ? false : true,
                'description' => implode("\n", $row['bullets'] ?? []),
                'sort_order' => $i++,
            ]);
        }

        // educations
        PortfolioEducation::where('user_id', $user->id)->delete();
        $i = 0;
        foreach ($data['education'] ?? [] as $row) {
            PortfolioEducation::create([
                'user_id' => $user->id,
                'school' => $row['school'] ?? '',
                'degree' => $row['degree'] ?? null,
                'field_of_study' => null,
                'start_date' => $this->toDate($row['start'] ?? null),
                'end_date' => $this->toDate($row['end'] ?? null),
                'description' => null,
                'sort_order' => $i++,
            ]);
        }

        // skills
        PortfolioSkill::where('user_id', $user->id)->delete();
        $i = 0;
        foreach ($data['skills'] ?? [] as $name) {
            PortfolioSkill::create([
                'user_id' => $user->id,
                'name' => (string) $name,
                'level' => null,
                'sort_order' => $i++,
            ]);
        }

        // certifications
        PortfolioCertification::where('user_id', $user->id)->delete();
        $i = 0;
        foreach ($data['certifications'] ?? [] as $row) {
            PortfolioCertification::create([
                'user_id' => $user->id,
                'name' => $row['name'] ?? '',
                'issuer' => $row['issuer'] ?? null,
                'issue_date' => $this->toDate($row['issued'] ?? null),
                'expire_date' => $this->toDate($row['expires'] ?? null),
                'credential_id' => null,
                'credential_url' => $row['url'] ?? null,
                'sort_order' => $i++,
            ]);
        }

        // projects
        PortfolioProject::where('user_id', $user->id)->delete();
        $i = 0;
        foreach ($data['projects'] ?? [] as $row) {
            $links = collect($row['links'] ?? []);
            $repo = optional($links->firstWhere('label', 'GitHub'))['url'] ?? null;
            $live = optional($links->firstWhere('label', 'Site'))['url']
                ?? optional($links->firstWhere('label', 'Admin'))['url'] ?? null;
            PortfolioProject::create([
                'user_id' => $user->id,
                'name' => $row['name'] ?? '',
                'slug' => Str::slug($row['name'] ?? ''),
                'description' => $row['tagline'] ?? null,
                'tech_stack' => implode(', ', $row['stack'] ?? []),
                'repo_url' => $repo,
                'live_url' => $live,
                'start_date' => null,
                'end_date' => null,
                'is_featured' => 0,
                'sort_order' => $i++,
            ]);
        }
    }

    private function toDate(?string $in): ?string
    {
        if (! $in) return null;
        if (strtolower($in) === 'present') return null;
        if (preg_match('/^\d{4}-\d{2}$/', $in)) {
            return $in . '-01';
        }
        if (preg_match('/^\d{4}$/', $in)) {
            return $in . '-01-01';
        }
        return $in;
    }
}
