<?php

/*
| Challenge Exchange (owner module M05).
| Category, issuer and evidence lists are a PROVISIONAL starting taxonomy pending owner approval
| (see docs/owner-alignment/modules/m05-challenge-exchange.md). Editing these lists does not need a migration.
*/
return [
    'categories' => [
        'innovation' => 'Innovation challenge',
        'market' => 'Market gap',
        'policy' => 'Policy and regulation',
        'public_service' => 'Public service delivery',
        'supply_chain' => 'Supply chain and logistics',
        'climate' => 'Climate and environment',
        'inclusion' => 'Inclusion and access',
        'other' => 'Other',
    ],

    'issuer_types' => [
        'government' => 'Government or public agency',
        'corporate' => 'Corporate',
        'ngo' => 'NGO or development partner',
        'academic' => 'University or research institute',
        'eso' => 'Enterprise support organisation',
        'community' => 'Community or individual',
        'hive' => 'Hive',
    ],

    'priorities' => [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'critical' => 'Critical',
    ],

    'evidence_types' => [
        'research' => 'Research paper or study',
        'statistics' => 'Official statistics',
        'survey' => 'Survey or field data',
        'policy' => 'Policy document',
        'news' => 'News or media report',
        'testimony' => 'Stakeholder testimony',
        'other' => 'Other',
    ],

    // Why a reviewer rejects a submission (reason is required with the comment).
    'rejection_reasons' => [
        'insufficient_evidence' => 'Insufficient evidence',
        'duplicate' => 'Duplicate of an existing challenge',
        'out_of_scope' => 'Out of scope for the Challenge Exchange',
        'unclear' => 'Problem not clearly described',
        'other' => 'Other',
    ],

    'per_page' => 12,

    // Private evidence documents (stored on the private "local" disk).
    'evidence_max_kb' => 10240,
    'evidence_extensions' => 'pdf,docx,xlsx,csv,png,jpg,jpeg',
    'evidence_mime_types' => [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv', 'text/plain', 'image/png', 'image/jpeg',
    ],

    /*
    | Cross-links shown on a challenge page. A key becomes a link only when the named route exists
    | and can be generated with [param => key]; otherwise it is shown as plain text.
    */
    'links' => [
        'vgi_dimension_keys' => ['label' => 'Growth Index dimensions', 'route' => 'vgi.dimensions.show', 'param' => 'dimension'],
        'builder_step_keys' => ['label' => 'Venture Builder steps', 'route' => 'workspace.builder.step', 'param' => 'step'],
        'expert_categories' => ['label' => 'Expert categories', 'route' => 'experts.categories.show', 'param' => 'category'],
    ],
];
