<?php

/**
 * Add emergency-specific fields to dive_sites for fiche de sécurité generation.
 *
 * These fields populate the emergency info block on the FFESSM fiche de sécurité:
 * required safety equipment, emergency phone/VHF, nearest hyperbaric chamber.
 *
 * @author ClubCEP.eu
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dive_sites', function (Blueprint $table) {
            $table->string('emergency_phone')->nullable()->after('nearest_hospital');
            $table->string('vhf_channel')->nullable()->after('emergency_phone');
            $table->text('required_safety_equipment')->nullable()->after('vhf_channel');
            $table->string('nearest_hyperbaric_chamber')->nullable()->after('required_safety_equipment');
            $table->string('hyperbaric_phone')->nullable()->after('nearest_hyperbaric_chamber');
            $table->unsignedSmallInteger('hospital_distance_km')->nullable()->after('hyperbaric_phone');
            $table->unsignedSmallInteger('hyperbaric_distance_km')->nullable()->after('hospital_distance_km');
        });
    }

    public function down(): void
    {
        Schema::table('dive_sites', function (Blueprint $table) {
            $table->dropColumn([
                'emergency_phone', 'vhf_channel', 'required_safety_equipment',
                'nearest_hyperbaric_chamber', 'hyperbaric_phone',
                'hospital_distance_km', 'hyperbaric_distance_km',
            ]);
        });
    }
};
