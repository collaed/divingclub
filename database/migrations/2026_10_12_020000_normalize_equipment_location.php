<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `location` was free text with three hardcoded French options in the edit
 * form ('Entrepôt', 'Piscine Merl', 'Hors Service') — the third one an
 * out-of-service state misusing the location field, not an actual place.
 * Normalize to locale-neutral keys (see Equipment::LOCATIONS) so the field
 * can be a real, validated, i18n'd enum. Anything not recognized (including
 * 'Hors Service' or other stray values on an environment we can't inspect
 * directly) falls through to 'other' rather than being left invalid.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('equipment')->where('location', 'Piscine Merl')->update(['location' => 'pool']);
        DB::table('equipment')->where('location', 'Entrepôt')->update(['location' => 'local']);
        DB::table('equipment')
            ->whereNotNull('location')
            ->whereNotIn('location', ['pool', 'local'])
            ->update(['location' => 'other']);
    }

    public function down(): void
    {
        DB::table('equipment')->where('location', 'pool')->update(['location' => 'Piscine Merl']);
        DB::table('equipment')->where('location', 'local')->update(['location' => 'Entrepôt']);
        DB::table('equipment')->where('location', 'other')->update(['location' => 'Hors Service']);
    }
};
