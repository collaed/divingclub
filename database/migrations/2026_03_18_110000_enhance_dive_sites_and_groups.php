<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dive_sites', function (Blueprint $table) {
            $table->text('facilities')->nullable()->after('access_notes');
            $table->text('nearest_hospital')->nullable()->after('facilities');
            $table->string('website_url')->nullable()->after('nearest_hospital');
            $table->string('map_image_path')->nullable()->after('image_path');
        });

        // Add purpose/label to dive groups
        Schema::table('dive_groups', function (Blueprint $table) {
            $table->string('purpose')->nullable()->after('dive_mode'); // explo, exercise, certify, autonomous_training, bapteme, navigation, etc.
        });
    }

    public function down(): void
    {
        Schema::table('dive_sites', function (Blueprint $table) {
            $table->dropColumn(['facilities', 'nearest_hospital', 'website_url', 'map_image_path']);
        });
        Schema::table('dive_groups', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });
    }
};
