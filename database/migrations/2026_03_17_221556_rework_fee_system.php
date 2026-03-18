<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rework: fees are absolute amounts per status per season, not ratios
        // Drop the old multiplier-based columns
        Schema::table('member_statuses', function (Blueprint $t) {
            $t->dropColumn('fee_multiplier');
        });

        // New table: absolute fee amounts per status per season year
        Schema::create('membership_fees', function (Blueprint $t) {
            $t->id();
            $t->string('season_year', 10);  // e.g. "2026"
            $t->foreignId('status_id')->constrained('member_statuses')->cascadeOnDelete();
            $t->decimal('amount', 8, 2);     // absolute amount decided by bureau
            $t->string('label')->nullable();  // e.g. "Cotisation Actif 2026"
            $t->text('notes')->nullable();    // e.g. "Decided at AG 2025-12-15"
            $t->timestamps();
            $t->unique(['season_year', 'status_id']);
        });

        // Keep optional add-ons (insurance, double affiliation) but simplify
        // membership_fee_components already exists — just used for optionals now
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_fees');
        Schema::table('member_statuses', function (Blueprint $t) {
            $t->decimal('fee_multiplier', 5, 2)->default(1.00)->after('slug');
        });
    }
};
