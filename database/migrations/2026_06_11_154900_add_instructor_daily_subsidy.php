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
            $table->decimal('instructor_daily_subsidy', 8, 2)->nullable()->after('nitrox_supplement');
        });

        Schema::table('trip_participants', function (Blueprint $table): void {
            $table->boolean('is_supervising_instructor')->default(false)->after('prepaid_amount');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('instructor_daily_subsidy');
        });

        Schema::table('trip_participants', function (Blueprint $table): void {
            $table->dropColumn('is_supervising_instructor');
        });
    }
};
