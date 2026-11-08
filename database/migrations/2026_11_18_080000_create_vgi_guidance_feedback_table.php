<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vgi_guidance_feedback', function (Blueprint $table) {
            $table->id();
            $table->uuid('startup_uuid');
            $table->foreign('startup_uuid')->references('uuid')->on('startups')->cascadeOnDelete();
            $table->uuid('assessment_uuid');
            $table->foreign('assessment_uuid')->references('uuid')->on('vgi_assessments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('dimension_key', 100);
            $table->string('feedback', 15); // accepted, dismissed
            $table->text('explanation')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'assessment_uuid', 'dimension_key'], 'vgi_guidance_feedback_unique');
            $table->index(['startup_uuid', 'assessment_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vgi_guidance_feedback');
    }
};
