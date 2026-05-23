<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('trip_settlement_enabled')->default(false)->after('estimated_cost');
            $table->decimal('driver_bounty_per_leg', 8, 2)->nullable()->after('trip_settlement_enabled');
            $table->decimal('local_daily_charge', 8, 2)->nullable()->after('driver_bounty_per_leg');
            $table->string('settlement_status', 20)->default('open')->after('local_daily_charge'); // open, closed
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->string('transit_mode', 10)->nullable()->after('comment'); // van, fly, own
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['trip_settlement_enabled', 'driver_bounty_per_leg', 'local_daily_charge', 'settlement_status']);
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn('transit_mode');
        });
    }
};
