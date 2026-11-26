<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('email_log', function (Blueprint $table) {
            // Shared by every per-recipient row produced from the same inbound
            // message/forwarding rule, so threading no longer depends on rows
            // landing in the same wall-clock second (see EmailLog::threadKey()).
            $table->string('batch_id')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_log', function (Blueprint $table) {
            $table->dropColumn('batch_id');
        });
    }
};
