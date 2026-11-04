<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5e: marketplace and startup showcase.
 * Spec partnership_requests are marketplace_listings with kind "partnership".
 * Inquiry messages stay inside NSP; reports open a dispute that Hive staff resolve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_listings', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('slug', 190)->unique();
            $table->uuid('startup_uuid')->index();
            $table->string('kind', 20); // offer, request, partnership
            $table->string('category', 40);
            $table->string('title', 150);
            $table->string('summary', 300);
            $table->text('description');
            $table->string('price_note', 150)->nullable();
            $table->string('location', 120)->nullable();
            $table->date('expires_on')->nullable();
            $table->string('status', 20)->default('draft')->index(); // draft, published, closed, removed
            $table->text('moderation_note')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('marketplace_inquiries', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('listing_uuid')->index();
            $table->uuid('startup_uuid')->index(); // the listing's startup
            $table->foreignId('inquirer_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('inquirer_startup_uuid')->nullable(); // when asking on behalf of a startup
            $table->string('subject', 150);
            $table->string('status', 20)->default('open'); // open, closed
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('inquirer_read_at')->nullable();
            $table->timestamp('team_read_at')->nullable();
            $table->timestamps();
            $table->index(['inquirer_id', 'status']);
            $table->foreign('listing_uuid')->references('uuid')->on('marketplace_listings')->cascadeOnDelete();
        });

        Schema::create('marketplace_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('inquiry_uuid')->index();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('side', 10); // inquirer, team
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('inquiry_uuid')->references('uuid')->on('marketplace_inquiries')->cascadeOnDelete();
        });

        Schema::create('marketplace_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('listing_uuid')->nullable()->index();
            $table->uuid('inquiry_uuid')->nullable()->index();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 30); // misleading, scam, offensive, not_delivered, other
            $table->text('details')->nullable();
            $table->string('status', 20)->default('open')->index(); // open, resolved, dismissed
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['marketplace_reports', 'marketplace_messages', 'marketplace_inquiries', 'marketplace_listings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
