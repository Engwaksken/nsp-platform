<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner module M02: Venture Builder.
 * Curricula (modules + step definitions) are metadata: edited as a draft,
 * published with four-eyes approval, then frozen. Startups enrol in one
 * published version and stay pinned to it; their submissions, revisions,
 * evidence, reviews, comments, documents and status events are kept even
 * when a newer curriculum version is published.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('builder_curricula', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('version');
            $table->string('status', 20)->default('draft')->index(); // draft, published, retired
            $table->string('methodology_status', 20)->default('provisional'); // provisional, owner_approved
            $table->text('description')->nullable();
            $table->foreignId('copied_from_id')->nullable()->constrained('builder_curricula')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['name', 'version']);
        });

        Schema::create('builder_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_id')->constrained('builder_curricula')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('key', 80);
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('outputs')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['curriculum_id', 'key']);
            $table->index(['curriculum_id', 'position']);
        });

        Schema::create('builder_step_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_id')->constrained('builder_curricula')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('builder_modules')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('key', 80);
            $table->string('title');
            $table->text('guidance')->nullable(); // Markdown
            $table->json('fields');
            $table->json('checklist')->nullable();
            $table->string('evidence_requirement', 10)->default('none'); // none, optional, required
            $table->boolean('requires_review')->default(false);
            $table->text('output_template')->nullable(); // Markdown with {{field}} placeholders
            $table->json('vgi_dimension_keys')->nullable();
            $table->json('prerequisites')->nullable(); // step keys; null = previous step in sequence
            $table->json('task_suggestions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['curriculum_id', 'key']);
            $table->index(['module_id', 'position']);
        });

        Schema::create('venture_builder_enrollments', function (Blueprint $table) {
            $table->id();
            $table->uuid('startup_uuid')->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->foreignId('curriculum_id')->constrained('builder_curricula');
            $table->string('status', 20)->default('active'); // active, superseded
            $table->foreignId('migrated_from_id')->nullable()->constrained('venture_builder_enrollments')->nullOnDelete();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamps();
            $table->unique(['startup_uuid', 'curriculum_id']);
            $table->index(['startup_uuid', 'status']);
        });

        Schema::create('venture_step_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('venture_builder_enrollments')->cascadeOnDelete();
            $table->uuid('startup_uuid')->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->foreignId('step_definition_id')->constrained('builder_step_definitions');
            $table->string('step_key', 80);
            // not_started, in_progress, submitted, changes_requested, validated, completed
            $table->string('status', 20)->default('not_started')->index();
            $table->json('responses')->nullable();
            $table->json('checklist_state')->nullable();
            $table->unsignedInteger('version')->default(1); // optimistic locking
            $table->timestamp('first_submitted_at')->nullable(); // unlocks dependent steps; never cleared
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['enrollment_id', 'step_definition_id']);
            $table->index(['startup_uuid', 'step_key']);
        });

        Schema::create('venture_step_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('venture_step_submissions')->cascadeOnDelete();
            $table->uuid('startup_uuid')->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('reason', 20); // submitted, validated, completed, revised, migrated
            $table->string('status', 20);
            $table->string('step_key', 80);
            $table->string('step_title');
            $table->unsignedInteger('curriculum_version');
            $table->json('field_labels')->nullable();
            $table->json('responses')->nullable();
            $table->json('checklist_state')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['submission_id', 'number']);
            $table->index(['startup_uuid', 'step_key']);
        });

        Schema::create('venture_step_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('venture_step_submissions')->cascadeOnDelete();
            $table->uuid('startup_uuid')->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->string('kind', 10); // file, link
            $table->string('title', 200);
            $table->string('disk', 20)->nullable();
            $table->string('path')->nullable();
            $table->string('original_name', 200)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('url', 2000)->nullable();
            $table->text('source_note')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('venture_step_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('venture_step_submissions')->cascadeOnDelete();
            $table->uuid('startup_uuid')->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 20); // changes_requested, validated
            $table->text('feedback');
            $table->unsignedInteger('revision_number')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('venture_step_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('venture_step_submissions')->cascadeOnDelete();
            $table->uuid('startup_uuid')->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('builder_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->unique()->constrained('venture_step_submissions')->cascadeOnDelete();
            $table->uuid('startup_uuid')->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('current_version')->default(0);
            $table->timestamps();
        });

        Schema::create('builder_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('builder_documents')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('source', 20); // generated, edited
            $table->longText('body'); // Markdown
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['document_id', 'number']);
        });

        Schema::create('builder_step_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('venture_step_submissions')->cascadeOnDelete();
            $table->uuid('startup_uuid')->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        foreach (['builder_step_events', 'builder_document_versions', 'builder_documents', 'venture_step_comments', 'venture_step_reviews',
            'venture_step_evidence', 'venture_step_revisions', 'venture_step_submissions', 'venture_builder_enrollments',
            'builder_step_definitions', 'builder_modules', 'builder_curricula'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
