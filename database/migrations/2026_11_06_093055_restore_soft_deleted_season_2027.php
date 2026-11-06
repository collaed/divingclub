<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The 2027 season was soft-deleted on 2026-09-14, leaving no live Season
 * row for that year — every calculate()/componentAmount() call for season
 * '2027' was silently falling back to defaults instead of the real
 * minor-fee/taper settings. The row's data was never actually lost
 * (deleted_at is the only thing that changed), so this just un-deletes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('seasons')->where('year', 2027)->whereNotNull('deleted_at')->update(['deleted_at' => null]);
    }

    public function down(): void
    {
        //
    }
};
