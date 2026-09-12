<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_details', function (Blueprint $table) {
            $table->boolean('is_lifeguard')->default(false)->after('active_instructor');
        });

        Schema::table('events', function (Blueprint $table) {
            // Guards against firing the same automation rules twice — set the
            // moment registrations close and rules are evaluated.
            $table->timestamp('automation_evaluated_at')->nullable()->after('inscriptions_closed');
        });

        Schema::create('event_automation_rules', function (Blueprint $table) {
            $table->id();
            // Exactly one of these is set: a pattern-level rule is the default
            // for every event generated from it; an event-level rule overrides
            // the pattern's rule of the same type for that one event.
            $table->foreignId('season_pattern_id')->nullable()->constrained('season_patterns')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->cascadeOnDelete();
            $table->enum('rule_type', ['min_registrations', 'requires_lifeguard']);
            $table->unsignedInteger('threshold')->nullable(); // min_registrations only
            $table->boolean('cancels_event')->default(false);
            $table->string('email_subject')->nullable();
            $table->text('email_body')->nullable();
            // Comma-separated fixed addresses, always notified alongside
            // whichever recipients the rule type sends to.
            $table->string('extra_recipients')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_automation_rules');

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('automation_evaluated_at');
        });

        Schema::table('member_details', function (Blueprint $table) {
            $table->dropColumn('is_lifeguard');
        });
    }
};
