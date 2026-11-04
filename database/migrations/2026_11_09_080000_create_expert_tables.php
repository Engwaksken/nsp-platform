<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5c: experts, consultants and mentors.
 *
 * Spec table names map as follows: consultation_requests, consultation_bookings and
 * mentorship_matches are one table, expert_engagements (type consultation or
 * mentorship); mentorship_sessions is expert_sessions; expert_skills is the
 * expertise JSON column (a fixed list in config/nsp.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expert_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('headline', 150);
            $table->text('bio');
            $table->string('kind', 20)->default('both'); // mentor, consultant, both
            $table->json('expertise')->nullable();
            $table->json('sectors')->nullable();
            $table->json('languages')->nullable();
            $table->string('website')->nullable();
            $table->string('availability', 500)->nullable();
            $table->boolean('offers_free')->default(true);
            $table->string('rate_note', 255)->nullable();
            $table->unsignedSmallInteger('max_active')->default(5);
            $table->string('status', 20)->default('pending')->index(); // pending, verified, rejected, suspended
            $table->text('verification_note')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_listed')->default(true);
            $table->timestamps();
        });

        Schema::create('expert_engagements', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('startup_uuid')->index();
            $table->foreignId('expert_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20); // consultation, mentorship
            $table->string('source', 20); // founder_request, staff_assignment
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('topic', 150);
            $table->text('details')->nullable();
            $table->string('preferred_times', 255)->nullable();
            $table->string('status', 20)->default('requested')->index(); // requested, active, declined, cancelled, completed
            $table->text('response_note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('outcome')->nullable();
            $table->timestamps();
            $table->index(['expert_user_id', 'status']);
        });

        Schema::create('expert_sessions', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('engagement_uuid')->index();
            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->string('mode', 20)->default('online'); // online, in_person, phone
            $table->string('location', 255)->nullable();
            $table->string('status', 20)->default('scheduled'); // scheduled, completed, cancelled
            $table->text('shared_notes')->nullable(); // the startup team and the expert
            $table->text('expert_notes')->nullable(); // the expert only
            $table->text('startup_notes')->nullable(); // the startup team only
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign('engagement_uuid')->references('uuid')->on('expert_engagements')->cascadeOnDelete();
        });

        Schema::create('session_actions', function (Blueprint $table) {
            $table->id();
            $table->uuid('engagement_uuid')->index();
            $table->uuid('session_uuid')->nullable();
            $table->string('title', 200);
            $table->string('owner', 20)->default('startup'); // startup, expert
            $table->date('due_on')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign('engagement_uuid')->references('uuid')->on('expert_engagements')->cascadeOnDelete();
        });

        Schema::create('expert_reviews', function (Blueprint $table) {
            $table->id();
            $table->uuid('engagement_uuid')->unique();
            $table->uuid('startup_uuid')->index();
            $table->foreignId('expert_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('rating'); // 1 to 5
            $table->text('comment')->nullable(); // the expert and Hive staff only
            $table->boolean('helped')->nullable(); // "Did this help your startup?"
            $table->timestamps();
            $table->foreign('engagement_uuid')->references('uuid')->on('expert_engagements')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['expert_reviews', 'session_actions', 'expert_sessions', 'expert_engagements', 'expert_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
