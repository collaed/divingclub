<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_details', function (Blueprint $table) {
            $table->boolean('public_photos_banned')->default(false)->after('show_on_public_site');
        });

        // Set default for existing minors
        DB::statement(
            'UPDATE member_details SET public_photos_banned = 1 WHERE date_of_birth IS NOT NULL AND date_of_birth > DATE_SUB(NOW(), INTERVAL 18 YEAR)'
        );
    }

    public function down(): void
    {
        Schema::table('member_details', function (Blueprint $table) {
            $table->dropColumn('public_photos_banned');
        });
    }
};
