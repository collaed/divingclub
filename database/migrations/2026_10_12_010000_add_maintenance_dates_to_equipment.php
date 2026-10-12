<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->date('last_maintenance_date')->nullable()->after('next_retest_date');
            $table->date('maintenance_due_date')->nullable()->after('last_maintenance_date');
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn(['last_maintenance_date', 'maintenance_due_date']);
        });
    }
};
