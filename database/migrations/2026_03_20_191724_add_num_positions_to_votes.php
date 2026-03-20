<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->unsignedTinyInteger('num_positions')->default(1)->after('allow_change');
            $table->unsignedTinyInteger('min_vote_pct')->default(50)->after('num_positions');
        });
    }

    public function down(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->dropColumn(['num_positions', 'min_vote_pct']);
        });
    }
};
