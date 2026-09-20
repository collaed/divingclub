<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Junior and Enfant were never categories of their own: a child is a
     * Membre de droit (Fonctionnaire, Associé, Assimilé, Famille) or an
     * Externe member and pays the under-18 share of that cotisation, with the
     * FFESSM licence following the age bands (see
     * FeeCalculationService::minorPercentage()). Removes the two statuses,
     * their fee rows and the "Jeune" eligibility set — but only when nobody
     * still holds them, so no member can be left without a status.
     */
    public function up(): void
    {
        $statusIds = DB::table('member_statuses')->whereIn('slug', ['junior', 'enfant'])->pluck('id');
        if ($statusIds->isNotEmpty() && DB::table('users')->whereIn('status_id', $statusIds)->exists()) {
            return;
        }

        $setIds = DB::table('status_sets')->where('slug', 'jeune')->pluck('id');
        // A member parked in the Jeune set (no status of their own to keep) is simply unclassified again.
        DB::table('users')->whereIn('status_set_id', $setIds)->update(['status_set_id' => null]);
        DB::table('status_set_members')->whereIn('status_set_id', $setIds)->delete();
        DB::table('status_sets')->whereIn('id', $setIds)->delete();

        DB::table('status_set_members')->whereIn('member_status_id', $statusIds)->delete();
        DB::table('membership_fees')->whereIn('status_id', $statusIds)->delete();
        DB::table('member_statuses')->whereIn('id', $statusIds)->delete();
    }

    public function down(): void
    {
        // Intentionally not restored: the statuses no longer exist in the model.
    }
};
