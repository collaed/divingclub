<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_loans', function (Blueprint $table) {
            $table->date('expected_return_date')->nullable()->after('loaned_at');
            $table->date('reminder_sent_at')->nullable()->after('returned_by');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_loans', function (Blueprint $table) {
            $table->dropColumn(['expected_return_date', 'reminder_sent_at']);
        });
    }
};
