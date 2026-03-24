<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_details', function (Blueprint $table) {
            $table->string('instructor_initial', 3)->nullable()->after('active_instructor');
            $table->string('instructor_color', 7)->nullable()->after('instructor_initial');
        });
    }

    public function down(): void
    {
        Schema::table('member_details', function (Blueprint $table) {
            $table->dropColumn(['instructor_initial', 'instructor_color']);
        });
    }
};
