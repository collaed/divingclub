<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_loans', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('user_id')->constrained('events')->nullOnDelete();
            $table->string('loan_reason')->nullable()->after('event_id');
            $table->timestamp('loan_email_sent_at')->nullable()->after('reminder_sent_at');
            $table->timestamp('return_email_sent_at')->nullable()->after('loan_email_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_loans', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
            $table->dropColumn(['event_id', 'loan_reason', 'loan_email_sent_at', 'return_email_sent_at']);
        });
    }
};
