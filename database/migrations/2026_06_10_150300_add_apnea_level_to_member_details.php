<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_details', function (Blueprint $table): void {
            $table->string('apnea_level')->nullable()->after('certification_level');
        });
    }

    public function down(): void
    {
        Schema::table('member_details', function (Blueprint $table): void {
            $table->dropColumn('apnea_level');
        });
    }
};
