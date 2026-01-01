<?php

namespace App\Services;

use App\Models\PortfolioCertification;
use App\Models\PortfolioEducation;
use App\Models\PortfolioExperience;
use App\Models\PortfolioProfile;
use App\Models\PortfolioProject;

class PortfolioExportService
{
    public function build(int $userId): array
    {
        $profile = PortfolioProfile::where('user_id', $userId)->first();
        $experiences = PortfolioExperience::where('user_id', $userId)->orderBy('sort_order')->orderBy('id')->get();
        $educations = PortfolioEducation::where('user_id', $userId)->orderBy('sort_order')->orderBy('id')->get();
        $skills = \App\Models\PortfolioSkill::where('user_id', $userId)->orderBy('sort_order')->orderBy('id')->get();
        $certs = PortfolioCertification::where('user_id', $userId)->orderBy('sort_order')->orderBy('id')->get();
        $projects = PortfolioProject::where('user_id', $userId)->orderBy('sort_order')->orderBy('id')->get();

        $basicsLinks = [];
        if ($profile?->website_url) $basicsLinks[] = ['label' => 'Site', 'url' => $profile->website_url];
        if ($profile?->github_url) $basicsLinks[] = ['label' => 'GitHub', 'url' => $profile->github_url];
        if ($profile?->linkedin_url) $basicsLinks[] = ['label' => 'LinkedIn', 'url' => $profile->linkedin_url];

        return [
            'basics' => [
                'name' => $profile?->email_public ?? 'Owner',
                'headline' => $profile?->headline ?? '',
                'location' => $profile?->location ?? '',
                'summary' => $profile?->summary ?? '',
                'aboutMe' => $profile?->about_me ?? '',
                'avatarUrl' => $profile?->avatar_url ?? null,
                'email' => $profile?->email_public ?? null,
                'links' => $basicsLinks,
            ],
            'highlights' => [],
            'skills' => $skills->pluck('name')->values()->all(),
            'experience' => $experiences->map(function ($e) {
                $bullets = [];
                if ($e->description) {
                    $bullets = array_values(array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", $e->description))));
                }
                return [
                    'company' => (string) $e->company,
                    'title' => (string) $e->title,
                    'location' => $e->location,
                    'start' => $e->start_date ? (string) $e->start_date : '',
                    'end' => $e->end_date ? (string) $e->end_date : null,
                    'bullets' => $bullets,
                ];
            })->values()->all(),
            'projects' => $projects->map(function ($p) {
                $links = [];
                if ($p->repo_url) $links[] = ['label' => 'GitHub', 'url' => $p->repo_url];
                if ($p->live_url) $links[] = ['label' => 'Site', 'url' => $p->live_url];
                $stack = [];
                if ($p->tech_stack) {
                    $stack = array_values(array_filter(array_map('trim', explode(',', $p->tech_stack))));
                }
                return [
                    'name' => (string) $p->name,
                    'tagline' => (string) ($p->description ?? ''),
                    'stack' => $stack,
                    'links' => $links,
                ];
            })->values()->all(),
            'certifications' => $certs->map(function ($c) {
                return [
                    'name' => (string) $c->name,
                    'issuer' => (string) ($c->issuer ?? ''),
                    'issued' => $c->issue_date ? (string) $c->issue_date : '',
                    'expires' => $c->expire_date ? (string) $c->expire_date : null,
                    'url' => $c->credential_url ?: null,
                ];
            })->values()->all(),
            'education' => $educations->map(function ($ed) {
                return [
                    'school' => (string) $ed->school,
                    'degree' => (string) ($ed->degree ?? ''),
                    'start' => $ed->start_date ? (string) $ed->start_date : null,
                    'end' => $ed->end_date ? (string) $ed->end_date : null,
                    'url' => null,
                ];
            })->values()->all(),
        ];
    }
}
