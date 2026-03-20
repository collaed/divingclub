<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dive_groups', function (Blueprint $table) {
            $table->unsignedSmallInteger('planned_duration')->nullable()->after('planned_depth'); // minutes
            $table->string('gas_mix', 20)->default('air')->after('planned_duration'); // air, nitrox32, nitrox36, trimix, O2
            $table->unsignedTinyInteger('line_number')->nullable()->after('gas_mix'); // 1-4 for fiche de sécurité
            $table->time('planned_entry_time')->nullable()->after('line_number');
            $table->time('planned_exit_time')->nullable()->after('planned_entry_time');
        });
    }

    public function down(): void
    {
        Schema::table('dive_groups', function (Blueprint $table) {
            $table->dropColumn(['planned_duration', 'gas_mix', 'line_number', 'planned_entry_time', 'planned_exit_time']);
        });
    }
};
