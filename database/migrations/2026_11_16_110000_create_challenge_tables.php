<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner module M05: Challenge Exchange. Its own moderated data model (not merged into opportunities).
 * The working copy lives on `challenges`; the public always reads the immutable snapshot in
 * `challenge_versions` that `published_version_id` points to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('problem_description');
            $table->string('sector', 80)->nullable()->index();
            $table->string('geography')->nullable(); // free text, e.g. "Northern Uganda"
            $table->string('country', 10)->nullable()->index();
            $table->string('region', 120)->nullable();
            $table->text('market_opportunity')->nullable();
            $table->text('existing_solutions')->nullable();
            $table->text('policy_considerations')->nullable();
            $table->json('research_references')->nullable(); // [{title, url, accessed}]
            $table->string('priority', 20)->default('medium')->index();
            $table->string('category', 40)->nullable()->index();
            $table->json('tags')->nullable();
            $table->string('issuer_name')->nullable();
            $table->string('issuer_type', 40)->nullable()->index();
            $table->unsignedBigInteger('organization_id')->nullable()->index(); // organizations module may not exist yet
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visibility', 20)->default('public'); // public, members
            $table->string('status', 30)->default('draft')->index();
            $table->json('vgi_dimension_keys')->nullable();
            $table->json('builder_step_keys')->nullable();
            $table->json('expert_categories')->nullable();
            $table->unsignedInteger('current_version')->default(0);
            $table->unsignedBigInteger('published_version_id')->nullable()->index(); // challenge_versions.id (circular, so no FK)
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('challenge_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            // Searchable/filterable copies of the snapshot, so public search only ever reads published text.
            $table->string('title');
            $table->text('problem_description');
            $table->string('sector', 80)->nullable()->index();
            $table->string('country', 10)->nullable()->index();
            $table->string('region', 120)->nullable();
            $table->string('priority', 20)->index();
            $table->string('category', 40)->nullable()->index();
            $table->string('issuer_name')->nullable();
            $table->string('issuer_type', 40)->nullable()->index();
            $table->string('visibility', 20)->default('public');
            $table->text('tags_text')->nullable();
            $table->json('content'); // full snapshot, including evidence ids
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['challenge_id', 'version']);
        });

        Schema::create('challenge_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->string('source_title');
            $table->string('url', 500)->nullable();
            $table->string('disk', 20)->nullable();
            $table->string('path')->nullable();
            $table->string('original_name', 200)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('evidence_type', 40);
            $table->boolean('validated')->default(false);
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->text('validation_note')->nullable(); // internal
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('challenge_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('challenge_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 30); // approve, reject, request_changes, comment
            $table->string('reason', 40)->nullable();
            $table->text('comments')->nullable();
            $table->text('source_validation_notes')->nullable(); // internal
            $table->timestamps();
            $table->index(['challenge_id', 'created_at']);
        });

        Schema::create('challenge_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['challenge_id', 'user_id']);
        });

        Schema::create('challenge_solution_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('startup_uuid')->nullable()->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->nullOnDelete();
            $table->text('summary');
            $table->text('approach')->nullable();
            $table->string('status', 20)->default('submitted')->index(); // submitted, under_review, shortlisted, declined
            $table->foreignId('status_changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamps();
            $table->index(['challenge_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_solution_submissions');
        Schema::dropIfExists('challenge_bookmarks');
        Schema::dropIfExists('challenge_reviews');
        Schema::dropIfExists('challenge_evidence');
        Schema::dropIfExists('challenge_versions');
        Schema::dropIfExists('challenges');
    }
};
