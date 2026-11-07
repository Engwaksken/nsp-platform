<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M07 Expert Marketplace (owner alignment), additive to Phase 5c.
 *
 * - categories, credential verification records and profile extras;
 * - service packages, weekly availability and bookings (times stored in UTC);
 * - private conversations, recorded terms acceptance and complaints;
 * - a provider-agnostic, sandbox-only payment trail: intents, webhook receipts and an
 *   append-only ledger. Nothing here settles real money.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expert_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('expert_category_profile', function (Blueprint $table) {
            $table->foreignId('expert_category_id')->constrained('expert_categories')->cascadeOnDelete();
            $table->foreignId('expert_profile_id')->constrained('expert_profiles')->cascadeOnDelete();
            $table->primary(['expert_category_id', 'expert_profile_id']);
            $table->index('expert_profile_id');
        });

        Schema::table('expert_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('expert_profiles', 'location')) {
                $table->string('location', 120)->nullable();
            }
            if (! Schema::hasColumn('expert_profiles', 'is_remote')) {
                $table->boolean('is_remote')->default(true);
            }
            if (! Schema::hasColumn('expert_profiles', 'time_zone')) {
                $table->string('time_zone', 64)->default('Africa/Kampala');
            }
            // Pro bono is the existing offers_free flag; the fee note is the existing rate_note.
        });

        Schema::create('expert_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expert_profile_id')->constrained('expert_profiles')->cascadeOnDelete();
            $table->string('type', 20); // identity, credential, reference
            $table->string('status', 20)->default('pending')->index(); // pending, verified, rejected
            $table->string('title', 150);
            $table->text('details')->nullable(); // e.g. referee name and how to reach them
            $table->string('disk', 20)->nullable();
            $table->string('path')->nullable(); // private disk only
            $table->string('original_name', 200)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });

        Schema::create('expert_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expert_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->string('currency', 3)->nullable();
            $table->boolean('is_pro_bono')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['expert_user_id', 'is_active']);
        });

        Schema::create('expert_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expert_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // ISO 1 (Monday) to 7 (Sunday), in the expert's time zone
            $table->string('start_time', 5); // HH:MM local to the expert
            $table->string('end_time', 5);
            $table->timestamps();
            $table->index(['expert_user_id', 'weekday']);
        });

        Schema::create('expert_bookings', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            // Kept (expert set to null) if the account is deleted, because the payment ledger must stay intact.
            $table->foreignId('expert_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('startup_uuid')->index();
            $table->uuid('engagement_uuid')->nullable()->index();
            $table->foreignId('service_id')->nullable()->constrained('expert_services')->nullOnDelete();
            $table->foreignId('booked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('topic', 150);
            $table->string('start_at', 19); // UTC, Y-m-d H:i:s
            $table->string('end_at', 19); // UTC
            $table->string('founder_timezone', 64);
            $table->string('expert_timezone', 64);
            $table->string('status', 20)->default('requested'); // requested, confirmed, cancelled, completed, no_show
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('payment_status', 20)->default('not_required'); // not_required, requires_payment, processing, paid, refund_pending, refunded, partially_refunded, disputed, failed
            $table->json('policy_snapshot');
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['expert_user_id', 'status', 'start_at']);
            $table->foreign('engagement_uuid')->references('uuid')->on('expert_engagements')->nullOnDelete();
        });

        Schema::create('expert_conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('engagement_uuid')->unique();
            $table->timestamps();
            $table->foreign('engagement_uuid')->references('uuid')->on('expert_engagements')->cascadeOnDelete();
        });

        Schema::create('expert_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('expert_conversations')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('expert_contracts', function (Blueprint $table) {
            $table->id();
            $table->uuid('engagement_uuid')->index();
            $table->uuid('booking_uuid')->nullable();
            $table->string('terms_version', 40);
            $table->string('terms_hash', 64);
            $table->text('terms_text');
            $table->foreignId('expert_accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expert_accepted_at')->nullable();
            $table->foreignId('startup_accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('startup_accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['engagement_uuid', 'terms_version']);
            $table->foreign('engagement_uuid')->references('uuid')->on('expert_engagements')->cascadeOnDelete();
        });

        Schema::create('expert_complaints', function (Blueprint $table) {
            $table->id();
            $table->uuid('engagement_uuid')->nullable()->index();
            $table->uuid('booking_uuid')->nullable();
            $table->foreignId('review_id')->nullable()->constrained('expert_reviews')->nullOnDelete();
            $table->foreignId('raised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 20); // conduct, conversation, booking, payment, review, other
            $table->text('description');
            $table->string('status', 20)->default('open')->index(); // open, reviewing, resolved, dismissed
            $table->text('resolution_note')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->foreign('engagement_uuid')->references('uuid')->on('expert_engagements')->nullOnDelete();
        });

        Schema::table('expert_reviews', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable();
            $table->text('hidden_reason')->nullable();
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->uuid('booking_uuid')->index();
            $table->string('provider', 30);
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('status', 20)->default('requires_payment'); // requires_payment, processing, succeeded, failed, refunded, partially_refunded, disputed
            $table->string('provider_ref', 100)->nullable();
            $table->string('idempotency_key', 100)->unique();
            $table->unsignedBigInteger('refunded_minor')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['provider', 'provider_ref']);
            $table->foreign('booking_uuid')->references('uuid')->on('expert_bookings')->cascadeOnDelete();
        });

        Schema::create('payment_webhook_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('event_id', 100)->nullable(); // set only when the signature is valid
            $table->string('claimed_event_id', 100)->nullable(); // what an unverified request claimed
            $table->string('event_type', 60)->nullable();
            $table->boolean('signature_valid');
            $table->string('payload_hash', 64);
            $table->string('result', 40)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_id']);
        });

        Schema::create('payment_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_intent_id')->constrained('payment_intents')->restrictOnDelete();
            $table->uuid('booking_uuid')->index();
            $table->string('entry_type', 30); // intent_created, payment_succeeded, payment_failed, refund_requested, refund_succeeded, dispute_opened, dispute_won, dispute_lost
            $table->bigInteger('amount_minor'); // signed: money in is positive, refunds negative
            $table->string('currency', 3);
            $table->string('source', 20); // system, webhook
            $table->foreignId('webhook_receipt_id')->nullable()->constrained('payment_webhook_receipts')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // The ledger is append-only. The model refuses updates and deletes; these triggers also stop raw SQL.
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER payment_ledger_no_update BEFORE UPDATE ON payment_ledger_entries BEGIN SELECT RAISE(ABORT, 'payment ledger is append-only'); END;");
            DB::unprepared("CREATE TRIGGER payment_ledger_no_delete BEFORE DELETE ON payment_ledger_entries BEGIN SELECT RAISE(ABORT, 'payment ledger is append-only'); END;");
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::unprepared("CREATE TRIGGER payment_ledger_no_update BEFORE UPDATE ON payment_ledger_entries FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'payment ledger is append-only'");
            DB::unprepared("CREATE TRIGGER payment_ledger_no_delete BEFORE DELETE ON payment_ledger_entries FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'payment ledger is append-only'");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if (in_array($driver, ['sqlite', 'mysql', 'mariadb'], true)) {
            DB::unprepared('DROP TRIGGER IF EXISTS payment_ledger_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS payment_ledger_no_delete');
        }
        foreach (['payment_ledger_entries', 'payment_webhook_receipts', 'payment_intents', 'expert_complaints', 'expert_contracts',
            'expert_messages', 'expert_conversations', 'expert_bookings', 'expert_availability', 'expert_services',
            'expert_verifications', 'expert_category_profile', 'expert_categories'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('expert_reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hidden_by');
            $table->dropColumn(['hidden_at', 'hidden_reason']);
        });
        Schema::table('expert_profiles', function (Blueprint $table) {
            $table->dropColumn(['location', 'is_remote', 'time_zone']);
        });
    }
};
