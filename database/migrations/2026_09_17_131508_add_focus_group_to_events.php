<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Plain string, not a native enum, for MySQL/PostgreSQL portability
            // (see CLAUDE.md). Validated against config('activity_types.focus_groups')
            // at the request layer instead.
            $table->string('focus_group', 20)->nullable()->after('event_type');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('focus_group');
        });
    }
};
