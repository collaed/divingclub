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
            if (! Schema::hasColumn('events', 'season_pattern_id')) {
                $table->foreignId('season_pattern_id')
                    ->nullable()
                    ->after('season_id')
                    ->constrained('season_patterns')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (Schema::hasColumn('events', 'season_pattern_id')) {
                $table->dropConstrainedForeignId('season_pattern_id');
            }
        });
    }
};
