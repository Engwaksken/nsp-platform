<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 5a: opportunities, events and calls (grants, accelerators, competitions …). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 30)->index();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('organisation')->nullable();
            $table->text('summary');
            $table->longText('body')->nullable(); // Markdown
            $table->string('location')->nullable();
            $table->boolean('is_online')->default(false);
            // Eligibility rules; an empty list means "anyone".
            $table->json('eligibility')->nullable();
            $table->string('amount_text', 120)->nullable();
            $table->timestamp('deadline_at')->nullable()->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('apply_url')->nullable();
            $table->boolean('accepts_interest')->default(false); // registration / expression of interest on NSP
            $table->unsignedInteger('capacity')->nullable();
            $table->string('status', 20)->default('draft')->index(); // pending_review, draft, published, rejected, archived
            $table->text('moderation_note')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('opportunity_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
            $table->unique(['opportunity_id', 'user_id']);
        });

        Schema::create('opportunity_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->uuid('startup_uuid')->nullable();
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message')->nullable();
            $table->string('status', 20)->default('registered'); // registered, confirmed, declined, withdrawn
            $table->timestamps();
            $table->unique(['opportunity_id', 'user_id']);
        });

        Schema::create('recommendation_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('startup_uuid')->nullable();
            $table->string('item_type', 30);
            $table->unsignedBigInteger('item_id');
            $table->string('feedback', 20); // not_relevant, useful
            $table->timestamps();
            $table->unique(['user_id', 'item_type', 'item_id']);
        });
    }

    public function down(): void
    {
        foreach (['recommendation_feedback', 'opportunity_interests', 'opportunity_bookmarks', 'opportunities'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
