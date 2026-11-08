<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M08 Capital Exchange (owner alignment). Additive only: extends the Phase 5b
 * funding module without changing its tables. Founders control every share;
 * nothing is visible to an investor by default.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Funding profile extras (one per startup). Traction and financial text is encrypted and private by default.
        Schema::create('startup_funding_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('startup_uuid')->unique();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->string('round_stage', 30)->nullable();
            $table->json('instruments_sought')->nullable();
            $table->json('use_of_funds_breakdown')->nullable(); // {category: percent}
            $table->text('traction_summary')->nullable(); // encrypted
            $table->text('financial_summary')->nullable(); // encrypted
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Per-investor granular sharing chosen by the founders.
        Schema::create('capital_share_grants', function (Blueprint $table) {
            $table->id();
            $table->uuid('startup_uuid')->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->foreignId('investor_user_id')->constrained('users')->cascadeOnDelete();
            $table->json('scopes');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['investor_user_id', 'revoked_at']);
        });

        Schema::create('investor_watchlist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investor_user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('startup_uuid')->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['investor_user_id', 'startup_uuid']);
        });

        // The investor's pipeline: one current stage per investor and startup.
        Schema::create('investment_pipeline_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investor_user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('startup_uuid')->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->string('stage', 30)->index();
            $table->timestamp('stage_changed_at')->nullable();
            $table->timestamps();
            $table->unique(['investor_user_id', 'startup_uuid']);
        });

        // Append-only history of every pipeline transition.
        Schema::create('investment_pipeline_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_item_id')->constrained('investment_pipeline_items')->cascadeOnDelete();
            $table->foreignId('investor_user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('startup_uuid')->index();
            $table->string('from_stage', 30)->nullable();
            $table->string('to_stage', 30);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('due_diligence_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('introduction_uuid')->index();
            $table->foreign('introduction_uuid')->references('uuid')->on('investor_introductions')->cascadeOnDelete();
            $table->uuid('startup_uuid')->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->foreignId('investor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('category', 30);
            $table->string('status', 20)->default('requested')->index();
            $table->date('due_date')->nullable()->index();
            $table->text('notes')->nullable(); // visible to both sides
            $table->uuid('data_room_file_uuid')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('capital_document_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('introduction_uuid')->index();
            $table->foreign('introduction_uuid')->references('uuid')->on('investor_introductions')->cascadeOnDelete();
            $table->uuid('startup_uuid')->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->foreignId('investor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('category', 30);
            $table->text('message')->nullable();
            $table->string('status', 20)->default('requested')->index(); // requested, fulfilled, declined
            $table->uuid('data_room_file_uuid')->nullable();
            $table->text('response_note')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('investor_match_feedback', function (Blueprint $table) {
            $table->id();
            $table->uuid('startup_uuid')->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->foreignId('investor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('side', 10); // founder, investor
            $table->foreignId('given_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rating', 20); // useful, not_useful
            $table->string('reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['startup_uuid', 'investor_user_id', 'side'], 'investor_match_feedback_side_unique');
        });

        Schema::create('investor_interactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('startup_uuid')->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->foreignId('investor_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40)->index();
            $table->boolean('visible_to_startup')->default(true);
            $table->json('meta')->nullable(); // labels only, never private content
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        foreach (['investor_interactions', 'investor_match_feedback', 'capital_document_requests', 'due_diligence_items',
            'investment_pipeline_events', 'investment_pipeline_items', 'investor_watchlist', 'capital_share_grants', 'startup_funding_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
