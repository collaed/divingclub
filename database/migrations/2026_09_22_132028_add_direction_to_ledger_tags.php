<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_tags', function (Blueprint $table): void {
            // Real-world money expectation, not the classification rule's matching
            // breadth (a rule can match either sign while the tag itself always
            // means one direction) — null for tags with no fixed direction (the
            // variable tags, and a genuinely two-way one like "fine").
            $table->string('direction')->nullable()->after('kind'); // in | out | null
        });
    }

    public function down(): void
    {
        Schema::table('ledger_tags', function (Blueprint $table): void {
            $table->dropColumn('direction');
        });
    }
};
