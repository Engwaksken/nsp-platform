<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M03 owner alignment (additive only):
 * - methodology status per rubric version: Hive approval makes a version usable,
 *   but it stays "provisional" until the owner's sign-off is recorded;
 * - an append-only log of every staff adjustment to an assessment result.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vgi_frameworks', function (Blueprint $table) {
            $table->string('methodology_status', 20)->default('provisional')->index(); // provisional, owner_approved
            $table->string('owner_signoff_reference', 200)->nullable();
            $table->string('owner_signatory', 200)->nullable();
            $table->date('owner_signed_on')->nullable();
            $table->text('owner_signoff_note')->nullable();
            $table->foreignId('owner_signoff_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('owner_signoff_recorded_at')->nullable();
        });

        Schema::create('vgi_override_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('startup_uuid')->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->uuid('assessment_uuid')->index();
            $table->foreign('assessment_uuid')->references('uuid')->on('vgi_assessments')->cascadeOnDelete();
            $table->foreignId('framework_id')->nullable()->constrained('vgi_frameworks')->nullOnDelete();
            // The calculated result is the original; it is never changed by an override.
            $table->decimal('original_score', 5, 1)->nullable();
            $table->string('original_band_label', 80)->nullable();
            $table->decimal('previous_score', 5, 1)->nullable();
            $table->string('previous_band_label', 80)->nullable();
            $table->decimal('new_score', 5, 1)->nullable();
            $table->string('new_band_label', 80)->nullable();
            $table->text('reason');
            $table->string('actor_role', 120)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vgi_override_logs');
        Schema::table('vgi_frameworks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_signoff_recorded_by');
            $table->dropIndex(['methodology_status']);
            $table->dropColumn(['methodology_status', 'owner_signoff_reference', 'owner_signatory', 'owner_signed_on', 'owner_signoff_note', 'owner_signoff_recorded_at']);
        });
    }
};
