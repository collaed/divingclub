<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->date('last_seen_at')->nullable()->after('last_inventory_date');
        });

        // Backfill from loan history
        DB::statement("
            UPDATE equipment SET last_seen_at = (
                SELECT GREATEST(
                    COALESCE(MAX(loaned_at), '1970-01-01'),
                    COALESCE(MAX(returned_at), '1970-01-01')
                ) FROM equipment_loans WHERE equipment_loans.equipment_id = equipment.id
            )
        ");
        // Override with inventory date if more recent
        DB::statement('
            UPDATE equipment SET last_seen_at = last_inventory_date
            WHERE last_inventory_date IS NOT NULL AND (last_seen_at IS NULL OR last_inventory_date > last_seen_at)
        ');
    }

    public function down(): void
    {
        Schema::table('equipment', fn (Blueprint $t) => $t->dropColumn('last_seen_at'));
    }
};
