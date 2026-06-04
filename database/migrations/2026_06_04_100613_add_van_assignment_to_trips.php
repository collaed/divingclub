<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->tinyInteger('van_count')->unsigned()->nullable()->after('local_daily_charge');
        });

        Schema::table('trip_participants', function (Blueprint $table) {
            $table->tinyInteger('van_number')->unsigned()->nullable()->after('driving_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('van_count');
        });

        Schema::table('trip_participants', function (Blueprint $table) {
            $table->dropColumn('van_number');
        });
    }
};
