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
        DB::table('member_details')
            ->whereNotNull('date_of_birth')
            ->where('date_of_birth', '>', now()->subYears(18))
            ->update(['public_photos_banned' => true]);
    }

    public function down(): void
    {
        Schema::table('member_details', function (Blueprint $table) {
            $table->dropColumn('public_photos_banned');
        });
    }
};
