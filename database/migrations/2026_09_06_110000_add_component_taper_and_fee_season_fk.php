<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-component, per-season age taper. When a member is younger than
        // `taper_below_age` at `age_anchor_date` (defaults to season start when
        // null), the component amount is multiplied by `taper_ratio`
        // (0 = free, 0.5 = half). Ratio-based so proportional discounts survive
        // amount changes without editing every component.
        Schema::table('membership_fee_components', function (Blueprint $table): void {
            if (! Schema::hasColumn('membership_fee_components', 'taper_below_age')) {
                $table->unsignedTinyInteger('taper_below_age')->nullable()->after('prorata_eligible');
            }
            if (! Schema::hasColumn('membership_fee_components', 'taper_ratio')) {
                $table->decimal('taper_ratio', 4, 3)->nullable()->after('taper_below_age');
            }
            if (! Schema::hasColumn('membership_fee_components', 'age_anchor_date')) {
                $table->date('age_anchor_date')->nullable()->after('taper_ratio');
            }
        });

        // Join fees to Season explicitly (they previously shared only the
        // season_year string). Backfill by matching seasons.year.
        Schema::table('membership_fees', function (Blueprint $table): void {
            if (! Schema::hasColumn('membership_fees', 'season_id')) {
                $table->foreignId('season_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('seasons')
                    ->nullOnDelete();
            }
        });

        DB::table('membership_fees')->whereNull('season_id')->orderBy('id')->each(function ($fee): void {
            $seasonId = DB::table('seasons')->where('year', $fee->season_year)->value('id');
            if ($seasonId !== null) {
                DB::table('membership_fees')->where('id', $fee->id)->update(['season_id' => $seasonId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('membership_fees', function (Blueprint $table): void {
            if (Schema::hasColumn('membership_fees', 'season_id')) {
                $table->dropConstrainedForeignId('season_id');
            }
        });

        Schema::table('membership_fee_components', function (Blueprint $table): void {
            foreach (['taper_below_age', 'taper_ratio', 'age_anchor_date'] as $col) {
                if (Schema::hasColumn('membership_fee_components', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
