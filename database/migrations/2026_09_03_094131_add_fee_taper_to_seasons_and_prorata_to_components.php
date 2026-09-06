<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table): void {
            // Season-relative fee tapering schedule.
            // Ordered list of {"from":"MM-DD","pct":<0-100>}. Implicit 100% from
            // season start until the first tier. Null = no tapering (always 100%).
            $table->json('fee_taper_tiers')->nullable()->after('is_active');
        });

        Schema::table('membership_fee_components', function (Blueprint $table): void {
            // Only components flagged eligible are reduced by the taper percentage
            // (the club-retained CEP membership). Licence/insurance/federation stay full.
            $table->boolean('prorata_eligible')->default(false)->after('is_optional');
        });
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table): void {
            $table->dropColumn('fee_taper_tiers');
        });

        Schema::table('membership_fee_components', function (Blueprint $table): void {
            $table->dropColumn('prorata_eligible');
        });
    }
};
