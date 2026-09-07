<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * users.preferred_locale (outgoing email / newsletters) drifted from
 * member_details.preferred_language (the UI locale) because the profile
 * "Langue" form only ever wrote the latter. Backfill from the detail value,
 * which is what the app treats as the source of truth. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('member_details') || ! Schema::hasColumn('users', 'preferred_locale')) {
            return;
        }

        DB::table('member_details')
            ->whereNotNull('preferred_language')
            ->orderBy('user_id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('users')
                        ->where('id', $row->user_id)
                        ->update(['preferred_locale' => $row->preferred_language]);
                }
            }, 'user_id');
    }

    public function down(): void
    {
        // No-op: the previous (possibly stale) preferred_locale is not recoverable.
    }
};
