<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->decimal('dive_unit_price', 8, 2)->nullable()->after('local_daily_charge');
            $table->decimal('nitrox_supplement', 8, 2)->nullable()->after('dive_unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['dive_unit_price', 'nitrox_supplement']);
        });
    }
};
