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
        Schema::table('bank_transactions', function (Blueprint $table) {
            // Populated when a match came from the AI-assisted pass — lets a
            // reviewer see why it was suggested before confirming it, since
            // (unlike the rule-based score) there's no formula to inspect.
            $table->text('match_reason')->nullable()->after('match_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropColumn('match_reason');
        });
    }
};
