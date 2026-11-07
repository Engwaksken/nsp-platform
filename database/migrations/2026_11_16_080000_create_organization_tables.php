<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M01 — organizations as tenants, distinct from startups.
 *
 * "open_key"/"active_key" columns hold a value only while a row is live
 * (invited/active membership; requested/active relationship) and are NULL
 * otherwise, so a plain unique index enforces "one live row" on every database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug', 120)->unique();
            $table->string('type', 30)->index();
            $table->string('country', 8)->nullable();
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending, active, suspended
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('suspended_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('organization_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 20); // org_admin, manager, analyst, member
            $table->string('status', 20)->default('invited')->index(); // invited, active, revoked, declined
            $table->string('invited_email')->nullable();
            $table->string('invite_token_hash', 64)->nullable()->unique();
            $table->timestamp('invite_expires_at')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('active_key')->nullable()->unique();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('organization_startup_relationships', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('startup_uuid')->index();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->string('purpose', 30);
            $table->json('scopes');
            $table->string('status', 20)->default('requested')->index(); // requested, active, declined, revoked, expired, withdrawn
            $table->string('initiated_by', 20)->default('organization'); // organization, startup
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->string('open_key')->nullable()->unique();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_startup_relationships');
        Schema::dropIfExists('organization_memberships');
        Schema::dropIfExists('organizations');
    }
};
