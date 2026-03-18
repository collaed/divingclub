<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Phase 4: Payments & Fees
        Schema::create('membership_fee_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->index();
            $table->decimal('amount', 8, 2);
            $table->boolean('is_base')->default(false);
            $table->boolean('is_optional')->default(false);
            $table->string('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payment_expected', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // membership, event_deposit
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('season_year')->nullable();
            $table->decimal('amount_due', 8, 2);
            $table->string('communication')->nullable();
            $table->json('components')->nullable();
            $table->string('status')->default('pending'); // pending, paid, partial, cancelled
            $table->decimal('amount_paid', 8, 2)->default(0);
            $table->date('paid_at')->nullable();
            $table->string('reconciled_by')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->date('transaction_date');
            $table->decimal('amount', 10, 2);
            $table->string('communication')->nullable();
            $table->string('counterparty')->nullable();
            $table->foreignId('matched_payment_id')->nullable()->constrained('payment_expected')->nullOnDelete();
            $table->integer('match_score')->nullable();
            $table->string('status')->default('unmatched'); // unmatched, matched, confirmed, ignored
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // Phase 5: Equipment
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // bcd, regulator, tank, wetsuit, mask, fins, computer, other
            $table->string('serial_number')->nullable();
            $table->date('purchase_date')->nullable();
            $table->string('condition')->default('good'); // new, good, fair, poor
            $table->string('status')->default('available'); // available, on_loan, maintenance_required, retired
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('equipment_maintenance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
            $table->string('maintenance_name');
            $table->date('due_date');
            $table->date('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->timestamps();
            $table->index(['equipment_id', 'due_date']);
        });

        Schema::create('equipment_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('loaned_at');
            $table->date('returned_at')->nullable();
            $table->foreignId('loaned_by')->nullable()->constrained('users');
            $table->foreignId('returned_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['equipment_id', 'returned_at']);
        });

        // Phase 6: Email System
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('subject');
            $table->text('body'); // supports {{variables}}
            $table->string('locale', 5)->default('en');
            $table->timestamps();
        });

        Schema::create('email_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to_email');
            $table->string('subject');
            $table->text('body')->nullable();
            $table->string('template_slug')->nullable();
            $table->string('status')->default('sent'); // queued, sent, failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();
        });

        // Phase 7: Voting
        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('mode'); // simple, election
            $table->string('status')->default('draft'); // draft, open, closed, cancelled
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('vote_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vote_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('vote_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 128)->unique();
            $table->boolean('is_consumed')->default(false);
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->unique(['vote_id', 'user_id']);
        });

        Schema::create('vote_ballots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vote_option_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash')->nullable(); // null for election (anonymous), set for simple
            $table->timestamps();
            $table->index(['vote_id', 'vote_option_id']);
        });

        // Phase 8: GDPR
        Schema::create('gdpr_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('consent_type'); // data_processing, marketing, photo_publication
            $table->boolean('granted')->default(false);
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'consent_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gdpr_consents');
        Schema::dropIfExists('vote_ballots');
        Schema::dropIfExists('vote_tokens');
        Schema::dropIfExists('vote_options');
        Schema::dropIfExists('votes');
        Schema::dropIfExists('email_log');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('equipment_loans');
        Schema::dropIfExists('equipment_maintenance');
        Schema::dropIfExists('equipment');
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('payment_expected');
        Schema::dropIfExists('membership_fee_components');
    }
};
