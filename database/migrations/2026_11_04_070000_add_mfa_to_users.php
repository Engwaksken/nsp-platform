<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Encrypted with APP_KEY (model casts). Recovery codes are stored as SHA-256 hashes.
            $table->text('mfa_secret')->nullable()->after('password');
            $table->text('mfa_recovery_codes')->nullable()->after('mfa_secret');
            $table->timestamp('mfa_enabled_at')->nullable()->after('mfa_recovery_codes');
            // Last accepted TOTP time step, so a code cannot be replayed.
            $table->unsignedBigInteger('mfa_last_step')->nullable()->after('mfa_enabled_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['mfa_secret', 'mfa_recovery_codes', 'mfa_enabled_at', 'mfa_last_step']);
        });
    }
};
