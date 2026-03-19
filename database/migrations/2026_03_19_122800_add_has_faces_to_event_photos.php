<?php

/**
 * Track face detection results on event photos.
 * Photos with faces are excluded from public/anonymous display.
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
        Schema::table('event_photos', function (Blueprint $table) {
            $table->boolean('has_faces')->nullable()->after('quality_score');
        });
    }

    public function down(): void
    {
        Schema::table('event_photos', function (Blueprint $table) {
            $table->dropColumn('has_faces');
        });
    }
};
