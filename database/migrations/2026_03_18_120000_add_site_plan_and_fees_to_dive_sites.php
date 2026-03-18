<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dive_sites', function (Blueprint $table) {
            $table->string('site_plan_path')->nullable()->after('map_image_path');
            $table->decimal('entry_fee', 8, 2)->nullable()->after('website_url');
            $table->string('booking_url')->nullable()->after('entry_fee');
        });
    }

    public function down(): void
    {
        Schema::table('dive_sites', function (Blueprint $table) {
            $table->dropColumn(['site_plan_path', 'entry_fee', 'booking_url']);
        });
    }
};
