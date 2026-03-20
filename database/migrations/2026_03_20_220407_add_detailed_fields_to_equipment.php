<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->string('club_id', 10)->nullable()->after('id');
            $table->string('brand')->nullable()->after('name');
            $table->string('manufacturer')->nullable()->after('brand');
            $table->string('threading', 20)->nullable()->after('manufacturer');
            $table->date('manufacture_date')->nullable()->after('threading');
            $table->decimal('weight_kg', 5, 1)->nullable()->after('manufacture_date');
            $table->string('volume', 20)->nullable()->after('weight_kg');
            $table->string('material', 20)->nullable()->after('volume');
            $table->unsignedSmallInteger('test_pressure_bar')->nullable()->after('material');
            $table->unsignedSmallInteger('working_pressure_bar')->nullable()->after('test_pressure_bar');
            $table->date('last_retest_date')->nullable()->after('working_pressure_bar');
            $table->date('next_retest_date')->nullable()->after('last_retest_date');
            $table->date('last_inventory_date')->nullable()->after('next_retest_date');
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn([
                'club_id', 'brand', 'manufacturer', 'threading', 'manufacture_date',
                'weight_kg', 'volume', 'material', 'test_pressure_bar', 'working_pressure_bar',
                'last_retest_date', 'next_retest_date', 'last_inventory_date',
            ]);
        });
    }
};
