<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Knowledge Hub (owner module M06). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('knowledge_categories')->nullOnDelete();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('knowledge_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 60)->unique();
            $table->timestamps();
        });

        Schema::create('knowledge_resources', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug', 140)->unique();
            $table->string('title', 200);
            $table->string('summary', 1000)->nullable();
            $table->longText('body')->nullable(); // Markdown
            $table->string('resource_type', 40)->index();
            $table->foreignId('category_id')->nullable()->constrained('knowledge_categories')->nullOnDelete();
            // Source and copyright metadata
            $table->string('author', 200)->nullable();
            $table->string('source', 200)->nullable();
            $table->string('publisher', 200)->nullable();
            $table->date('publication_date')->nullable();
            $table->string('license', 60);
            $table->string('external_url', 500)->nullable();
            // Private file (served only through knowledge.download)
            $table->string('file_disk', 20)->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name', 200)->nullable();
            $table->string('file_mime', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            // Access and targeting
            $table->string('visibility', 20)->default('public')->index();
            $table->json('audience')->nullable();
            $table->json('stages')->nullable();
            $table->json('vgi_dimension_keys')->nullable();
            $table->json('builder_step_keys')->nullable();
            // Editorial workflow
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('review_due_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            // SEO and counters
            $table->string('seo_title', 200)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->unsignedInteger('current_version')->default(0);
            $table->timestamps();
            $table->index(['status', 'visibility', 'published_at']);
        });

        Schema::create('knowledge_resource_tag', function (Blueprint $table) {
            $table->foreignId('knowledge_resource_id')->constrained()->cascadeOnDelete();
            $table->foreignId('knowledge_tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['knowledge_resource_id', 'knowledge_tag_id']);
        });

        Schema::create('knowledge_resource_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_resource_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot'); // metadata, body and file reference at publication
            $table->string('file_path')->nullable();
            $table->string('file_name', 200)->nullable();
            $table->string('file_mime', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('change_note', 500)->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['knowledge_resource_id', 'version']);
        });

        Schema::create('knowledge_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('knowledge_resource_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'knowledge_resource_id']);
        });

        Schema::create('knowledge_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('knowledge_resource_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->nullable();
            $table->string('kind', 10)->default('file'); // file | link
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('knowledge_recent_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('knowledge_resource_id')->constrained()->cascadeOnDelete();
            $table->timestamp('viewed_at')->index();
            $table->unique(['user_id', 'knowledge_resource_id']);
        });

        Schema::create('knowledge_index_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_resource_id')->constrained()->cascadeOnDelete();
            $table->string('index_name', 60);
            $table->string('visibility', 20); // scope carried into any future index so it can be filtered there too
            $table->unsignedInteger('version')->nullable();
            $table->string('checksum', 64);
            $table->timestamp('indexed_at')->nullable(); // null = waiting for (re)indexing
            $table->timestamps();
            $table->unique(['knowledge_resource_id', 'index_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_index_metadata');
        Schema::dropIfExists('knowledge_recent_views');
        Schema::dropIfExists('knowledge_downloads');
        Schema::dropIfExists('knowledge_bookmarks');
        Schema::dropIfExists('knowledge_resource_versions');
        Schema::dropIfExists('knowledge_resource_tag');
        Schema::dropIfExists('knowledge_resources');
        Schema::dropIfExists('knowledge_tags');
        Schema::dropIfExists('knowledge_categories');
    }
};
