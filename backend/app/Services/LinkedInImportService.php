<?php

namespace App\Services;

use App\Models\PortfolioCertification;
use App\Models\PortfolioEducation;
use App\Models\PortfolioExperience;
use App\Models\PortfolioProfile;
use App\Models\PortfolioSkill;

class LinkedInImportService
{
    public function import(int $userId, array $payload): array
    {
        $result = [
            'profile' => 0,
            'experiences' => 0,
            'educations' => 0,
            'skills' => 0,
            'certifications' => 0,
        ];

        if (isset($payload['profile']) && is_array($payload['profile'])) {
            $profile = PortfolioProfile::firstOrCreate(['user_id' => $userId]);
            $profile->fill(array_intersect_key($payload['profile'], array_flip([
                'headline', 'summary', 'location', 'website_url', 'github_url', 'linkedin_url',
            ])));
            $profile->save();
            $result['profile'] = 1;
        }

        if (! empty($payload['experiences']) && is_array($payload['experiences'])) {
            PortfolioExperience::where('user_id', $userId)->delete();
            foreach ($payload['experiences'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $row['user_id'] = $userId;
                PortfolioExperience::create($this->only($row, [
                    'user_id', 'company', 'title', 'location', 'employment_type', 'start_date', 'end_date', 'is_current', 'description', 'sort_order',
                ]));
                $result['experiences']++;
            }
        }

        if (! empty($payload['educations']) && is_array($payload['educations'])) {
            PortfolioEducation::where('user_id', $userId)->delete();
            foreach ($payload['educations'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $row['user_id'] = $userId;
                PortfolioEducation::create($this->only($row, [
                    'user_id', 'school', 'degree', 'field_of_study', 'start_date', 'end_date', 'description', 'sort_order',
                ]));
                $result['educations']++;
            }
        }

        if (! empty($payload['skills']) && is_array($payload['skills'])) {
            PortfolioSkill::where('user_id', $userId)->delete();
            foreach ($payload['skills'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $row['user_id'] = $userId;
                PortfolioSkill::create($this->only($row, [
                    'user_id', 'name', 'level', 'sort_order',
                ]));
                $result['skills']++;
            }
        }

        if (! empty($payload['certifications']) && is_array($payload['certifications'])) {
            PortfolioCertification::where('user_id', $userId)->delete();
            foreach ($payload['certifications'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $row['user_id'] = $userId;
                PortfolioCertification::create($this->only($row, [
                    'user_id', 'name', 'issuer', 'issue_date', 'expire_date', 'credential_id', 'credential_url', 'sort_order',
                ]));
                $result['certifications']++;
            }
        }

        return $result;
    }

    private function only(array $data, array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            if (array_key_exists($k, $data)) {
                $out[$k] = $data[$k];
            }
        }

        return $out;
    }
}
