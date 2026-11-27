<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A second, independent star alongside `is_primary` — one member can
     * have a starred scuba cert (`is_primary`) and a separately starred
     * freediving cert (`is_primary_freediving`) at the same time.
     */
    public function up(): void
    {
        Schema::table('user_certification_levels', function (Blueprint $table) {
            $table->boolean('is_primary_freediving')->default(false)->after('is_primary');
        });
    }

    public function down(): void
    {
        Schema::table('user_certification_levels', function (Blueprint $table) {
            $table->dropColumn('is_primary_freediving');
        });
    }
};
