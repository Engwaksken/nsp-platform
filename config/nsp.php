<?php

return [
    /*
     * Feature flags. These are defaults; an admin can override them in the
     * feature_flags table. Modules for later phases stay off until they are
     * built and approved.
     */
    'features' => [
        'oidc' => ['default' => (bool) env('NSP_OIDC_ENABLED', false), 'label' => 'Single sign-on (OIDC)', 'description' => 'Sign in through the shared Hive identity provider.'],
        'local_login' => ['default' => (bool) env('NSP_LOCAL_LOGIN', true), 'label' => 'Email and password login', 'description' => 'Local fallback login for development and admin until the shared IdP is live.'],
        'registry' => ['default' => false, 'label' => 'Startup registry', 'description' => 'Canonical startup profile from the Hive Startup Registry (Phase 2).'],
        'ims_integration' => ['default' => false, 'label' => 'IMS applications and programs', 'description' => 'Application and program summaries from Hive IMS (Phase 3).'],
        'vgi' => ['default' => false, 'label' => 'Growth and VGI', 'description' => 'VGI assessments and growth plans (Phase 4).'],
        'funding' => ['default' => false, 'label' => 'Funding and investors', 'description' => 'Funding needs, investors and data rooms (Phase 5).'],
        'experts' => ['default' => false, 'label' => 'Experts and mentors', 'description' => 'Expert profiles, bookings and mentorship (Phase 5).'],
        'talent' => ['default' => false, 'label' => 'Talent', 'description' => 'Vacancies, candidates and hiring (Phase 5).'],
        'opportunities' => ['default' => false, 'label' => 'Opportunities and marketplace', 'description' => 'Grants, events, competitions and marketplace (Phase 5).'],
        'ai_assistant' => ['default' => false, 'label' => 'AI assistant', 'description' => 'Optional AI help with provenance and consent (Phase 6).'],
    ],

    'oidc' => [
        'issuer' => env('OIDC_ISSUER'),
        'client_id' => env('OIDC_CLIENT_ID'),
        'client_secret' => env('OIDC_CLIENT_SECRET'),
        'redirect_uri' => env('OIDC_REDIRECT_URI'),
        'post_logout_redirect_uri' => env('OIDC_POST_LOGOUT_REDIRECT_URI'),
        'scopes' => env('OIDC_SCOPES', 'openid profile email'),
        // Never link an IdP identity to an existing local account by email unless the owner approves it.
        'link_verified_email' => (bool) env('OIDC_LINK_VERIFIED_EMAIL', false),
        'leeway_seconds' => 60,
        'cache_seconds' => 3600,
    ],

    'mfa' => [
        // Admin roles must use two-factor sign-in (authenticator app). Only switch off for local development.
        'enforce' => (bool) env('NSP_MFA_ENFORCE', true),
    ],

    // Version of the consent wording shown on the contact form; bump when the text changes.
    'contact_consent_version' => '2026-11-04',
];
