<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->boolean('is_child_sized')->default(false)->after('is_loanable');
            $table->boolean('is_cold_water')->default(false)->after('is_child_sized');
            $table->date('last_inventory_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn(['is_child_sized', 'is_cold_water']);
        });
    }
};
