<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * "Actif" is a system status nobody can choose; the Externe set's default
     * (the status a member classified into it gets) is Externe itself.
     */
    public function up(): void
    {
        $set = DB::table('status_sets')->where('slug', 'externe')->value('id');
        $externe = DB::table('member_statuses')->where('slug', 'externe')->value('id');
        $actif = DB::table('member_statuses')->where('slug', 'actif')->value('id');
        if (! $set || ! $externe || ! $actif) {
            return;
        }

        DB::table('status_set_members')->where('status_set_id', $set)->where('member_status_id', $actif)->update(['is_default' => false]);
        DB::table('status_set_members')->where('status_set_id', $set)->where('member_status_id', $externe)->update(['is_default' => true]);
    }

    public function down(): void
    {
        // Not restored: Actif stays a system status, never a default choice.
    }
};
