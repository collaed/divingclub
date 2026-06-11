<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_participants', function (Blueprint $table): void {
            $table->unsignedSmallInteger('supervising_days')->default(0)->after('is_supervising_instructor');
        });
    }

    public function down(): void
    {
        Schema::table('trip_participants', function (Blueprint $table): void {
            $table->dropColumn('supervising_days');
        });
    }
};
