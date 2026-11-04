<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 3: NSP ⇄ IMS integration (docs/integration-contract-v1.md). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_id', 60)->unique();
            $table->string('name');
            $table->string('secret_hash');
            $table->text('webhook_secret'); // encrypted: needed to sign outgoing events
            $table->json('redirect_uris')->nullable();
            $table->string('webhook_url')->nullable();
            $table->string('recipient_system', 20);
            $table->json('scopes');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('connect_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code_hash', 64)->unique();
            $table->foreignId('api_client_id')->constrained()->cascadeOnDelete();
            $table->uuid('startup_uuid');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('redirect_uri');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id');
            $table->foreignId('api_client_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending')->index(); // pending, delivered, dead
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable();
            $table->unsignedSmallInteger('last_status_code')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'api_client_id']);
        });

        Schema::table('registry_outbox_events', function (Blueprint $table) {
            // When the event was fanned out into per-client deliveries.
            $table->timestamp('dispatched_at')->nullable()->index();
        });

        Schema::create('integration_inbox', function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->foreignId('api_client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 60);
            $table->uuid('subject_uuid')->nullable()->index();
            $table->string('status', 20); // processed, rejected
            $table->string('error')->nullable();
            $table->json('data')->nullable(); // whitelisted fields only
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('received_at')->useCurrent();
        });

        Schema::create('startup_program_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('startup_uuid')->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->string('source_system', 20);
            $table->string('external_application_id', 100);
            $table->string('program_title')->nullable();
            $table->string('cohort_name')->nullable();
            $table->string('status', 30);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('status_changed_at')->nullable();
            $table->date('program_start_date')->nullable();
            $table->date('program_end_date')->nullable();
            $table->boolean('enrolled')->default(false);
            $table->timestamps();
            $table->unique(['source_system', 'external_application_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('startup_program_records');
        Schema::dropIfExists('integration_inbox');
        Schema::table('registry_outbox_events', fn (Blueprint $table) => $table->dropColumn('dispatched_at'));
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('connect_codes');
        Schema::dropIfExists('api_clients');
    }
};
