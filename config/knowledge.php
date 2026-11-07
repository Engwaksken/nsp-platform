<?php

/*
| Knowledge Hub (owner module M06).
| Lists marked "provisional" are working defaults until the owner confirms them
| (see docs/owner-alignment/modules/m06-knowledge-hub.md).
*/
return [

    'resource_types' => [
        'research_publication' => 'Research publication',
        'market_intelligence' => 'Market intelligence',
        'policy_brief' => 'Policy brief',
        'policy_document' => 'Policy document',
        'template' => 'Template',
        'toolkit' => 'Toolkit',
        'learning_resource' => 'Learning resource',
        'case_study' => 'Case study',
        'practical_guide' => 'Practical guide',
        'business_development' => 'Business development material',
    ],

    // Who may open a resource. "startups" = signed-in users with an active startup membership.
    'visibilities' => [
        'public' => 'Everyone (public)',
        'members' => 'Signed-in users',
        'startups' => 'Startup teams (active startup membership)',
        'staff' => 'Hive staff only',
    ],

    'audiences' => [
        'founders' => 'Founders',
        'investors' => 'Investors',
        'universities' => 'Universities',
        'esos' => 'Ecosystem support organisations',
        'experts' => 'Experts',
    ],

    // Provisional stage labels: the owner has not defined venture stages yet.
    'stages' => [
        'idea' => 'Idea',
        'validation' => 'Validation',
        'early_traction' => 'Early traction',
        'growth' => 'Growth',
        'scale' => 'Scale',
    ],

    // The ten owner VGI dimensions (M03), keyed for tagging resources.
    'vgi_dimensions' => [
        'problem_clarity' => 'Problem Clarity',
        'customer_validation' => 'Customer Validation',
        'market_understanding' => 'Market Understanding',
        'product_readiness' => 'Product Readiness',
        'business_model_strength' => 'Business Model Strength',
        'market_traction' => 'Market Traction',
        'team_capacity' => 'Team Capacity',
        'financial_readiness' => 'Financial Readiness',
        'scalability' => 'Scalability',
        'commercialization_readiness' => 'Commercialization Readiness',
    ],

    // Licences. "linked_only" licences must point to the source (external_url) and may not carry an uploaded copy.
    'licenses' => [
        'hive-owned' => ['label' => 'Hive-owned', 'linked_only' => false],
        'cc-by' => ['label' => 'Creative Commons Attribution (CC BY)', 'linked_only' => false],
        'cc-by-sa' => ['label' => 'Creative Commons Attribution-ShareAlike (CC BY-SA)', 'linked_only' => false],
        'cc-by-nc' => ['label' => 'Creative Commons Attribution-NonCommercial (CC BY-NC)', 'linked_only' => false],
        'cc0' => ['label' => 'Public domain dedication (CC0)', 'linked_only' => false],
        'partner-licensed' => ['label' => 'Licensed to Hive by the publisher', 'linked_only' => false],
        'all-rights-reserved-linked-only' => ['label' => 'All rights reserved (linked, not hosted)', 'linked_only' => true],
    ],

    'uploads' => [
        'disk' => 'local', // private disk; files are only served through knowledge.download
        'max_kb' => (int) env('NSP_KNOWLEDGE_MAX_KB', 20480),
        // Extension => MIME types accepted, checked from the file contents.
        'allowed' => [
            'pdf' => ['application/pdf'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
            'csv' => ['text/csv', 'text/plain'],
            'txt' => ['text/plain'],
            'png' => ['image/png'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
        ],
    ],

    'workflow' => [
        // Four-eyes: the uploader of a resource may not publish it themselves (super admins excepted).
        'four_eyes' => (bool) env('NSP_KNOWLEDGE_FOUR_EYES', true),
        // Default months until a published resource is due for content review.
        'review_months' => 12,
    ],

    'recent_views_kept' => 20,

    'semantic' => [
        // Off by default: no embeddings are generated and no external vendor is called.
        'enabled' => (bool) env('NSP_KNOWLEDGE_SEMANTIC', false),
        // Class implementing App\Knowledge\Search\SemanticRanker. None ships with the platform.
        'ranker' => env('NSP_KNOWLEDGE_SEMANTIC_RANKER'),
        'index_name' => 'knowledge-v1',
    ],
];
