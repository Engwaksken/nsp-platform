<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7b (PERF-708): indexes for queries that run on every list or KPI page.
 * - consents by purpose: directory, investor listing and marketplace look up all startups with a purpose;
 * - sign-ins by user and time: active startups and returning users KPIs;
 * - assessments by startup and status: growth stage shown in many lists;
 * - published listings, open funding requests and created_at ranges used by the KPIs.
 */
return new class extends Migration
{
    private const INDEXES = [
        'data_sharing_consents' => [['purpose', 'revoked_at']],
        'login_audits' => [['user_id', 'success', 'created_at']],
        'vgi_assessments' => [['startup_uuid', 'status', 'submitted_at']],
        'funding_requests' => [['status', 'startup_uuid']],
        'marketplace_listings' => [['status', 'published_at']],
        'expert_engagements' => [['startup_uuid', 'status']],
        'job_applications' => [['startup_uuid', 'status']],
        'opportunity_bookmarks' => [['created_at']],
        'opportunity_interests' => [['created_at']],
        'investor_introductions' => [['created_at'], ['decided_at']],
        'users' => [['created_at']],
        'startups' => [['status', 'created_at']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexes) {
                foreach ($indexes as $columns) {
                    $blueprint->index($columns);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexes) {
                foreach ($indexes as $columns) {
                    $blueprint->dropIndex($columns);
                }
            });
        }
    }
};
