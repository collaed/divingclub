<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('trip_participants', 'prepaid_amount')) {
            Schema::table('trip_participants', function (Blueprint $table): void {
                $table->decimal('prepaid_amount', 10, 2)->default(0)->after('local_transit_days');
            });
        }
    }

    public function down(): void
    {
        Schema::table('trip_participants', function (Blueprint $table): void {
            $table->dropColumn('prepaid_amount');
        });
    }
};
