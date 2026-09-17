<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_automation_rules', function (Blueprint $table) {
            // 'registration_close' (default) preserves today's behaviour for
            // every existing rule. 'hours_before_event' checks/fires relative
            // to the event's own start time instead, independent of whenever
            // registrations close.
            $table->enum('trigger', ['registration_close', 'hours_before_event'])
                ->default('registration_close')->after('rule_type');
            $table->unsignedInteger('hours_before_event')->nullable()->after('trigger');
        });

        // Per-rule firing log for hours_before_event rules — needed because a
        // single event can have several such rules (one per rule_type) with
        // different hours_before_event offsets, each of which must be gated
        // and fired independently. See EventAutomationService.
        Schema::create('event_automation_rule_fires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_automation_rule_id')->constrained('event_automation_rules')->cascadeOnDelete();
            $table->timestamp('fired_at');
            $table->unique(['event_id', 'event_automation_rule_id'], 'event_automation_rule_fires_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_automation_rule_fires');

        Schema::table('event_automation_rules', function (Blueprint $table) {
            $table->dropColumn(['trigger', 'hours_before_event']);
        });
    }
};
