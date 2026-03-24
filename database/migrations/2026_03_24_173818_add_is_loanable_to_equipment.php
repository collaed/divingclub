<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->boolean('is_loanable')->default(true)->after('status');
        });

        // Default non-loanable for types that aren't typically loaned
        DB::table('equipment')
            ->whereIn('type', ['computer', 'other'])
            ->update(['is_loanable' => false]);
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn('is_loanable');
        });
    }
};
