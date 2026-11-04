<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6c: optional AI writing assistant (off until legal review approves it).
 * No prompt or response text is stored: the log keeps only counts for rate limits,
 * cost tracking and provenance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('version', 20);
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->index(['user_id', 'revoked_at']);
        });

        Schema::create('ai_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('startup_uuid')->nullable()->index();
            $table->string('task', 30);
            $table->string('model', 60)->nullable();
            $table->string('status', 20); // ok, refused, error
            $table->unsignedInteger('input_chars')->default(0);
            $table->unsignedInteger('output_chars')->default(0);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->string('error', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_interactions');
        Schema::dropIfExists('ai_consents');
    }
};
