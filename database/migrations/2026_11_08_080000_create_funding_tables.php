<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5b: funding and investors. Privacy default (decision D5, option a):
 * investors see nothing private unless a founder shares it with that investor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('organisation')->nullable();
            $table->string('investor_type', 30);
            $table->text('bio')->nullable();
            $table->string('website')->nullable();
            $table->text('thesis')->nullable();
            $table->unsignedBigInteger('ticket_min')->nullable();
            $table->unsignedBigInteger('ticket_max')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->json('sectors')->nullable();
            $table->json('stages')->nullable();
            $table->json('countries')->nullable();
            $table->json('instruments')->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending, verified, rejected, suspended
            $table->text('verification_note')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_listed')->default(true); // visible to founders once verified
            $table->timestamps();
        });

        Schema::create('funding_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('startup_uuid')->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3);
            $table->json('instruments');
            $table->text('pitch'); // written for investors; the only text they see
            $table->text('use_of_funds')->nullable();
            $table->string('status', 20)->default('open')->index(); // open, closed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('data_room_files', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('startup_uuid')->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->string('title');
            $table->string('category', 30);
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('data_room_grants', function (Blueprint $table) {
            $table->id();
            $table->uuid('startup_uuid')->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->foreignId('investor_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['investor_user_id', 'revoked_at']);
        });

        Schema::create('data_room_access_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('file_uuid')->index();
            $table->uuid('startup_uuid')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('access_type', 20); // team, investor
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('accessed_at')->useCurrent();
        });

        Schema::create('investor_introductions', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('startup_uuid')->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->foreignId('investor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('requested_by', 10); // founder, investor
            $table->foreignId('requester_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('message')->nullable();
            $table->string('status', 20)->default('requested'); // requested, accepted, declined, withdrawn
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            // The investor's private pipeline for this startup (never shown to the founders).
            $table->string('investor_stage', 20)->nullable();
            $table->text('investor_notes')->nullable();
            $table->timestamps();
            $table->index(['investor_user_id', 'status']);
        });
    }

    public function down(): void
    {
        foreach (['investor_introductions', 'data_room_access_logs', 'data_room_grants', 'data_room_files', 'funding_requests', 'investor_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
