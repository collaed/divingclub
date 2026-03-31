<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class GuideController extends Controller
{
    private array $sections = [
        'overview' => 'System Overview',
        'first-steps' => 'First Steps After Deployment',
        'members' => 'Managing Members',
        'minors' => 'Minors & Parental Consent',
        'seasons-events' => 'Seasons & Events',
        'dive-groups' => 'Dive Group Planner',
        'medical' => 'Medical Compliance',
        'payments' => 'Payments & Fees',
        'equipment' => 'Equipment Inventory',
        'content' => 'CMS, Classifieds & Documents',
        'email' => 'Email System',
        'voting' => 'Voting System',
        'partnerships' => 'Inter-Club Partnerships',
        'social-media' => 'Social Media Auto-Publish',
        'gdpr' => 'GDPR & Privacy',
        'audit-log' => 'Audit Log',
        'settings' => 'Settings & Configuration',
        'api-keys' => 'API Keys & OAuth Setup',
        'backup' => 'Backups & Maintenance',
        'newsletters' => 'Newsletters',
        'system' => 'System & Monitoring',
        'troubleshooting' => 'Troubleshooting',
    ];

    public function index()
    {
        return view('admin.guide.index', ['sections' => $this->sections]);
    }

    public function show(string $section)
    {
        abort_unless(array_key_exists($section, $this->sections), 404);
        $keys = array_keys($this->sections);
        $idx = array_search($section, $keys);
        $prev = $idx > 0 ? ['slug' => $keys[$idx - 1], 'title' => $this->sections[$keys[$idx - 1]]] : null;
        $next = $idx < count($keys) - 1 ? ['slug' => $keys[$idx + 1], 'title' => $this->sections[$keys[$idx + 1]]] : null;

        return view("admin.guide.{$section}", [
            'sections' => $this->sections,
            'current' => $section,
            'title' => $this->sections[$section],
            'prev' => $prev,
            'next' => $next,
        ]);
    }
}
