<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hive Startup Registry, hosted inside NSP for now (decision D2, docs/decisions.md).
 * Kept self-contained (registry_* concerns only reference users) so it can be
 * extracted into its own service later behind the same API contract.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('startups', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('legal_name');
            $table->string('display_name');
            $table->string('normalized_name')->index();
            $table->string('registration_number', 80)->nullable();
            $table->string('normalized_registration', 80)->nullable()->index();
            $table->string('country', 2)->default('UG');
            $table->unsignedSmallInteger('year_founded')->nullable();
            $table->text('description')->nullable();
            $table->string('website')->nullable();
            $table->string('website_domain')->nullable()->index();
            $table->string('status', 20)->default('active')->index();
            $table->uuid('merged_into_uuid')->nullable()->index();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->timestamp('archived_at')->nullable();
        });

        Schema::create('startup_profiles', function (Blueprint $table) {
            $table->uuid('startup_uuid')->primary();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->string('sector', 80)->nullable();
            $table->string('sub_sector', 80)->nullable();
            $table->string('headquarters_location')->nullable();
            $table->unsignedInteger('team_size')->nullable();
            // Sensitive values are encrypted at rest (model casts) and private by default.
            $table->text('revenue_band')->nullable();
            $table->text('traction_summary')->nullable();
            $table->text('funding_raised_amount')->nullable();
            $table->string('funding_currency', 3)->nullable();
            $table->string('contact_email')->nullable();
            $table->text('contact_phone')->nullable();
            $table->json('visibility')->nullable();
            $table->unsignedTinyInteger('profile_completion')->default(0);
            $table->timestamps();
        });

        Schema::create('startup_memberships', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('startup_uuid');
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            // Shared IdP subject once single sign-on is live; null for local accounts.
            $table->string('user_subject')->nullable();
            $table->string('role', 20);
            $table->string('status', 20);
            $table->string('invited_email')->nullable();
            $table->string('invite_token_hash', 64)->nullable()->unique();
            $table->timestamp('invite_expires_at')->nullable();
            $table->text('request_message')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['startup_uuid', 'status']);
        });

        Schema::create('startup_founders', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('startup_uuid');
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('visibility', 20)->default('members');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('startup_field_changes', function (Blueprint $table) {
            $table->id();
            $table->uuid('startup_uuid')->index();
            $table->string('field_name', 60);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('source_system', 20)->default('nsp');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('startup_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('source_system', 20);
            $table->string('external_id', 100);
            $table->uuid('startup_uuid')->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['source_system', 'external_id']);
        });

        Schema::create('startup_duplicate_candidates', function (Blueprint $table) {
            $table->id();
            $table->uuid('startup_a_uuid');
            $table->uuid('startup_b_uuid');
            $table->unsignedTinyInteger('score');
            $table->json('evidence');
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(
                ['startup_a_uuid', 'startup_b_uuid'],
                'startup_duplicate_pair_unique'
            );
        });

        Schema::create('startup_merges', function (Blueprint $table) {
            $table->id();
            $table->uuid('source_uuid')->index();
            $table->uuid('target_uuid')->index();
            $table->foreignId('candidate_id')->nullable()
                ->constrained('startup_duplicate_candidates')->nullOnDelete();
            $table->json('journal');
            $table->text('reason');
            $table->foreignId('merged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('merged_at');
            $table->foreignId('undone_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('undone_at')->nullable();
        });

        Schema::create('data_sharing_consents', function (Blueprint $table) {
            $table->id();
            $table->uuid('startup_uuid')->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->string('purpose', 40);
            $table->string('recipient_system', 20);
            $table->json('scope');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at');
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->string('policy_version', 20);
            $table->index(['startup_uuid', 'purpose', 'revoked_at']);
        });

        Schema::create('registry_outbox_events', function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->string('type', 60)->index();
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->timestamp('occurred_at');
            $table->string('source', 20)->default('registry');
            $table->uuid('subject_uuid')->index();
            $table->uuid('correlation_id')->nullable();
            $table->json('data');
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
        });
    }

    public function down(): void
    {
        foreach ([
            'registry_outbox_events',
            'data_sharing_consents',
            'startup_merges',
            'startup_duplicate_candidates',
            'startup_aliases',
            'startup_field_changes',
            'startup_founders',
            'startup_memberships',
            'startup_profiles',
            'startups',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};