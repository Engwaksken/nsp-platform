<?php

/*
| Venture Builder (owner module M02).
| The owner requires six sequential modules and exactly 24 steps in every
| published curriculum. Titles and content live in the database; this file
| only holds the structural rules and fixed vocabularies.
*/

return [
    // A curriculum can only be published with exactly this many ACTIVE modules and steps.
    'modules' => 6,
    'steps' => 24,

    // The ten owner-defined VGI dimensions (keys are stable; labels are the owner's exact names).
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

    // Structured field types a step can ask for.
    'field_types' => ['text', 'textarea', 'number', 'select', 'url', 'date', 'list'],

    'evidence_requirements' => ['none', 'optional', 'required'],

    // Evidence files: private disk, checked by content type, not by name.
    'evidence' => [
        'disk' => 'local',
        'max_kb' => 20480,
        'extensions' => 'pdf,docx,xlsx,xls,pptx,csv,txt,png,jpg,jpeg',
        'mime_types' => [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.ms-excel', 'text/csv', 'text/plain', 'image/png', 'image/jpeg',
        ],
    ],
];
