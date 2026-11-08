<?php

/*
| M01 — Organizations as tenants (universities, ESOs, investors, development
| partners, corporates, government). Organizations never own startups: they see
| a startup only through an active, unexpired relationship the startup's owners
| or founders approved, and only the scopes granted in it.
*/
return [
    'types' => [
        'university' => 'University',
        'eso' => 'Entrepreneurship support organization (ESO)',
        'investor' => 'Investor',
        'development_partner' => 'Development partner',
        'corporate' => 'Corporate innovation team',
        'government' => 'Government agency',
        'other' => 'Other',
    ],

    'statuses' => [
        'pending' => 'Pending verification',
        'active' => 'Active',
        'suspended' => 'Suspended',
    ],

    'roles' => [
        'org_admin' => 'Organization admin',
        'manager' => 'Manager',
        'analyst' => 'Analyst',
        'member' => 'Member',
    ],

    // Who may do what inside an organization.
    'role_rights' => [
        'manage_members' => ['org_admin'],
        'request_access' => ['org_admin', 'manager'],
        'view_portfolio' => ['org_admin', 'manager', 'analyst'],
    ],

    'purposes' => [
        'portfolio' => 'Portfolio support',
        'program' => 'Programme participation',
        'investment' => 'Investment consideration',
        'mentoring' => 'Mentoring',
        'other' => 'Other',
    ],

    // The fixed list of data scopes a startup can grant. Nothing outside these is ever shown.
    'scopes' => [
        'profile_summary' => ['label' => 'Profile summary', 'description' => 'Name, country, year founded, sector, team size, website and short description. Never revenue or contact details.'],
        'vgi_summary' => ['label' => 'Growth assessment summary', 'description' => 'Latest submitted growth (VGI) score and band, without answers or evidence notes.'],
        'builder_progress' => ['label' => 'Venture Builder progress', 'description' => 'How far the startup is through the Venture Builder steps.'],
        'funding_summary' => ['label' => 'Funding summary', 'description' => 'Whether the startup is raising, how much and in which instruments. Never the pitch, data room or investor list.'],
        'programme_outcomes' => ['label' => 'Programme outcomes', 'description' => 'Programmes and cohorts the startup applied to or joined, and their status.'],
    ],

    /*
    | Other modules can supply a scope summary without editing this module's code:
    | map the scope to a class with `summary(App\Models\Registry\Startup $startup): ?array`
    | returning a list of [label, value] rows (strings only, no private data).
    */
    'scope_providers' => [
        'builder_progress' => null, // set by the Venture Builder module (M02) when it provides a summary
    ],

    'invite_days' => 7,

    // Suggested length of an approved grant; the startup can choose another date or none.
    'default_grant_days' => 365,
];
