<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructor_availabilities', function (Blueprint $table) {
            $table->string('activity_type')->default('pool')->after('slot');
        });

        // MySQL won't drop unique if FK references same column — drop FK first
        Schema::table('instructor_availabilities', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('instructor_availabilities', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'date', 'slot']);
        });
        Schema::table('instructor_availabilities', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'date', 'slot', 'activity_type']);
        });
    }

    public function down(): void
    {
        Schema::table('instructor_availabilities', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id', 'date', 'slot', 'activity_type']);
        });
        Schema::table('instructor_availabilities', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'date', 'slot']);
            $table->dropColumn('activity_type');
        });
    }
};
