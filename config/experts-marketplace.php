<?php

/*
| M07 Expert Marketplace settings (owner alignment).
|
| Everything about money here is PROVISIONAL and runs in sandbox only. The owner has not yet
| decided the payment gateway, currency, fees, commission, tax, refund rules, contract wording
| or invoice policy (owner decision register item 5). Do not switch on a real gateway or show
| these numbers as official terms until those decisions are recorded.
*/

return [

    // Default categories created by ExpertCategoriesSeeder. Staff manage the live list in admin.
    'categories' => [
        'mentor' => 'Mentor',
        'consultant' => 'Consultant',
        'researcher' => 'Researcher',
        'legal-adviser' => 'Legal adviser',
        'financial-specialist' => 'Financial specialist',
        'developer' => 'Developer',
        'product-designer' => 'Product designer',
        'marketer' => 'Marketer',
    ],

    // Time zone used when a viewer has not chosen one.
    'default_timezone' => 'Africa/Kampala',

    'booking' => [
        // A founder cannot book a slot that starts sooner than this.
        'min_notice_hours' => (int) env('NSP_EXPERT_BOOKING_MIN_NOTICE_HOURS', 12),
        // How far ahead the slot list reaches.
        'horizon_days' => (int) env('NSP_EXPERT_BOOKING_HORIZON_DAYS', 28),
        // Slots start on this grid inside an availability window.
        'slot_step_minutes' => 30,
    ],

    // Cancellation and refund policy (PROVISIONAL, owner decision 5). A copy is stored on every booking.
    'policy' => [
        'version' => '2026-11-provisional',
        'free_cancellation_hours' => (int) env('NSP_EXPERT_FREE_CANCEL_HOURS', 24),
        'late_cancellation_refund_percent' => (int) env('NSP_EXPERT_LATE_REFUND_PERCENT', 50),
        'expert_cancellation_refund_percent' => 100,
        'no_show_refund_percent' => 0,
        'summary' => 'Provisional: cancel at least 24 hours before the session for a full refund; later cancellations by the startup are refunded 50%; cancellations by the expert are refunded in full. Hive has not approved these rules yet.',
    ],

    // Contract / terms template. Bump the version whenever the wording changes; acceptances keep the version and a hash of the exact text.
    'terms' => [
        'version' => '2026-11-draft-1',
        'title' => 'Expert engagement terms (draft, pending Hive legal review)',
        'paragraphs' => [
            'This records that :expert (the expert) and :startup (the startup) agree to work together on: :topic.',
            'The expert gives advice in good faith based on the information the startup shares. The startup remains responsible for its own decisions.',
            'Both sides keep information shared in this engagement confidential and use it only for this engagement, unless the other side agrees in writing.',
            'Fees, if any, are those shown on the booked service package. While NSP runs in sandbox mode no real money is charged or settled.',
            'Cancellations and refunds follow the NSP policy shown at booking time: :policy',
            'Either side can raise a concern with Hive through the engagement page. Hive may read the engagement conversation only to handle a complaint, and records that it did so.',
        ],
        'label' => 'Recorded acceptance — not a legal e-signature',
    ],

    'payments' => [
        // Only "sandbox" exists. A real provider needs owner decision 5 (provider, currency, fees, settlement, compliance).
        'gateway' => env('NSP_PAYMENTS_GATEWAY', 'sandbox'),
        'currency' => env('NSP_PAYMENTS_CURRENCY', 'UGX'),
        'currencies' => ['UGX', 'USD', 'KES'],
        // Minor units per major unit; UGX has no minor unit in practice.
        'minor_units' => ['UGX' => 1, 'USD' => 100, 'KES' => 100],
        // Platform commission is not decided (owner decision 5). Kept at 0 and never shown as a fee.
        'commission_percent' => 0,
        'webhook_tolerance_seconds' => 300,
        'providers' => [
            'sandbox' => [
                // Secrets live in the environment, never in git. When unset in sandbox, a secret is derived from APP_KEY.
                'webhook_secret' => env('NSP_PAYMENTS_SANDBOX_WEBHOOK_SECRET'),
            ],
        ],
    ],
];
