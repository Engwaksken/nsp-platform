<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4: growth assessments (VGI) and growth plans.
 * A framework version is edited as a draft, approved by an administrator,
 * and then frozen. Assessments keep a copy of the exact version they used,
 * so every score can be recalculated and checked later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vgi_frameworks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('version');
            $table->string('status', 20)->default('draft')->index(); // draft, approved, retired
            $table->text('description')->nullable();
            // The whole rubric: dimensions, weights, questions, options, scores, tips and bands.
            $table->json('definition');
            $table->string('definition_hash', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_note')->nullable();
            $table->timestamps();
            $table->unique(['name', 'version']);
        });

        Schema::create('vgi_assessments', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('startup_uuid')->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->foreignId('framework_id')->constrained('vgi_frameworks');
            $table->string('status', 20)->default('draft'); // draft, submitted
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->decimal('score', 5, 1)->nullable();
            $table->string('band_label', 80)->nullable();
            $table->json('result')->nullable(); // per-dimension scores and gaps
            $table->string('calculation_hash', 64)->nullable();
            // Staff adjustment: never replaces the calculated score, always explained.
            $table->decimal('override_score', 5, 1)->nullable();
            $table->string('override_band_label', 80)->nullable();
            $table->text('override_reason')->nullable();
            $table->foreignId('override_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('override_at')->nullable();
            $table->timestamps();
        });

        Schema::create('vgi_responses', function (Blueprint $table) {
            $table->id();
            $table->uuid('assessment_uuid');
            $table->foreign('assessment_uuid')->references('uuid')->on('vgi_assessments')->cascadeOnDelete();
            $table->string('question_key', 60);
            $table->string('option_key', 60)->nullable();
            $table->text('evidence_note')->nullable();
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['assessment_uuid', 'question_key']);
        });

        Schema::create('growth_actions', function (Blueprint $table) {
            $table->id();
            $table->uuid('startup_uuid')->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->uuid('assessment_uuid')->nullable();
            $table->string('dimension_key', 60)->nullable();
            $table->string('question_key', 60)->nullable();
            $table->string('title');
            $table->text('details')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('todo'); // todo, doing, done
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('growth_updates', function (Blueprint $table) {
            $table->id();
            $table->uuid('startup_uuid')->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->foreignId('growth_action_id')->nullable()->constrained('growth_actions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['growth_updates', 'growth_actions', 'vgi_responses', 'vgi_assessments', 'vgi_frameworks'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
