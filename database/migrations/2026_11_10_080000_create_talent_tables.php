<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5d: talent and recruitment.
 * Spec talent_skills is the skills JSON column on talent_profiles and vacancies.
 * Candidate CVs live on the private disk; each application keeps its own copy,
 * which is purged after config('nsp.talent.cv_retention_days').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talent_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('headline', 150);
            $table->text('summary')->nullable();
            $table->json('skills')->nullable();
            $table->json('work_types')->nullable();
            $table->string('location', 120)->nullable();
            $table->string('country', 10)->nullable();
            $table->boolean('open_to_work')->default(true);
            $table->string('visibility', 20)->default('hidden'); // hidden, startups
            $table->string('consent_version', 20)->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->string('cv_path')->nullable();
            $table->string('cv_name', 200)->nullable();
            $table->timestamps();
        });

        Schema::create('vacancies', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('startup_uuid')->index();
            $table->string('slug', 190)->unique();
            $table->string('title', 150);
            $table->string('type', 20); // full_time, part_time, internship, gig, volunteer
            $table->text('description');
            $table->text('requirements')->nullable();
            $table->json('skills')->nullable();
            $table->string('location', 120)->nullable();
            $table->boolean('remote')->default(false);
            $table->string('pay_note', 150)->nullable();
            $table->date('closes_on')->nullable();
            $table->string('status', 20)->default('draft')->index(); // draft, open, closed, filled, removed
            $table->text('moderation_note')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('job_applications', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('vacancy_uuid')->index();
            $table->uuid('startup_uuid')->index();
            $table->foreignId('applicant_id')->constrained('users')->cascadeOnDelete();
            $table->text('cover_note')->nullable();
            $table->string('cv_path')->nullable(); // this application's own copy
            $table->string('cv_name', 200)->nullable();
            $table->timestamp('cv_purged_at')->nullable();
            $table->string('status', 20)->default('submitted')->index();
            $table->text('team_notes')->nullable(); // the startup team only
            $table->boolean('invited')->default(false); // applied after the startup invited them
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamps();
            $table->unique(['vacancy_uuid', 'applicant_id']);
            $table->foreign('vacancy_uuid')->references('uuid')->on('vacancies')->cascadeOnDelete();
        });

        Schema::create('interviews', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('application_uuid')->index();
            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(45);
            $table->string('mode', 20)->default('online'); // online, in_person, phone
            $table->string('location', 255)->nullable();
            $table->string('status', 20)->default('proposed'); // proposed, confirmed, declined, done, cancelled
            $table->string('candidate_note', 500)->nullable();
            $table->text('team_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign('application_uuid')->references('uuid')->on('job_applications')->cascadeOnDelete();
        });

        Schema::create('hiring_pipeline_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('application_uuid')->index();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('message', 500)->nullable(); // shown to the candidate
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('application_uuid')->references('uuid')->on('job_applications')->cascadeOnDelete();
        });

        Schema::create('vacancy_invitations', function (Blueprint $table) {
            $table->id();
            $table->uuid('vacancy_uuid')->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('message', 1000)->nullable();
            $table->timestamps();
            $table->unique(['vacancy_uuid', 'user_id']);
            $table->foreign('vacancy_uuid')->references('uuid')->on('vacancies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['vacancy_invitations', 'hiring_pipeline_events', 'interviews', 'job_applications', 'vacancies', 'talent_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
