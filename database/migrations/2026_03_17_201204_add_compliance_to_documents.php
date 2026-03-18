<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->boolean('is_compliant')->nullable()->after('is_current');
            $table->text('compliance_notes')->nullable()->after('is_compliant');
            $table->date('reminder_30_sent_at')->nullable()->after('compliance_notes');
            $table->date('reminder_15_sent_at')->nullable()->after('reminder_30_sent_at');
            $table->date('reminder_7_sent_at')->nullable()->after('reminder_15_sent_at');
            $table->date('reminder_0_sent_at')->nullable()->after('reminder_7_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['is_compliant', 'compliance_notes', 'reminder_30_sent_at', 'reminder_15_sent_at', 'reminder_7_sent_at', 'reminder_0_sent_at']);
        });
    }
};
