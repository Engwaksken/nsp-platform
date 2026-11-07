<?php

/*
| M08 Capital Exchange (owner alignment, Nov 2026).
|
| Everything here is PROVISIONAL: labels, checklist templates and scopes are
| safe defaults until the owner decides investor diligence permissions and
| legal obligations (directive §11 decision 6). Matching and readiness are
| discovery aids only: no eligibility decision, no finance approval, no
| automated investment decision and no fund transfers.
*/
return [
    'provisional' => true,

    // Instruments a startup can seek. Keys match the existing funding module (InvestorProfile::INSTRUMENTS).
    'instruments' => [
        'grant' => 'Grant',
        'debt' => 'Loan or debt',
        'equity' => 'Equity',
        'convertible' => 'Convertible note or SAFE',
        'revenue_share' => 'Revenue-based financing',
    ],

    // The round a startup is raising (not the VGI growth stage).
    'round_stages' => [
        'pre_seed' => 'Pre-seed',
        'seed' => 'Seed',
        'series_a' => 'Series A',
        'series_b_plus' => 'Series B or later',
        'bridge' => 'Bridge',
        'non_dilutive' => 'Non-dilutive (grant or debt only)',
    ],

    // Use of funds breakdown categories (percent of the amount raised).
    'use_of_funds_categories' => [
        'product' => 'Product and technology',
        'hiring' => 'Hiring',
        'sales_marketing' => 'Sales and marketing',
        'operations' => 'Operations',
        'equipment' => 'Equipment and assets',
        'working_capital' => 'Working capital',
        'other' => 'Other',
    ],

    // Investor pipeline stage labels, in order. Keys are stored; labels can change.
    'pipeline_stages' => [
        'sourced' => 'Sourced',
        'screened' => 'Screened',
        'contacted' => 'Contacted',
        'diligence' => 'Diligence',
        'decision' => 'Decision',
        'committed' => 'Committed',
        'closed' => 'Closed',
        'declined' => 'Declined',
    ],

    // Owner decision: should founders see their position in an investor's pipeline? Default: no (private to the investor).
    'share_pipeline_with_startup' => false,

    // What a founder can share with one investor. Default: nothing.
    'share_scopes' => [
        'vgi_summary' => ['label' => 'Growth Index (VGI) summary', 'hint' => 'Overall result, stage and rubric version. Never your answers or evidence.'],
        'builder_progress' => ['label' => 'Venture Builder progress', 'hint' => 'Steps and modules completed. Never your answers or documents.'],
        'traction' => ['label' => 'Traction summary', 'hint' => 'The traction summary you write on this page.'],
        'financials' => ['label' => 'Financial summary and use of funds', 'hint' => 'Your financial summary, use-of-funds breakdown and the titles of financial documents (downloads still need data-room access).'],
    ],

    'share_default_days' => 90,
    'share_max_days' => 365,

    // Data-room categories a complete investor data room should contain (readiness check).
    'data_room_checklist' => ['pitch_deck', 'financials', 'projections', 'legal', 'traction'],

    'diligence_statuses' => [
        'requested' => 'Requested',
        'provided' => 'Provided',
        'accepted' => 'Accepted',
        'not_applicable' => 'Not applicable',
    ],

    'diligence_categories' => [
        'corporate' => 'Corporate and legal',
        'financial' => 'Financial',
        'commercial' => 'Commercial and market',
        'product' => 'Product and technology',
        'team' => 'Team and governance',
        'impact' => 'Impact and ESG',
        'other' => 'Other',
    ],

    // Checklist templates an investor can apply to an introduction. Provisional starting points.
    'diligence_templates' => [
        'equity_basic' => [
            'label' => 'Equity: basic checklist',
            'items' => [
                ['title' => 'Certificate of incorporation and company registration', 'category' => 'corporate'],
                ['title' => 'Shareholding (cap) table', 'category' => 'corporate'],
                ['title' => 'Last 12 months management accounts', 'category' => 'financial'],
                ['title' => 'Financial projections (3 years)', 'category' => 'financial'],
                ['title' => 'Key customer contracts or letters of intent', 'category' => 'commercial'],
                ['title' => 'Team CVs and organisation chart', 'category' => 'team'],
            ],
        ],
        'debt_basic' => [
            'label' => 'Debt: basic checklist',
            'items' => [
                ['title' => 'Company registration documents', 'category' => 'corporate'],
                ['title' => 'Audited or reviewed financial statements', 'category' => 'financial'],
                ['title' => 'Bank statements (6 months)', 'category' => 'financial'],
                ['title' => 'Cash-flow forecast and repayment plan', 'category' => 'financial'],
                ['title' => 'Existing loans and collateral schedule', 'category' => 'financial'],
            ],
        ],
        'grant_basic' => [
            'label' => 'Grant: basic checklist',
            'items' => [
                ['title' => 'Company registration documents', 'category' => 'corporate'],
                ['title' => 'Project budget', 'category' => 'financial'],
                ['title' => 'Impact measurement plan', 'category' => 'impact'],
                ['title' => 'Governance and safeguarding policies', 'category' => 'team'],
            ],
        ],
    ],

    // Days before an item's due date that `capital:diligence-reminders` notifies.
    'reminder_days' => 3,

    // Existing opportunity types (Opportunities module) shown as funding opportunities.
    'opportunity_types' => ['grant', 'accelerator', 'investment'],

    // Interaction types recorded in investor_interactions.
    'interaction_types' => [
        'viewed_startup' => 'Viewed funding page',
        'viewed_readiness' => 'Viewed readiness report',
        'expressed_interest' => 'Expressed interest',
        'downloaded_document' => 'Downloaded a document',
        'document_requested' => 'Requested a document',
        'document_fulfilled' => 'Document request fulfilled',
        'document_declined' => 'Document request declined',
        'diligence_item_added' => 'Added a checklist item',
        'diligence_item_updated' => 'Updated a checklist item',
        'scopes_granted' => 'Sharing granted',
        'scopes_revoked' => 'Sharing revoked',
        'watchlisted' => 'Added to watchlist',
        'pipeline_transition' => 'Pipeline stage changed',
        'match_feedback' => 'Gave match feedback',
    ],
];
