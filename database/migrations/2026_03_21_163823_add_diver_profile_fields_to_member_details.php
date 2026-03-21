<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_details', function (Blueprint $table) {
            $table->float('air_consumption')->default(0.5)->after('dive_count');
            $table->float('ease_level')->default(0.5)->after('air_consumption');
            $table->string('primary_intent', 30)->default('exploration')->after('ease_level');
            $table->boolean('is_photographer')->default(false)->after('primary_intent');
            $table->integer('total_dives')->default(0)->after('is_photographer');
            $table->date('last_dive_date')->nullable()->after('total_dives');
        });
    }

    public function down(): void
    {
        Schema::table('member_details', function (Blueprint $table) {
            $table->dropColumn(['air_consumption', 'ease_level', 'primary_intent', 'is_photographer', 'total_dives', 'last_dive_date']);
        });
    }
};
