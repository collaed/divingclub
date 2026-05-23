<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_participants', function (Blueprint $table) {
            $table->dropColumn('legs_driven');
            $table->unsignedSmallInteger('driving_percentage')->default(0)->after('user_id');
        });

        // Also rename event field: driver_bounty_per_leg → driver_bounty_total
        Schema::table('events', function (Blueprint $table) {
            $table->renameColumn('driver_bounty_per_leg', 'driver_bounty_total');
        });
    }

    public function down(): void
    {
        Schema::table('trip_participants', function (Blueprint $table) {
            $table->dropColumn('driving_percentage');
            $table->unsignedSmallInteger('legs_driven')->default(0)->after('user_id');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->renameColumn('driver_bounty_total', 'driver_bounty_per_leg');
        });
    }
};
