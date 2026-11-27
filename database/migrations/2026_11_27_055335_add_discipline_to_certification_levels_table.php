<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Freediving/apnea levels were lumped into the same category as scuba
     * levels (diver/instructor), with no way to tell them apart except by
     * reading the name — e.g. an FFESSM apnea level's rank could outrank a
     * scuba level's and get picked as a member's "highest" scuba cert.
     * `discipline` separates the two so each gets its own comparison.
     */
    public function up(): void
    {
        Schema::table('certification_levels', function (Blueprint $table) {
            $table->enum('discipline', ['scuba', 'freediving'])->default('scuba')->after('category');
        });

        $federationId = DB::table('federations')->where('acronym', 'FFESSM')->value('id');
        if ($federationId) {
            DB::table('certification_levels')
                ->where('federation_id', $federationId)
                ->whereIn('code', ['ATP', 'APNEA_1', 'AP', 'ACP', 'AEL', 'ACEL', 'AEEL', 'IE1', 'IE2', 'MEF1', 'MEF2'])
                ->update(['discipline' => 'freediving']);
        }
    }

    public function down(): void
    {
        Schema::table('certification_levels', function (Blueprint $table) {
            $table->dropColumn('discipline');
        });
    }
};
