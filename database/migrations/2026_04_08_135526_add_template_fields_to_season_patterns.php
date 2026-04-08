<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('season_patterns', function (Blueprint $table) {
            $table->text('description')->nullable()->after('location');
            $table->decimal('estimated_cost', 8, 2)->nullable()->after('max_participants');
            $table->smallInteger('registration_closes_days_before')->unsigned()->nullable()->after('registration_opens_days_before');
            $table->foreignId('dive_site_id')->nullable()->after('whatsapp_group_url')->constrained('dive_sites')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('season_patterns', function (Blueprint $table) {
            $table->dropForeign(['dive_site_id']);
            $table->dropColumn(['description', 'estimated_cost', 'registration_closes_days_before', 'dive_site_id']);
        });
    }
};
