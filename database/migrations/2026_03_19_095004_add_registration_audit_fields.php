<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->text('comment')->nullable()->after('status');
            $table->foreignId('registered_by')->nullable()->after('comment')->constrained('users');
            $table->timestamp('cancelled_at')->nullable()->after('registered_by');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users');
            $table->text('cancel_comment')->nullable()->after('cancelled_by');
        });

        Schema::table('email_log', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('id')->constrained('events')->nullOnDelete();
            $table->string('from_name')->nullable()->after('to_email');
            $table->string('from_email')->nullable()->after('from_name');
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registered_by');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['comment', 'cancelled_at', 'cancel_comment']);
        });

        Schema::table('email_log', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
            $table->dropColumn(['from_name', 'from_email']);
        });
    }
};
